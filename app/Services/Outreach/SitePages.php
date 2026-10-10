<?php

namespace App\Services\Outreach;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Symfony\Component\DomCrawler\Crawler;

class SitePages
{
    public function discover(string $website): array
    {
        $pages = [];
        $host = fn (string $url) => strtolower(preg_replace('/^www\./i', '', parse_url($url, PHP_URL_HOST) ?? ''));
        $resolve = function (string $url) use ($website, $host): ?string {
            try {
                $url = (string) UriResolver::resolve(new Uri($website), new Uri(trim($url)))->withFragment('');
                if (! PublicPage::validUrl($url) || $host($url) !== $host($website) || strlen($url) > 2048) {
                    return null;
                }
                if (preg_match('/\.(?:jpg|jpeg|png|gif|svg|webp|pdf|zip|css|js|xml|gz)$/i', parse_url($url, PHP_URL_PATH) ?? '')) {
                    return null;
                }

                return $url;
            } catch (\Throwable) {
                return null;
            }
        };
        $add = function (string $url, string $title = '') use (&$pages, $resolve) {
            if (count($pages) >= 500 || ! ($url = $resolve($url))) {
                return;
            }
            $title = trim(preg_replace('/\s+/u', ' ', $title));
            $pages[$url] = ['url' => $url, 'title' => mb_substr($title ?: $url, 0, 255)];
        };
        $reader = app(PublicPage::class);
        try {
            $crawler = new Crawler($reader->fetch($website));
            $add($website, $crawler->filter('title')->count() ? $crawler->filter('title')->text() : 'Home');
            foreach ($crawler->filter('a[href]') as $link) {
                $add($link->getAttribute('href'), $link->textContent);
            }
        } catch (\Throwable) {
            // Sitemaps can still be available when the homepage cannot be read.
        }
        $origin = (string) (new Uri($website))->withPath('/')->withQuery('')->withFragment('');
        $pending = [$origin.'sitemap.xml'];
        $visited = [];
        while ($pending && count($visited) < 5 && count($pages) < 500) {
            $url = array_shift($pending);
            if (isset($visited[$url]) || ! PublicPage::validUrl($url) || $host($url) !== $host($website)) {
                continue;
            }
            $visited[$url] = true;
            try {
                $xml = $reader->fetch($url);
                if (stripos($xml, '<!DOCTYPE') !== false) {
                    continue;
                }
                $document = new \DOMDocument;
                if (! @$document->loadXML($xml, LIBXML_NONET)) {
                    continue;
                }
                $xpath = new \DOMXPath($document);
                foreach ($xpath->query('/*[local-name()="sitemapindex"]/*[local-name()="sitemap"]/*[local-name()="loc"]') as $loc) {
                    if (count($pending) < 20) {
                        $pending[] = trim($loc->textContent);
                    }
                }
                foreach ($xpath->query('/*[local-name()="urlset"]/*[local-name()="url"]/*[local-name()="loc"]') as $loc) {
                    $candidate = trim($loc->textContent);
                    if (! isset($pages[$candidate])) {
                        $add($candidate);
                    }
                }
            } catch (\Throwable) {
                // Keep pages found in other sources if a sitemap is unavailable.
            }
        }
        ksort($pages);

        return array_values($pages);
    }
}
