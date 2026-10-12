<?php

namespace App\Services\Outreach;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class PublicPage
{
    public static function validUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'])
            && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS);
    }

    public function fetch(string $url): string
    {
        for ($redirect = 0; $redirect < 5; $redirect++) {
            if (! self::validUrl($url)) {
                throw new RuntimeException('Enter a public HTTP or HTTPS page URL.');
            }
            $host = parse_url($url, PHP_URL_HOST);
            $port = parse_url($url, PHP_URL_PORT) ?: (str_starts_with($url, 'https:') ? 443 : 80);
            if (! in_array($port, [80, 443])) {
                throw new RuntimeException('Only standard web ports are supported.');
            }
            $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_column(dns_get_record($host, DNS_A) ?: [], 'ip');
            if (! $addresses) {
                throw new RuntimeException('The page hostname could not be resolved.');
            }
            foreach ($addresses as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new RuntimeException('Only public websites can be fetched.');
                }
            }
            $response = Http::timeout(20)->connectTimeout(5)->withUserAgent('BacklinkOutreach/1.0')
                ->withOptions([
                    'allow_redirects' => false,
                    'proxy' => '',
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$addresses[0]}"]],
                    'on_headers' => function ($response) {
                        if ((int) $response->getHeaderLine('Content-Length') > 3_000_000) {
                            throw new RuntimeException('Page is too large.');
                        }
                    },
                    'progress' => function ($total, $downloaded) {
                        if ($downloaded > 3_000_000) {
                            throw new RuntimeException('Page is too large.');
                        }
                    },
                ])->get($url);
            if ($response->redirect()) {
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location')));

                continue;
            }
            $response->throw();

            return $response->body();
        }
        throw new RuntimeException('Too many page redirects.');
    }

    public function text(string $url): string
    {
        $raw = $this->fetch($url);
        $body = self::bodyText($raw);
        // JavaScript-built pages (Interfolio, many US job portals) come back as an empty shell, so render those in a browser.
        if (mb_strlen($body) < 1500 || preg_match('/enable javascript|\{\{\s*\$?\w|^\s*loading\W*$/i', $body)) {
            try {
                $rendered = $this->render($url);
                if (mb_strlen(self::bodyText($rendered)) > mb_strlen($body)) {
                    $raw = $rendered;
                    $body = self::bodyText($rendered);
                }
            } catch (\Throwable) {
                // Keep the plain fetch; the quality check will reject the page if it is still unusable.
            }
        }
        // Contact emails usually sit in footers, mailto links or Cloudflare-encoded spans that the text drops,
        // so list them first where truncation can't remove them.
        $emails = self::emails($raw);
        $prefix = $emails ? 'Email addresses published on this page: '.implode(', ', $emails).'. ' : '';

        return mb_substr($prefix.$body, 0, 18000);
    }

    public function render(string $url): string
    {
        $result = Process::timeout(75)->path(base_path())
            ->run([config('outreach.node_binary'), resource_path('scripts/render-page.mjs'), $url]);
        if (! $result->successful() || trim($result->output()) === '') {
            throw new RuntimeException('Page could not be rendered.');
        }

        return $result->output();
    }

    private static function bodyText(string $html): string
    {
        // Job portals such as Workday publish the full advert as JobPosting structured data, which the script strip would drop.
        $posting = '';
        preg_match_all('/<script\b[^>]*application\/ld\+json[^>]*>(.*?)<\/script>/is', $html, $blocks);
        foreach ($blocks[1] as $block) {
            $data = json_decode(html_entity_decode($block), true);
            foreach (is_array($data) && array_is_list($data) ? $data : [$data] as $item) {
                if (is_array($item) && ($item['@type'] ?? null) === 'JobPosting') {
                    $posting .= ' '.implode('. ', array_filter([$item['title'] ?? null, $item['hiringOrganization']['name'] ?? null,
                        isset($item['datePosted']) ? 'Date posted: '.$item['datePosted'] : null,
                        isset($item['validThrough']) ? 'Valid through: '.$item['validThrough'] : null,
                        strip_tags(str_replace(['<br>', '</p>', '</li>'], ' ', html_entity_decode($item['description'] ?? '')))], 'is_string'));
                }
            }
        }
        $html = preg_replace('/<(script|style|nav|footer)\b[^>]*>.*?<\/\1>/is', '', $html);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html)).$posting));
    }

    public static function emails(string $html): array
    {
        $decoded = [];
        preg_match_all('/data-cfemail="([0-9a-f]+)"|email-protection#([0-9a-f]+)/i', $html, $encoded, PREG_SET_ORDER);
        foreach ($encoded as $match) {
            $bytes = array_map('hexdec', str_split($match[1] ?: $match[2], 2));
            $key = array_shift($bytes);
            $decoded[] = implode('', array_map(fn ($byte) => chr($byte ^ $key), $bytes));
        }
        preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9\-]+(?:\.[a-z0-9\-]+)*\.[a-z]{2,}/i', html_entity_decode(urldecode($html)).' '.implode(' ', $decoded), $found);

        return array_values(array_unique(array_filter(array_map('strtolower', $found[0]),
            fn ($email) => ! preg_match('/\.(png|jpe?g|gif|webp|svg|css|js)$|@\d+x\.|sentry|wixpress|example\.com/', $email))));
    }
}
