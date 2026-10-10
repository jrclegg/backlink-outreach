<?php

namespace App\Services\Outreach;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
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
        $html = preg_replace('/<(script|style|nav|footer)\b[^>]*>.*?<\/\1>/is', '', $this->fetch($url));

        return mb_substr(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))), 0, 18000);
    }
}
