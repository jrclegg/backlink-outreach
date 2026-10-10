<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Symfony\Component\DomCrawler\Crawler;
use Exception;
use Illuminate\Support\Facades\Log;

class BacklinkDiscovery
{
    private const WAYBACK_CDX_API = 'https://web.archive.org/cdx/search/cdx';
    private const WAYBACK_SNAPSHOT = 'https://web.archive.org/web/{timestamp}/{url}';

    /**
     * Find backlinks to a domain using Wayback Machine
     * Returns pages that link to the target domain
     */
    public function findBacklinks(string $targetDomain, int $maxResults = 50): Collection
    {
        $backlinks = collect();

        try {
            // Get CDX (Capture inDeX) data from Wayback Machine
            $response = Http::timeout(30)->get(self::WAYBACK_CDX_API, [
                'url' => $targetDomain,
                'matchType' => 'domain',
                'output' => 'json',
                'collapse' => 'statuscode',
                'filter' => 'statuscode:200',
                'gzip' => 'false',
            ]);

            if (!$response->successful()) {
                Log::warning('Wayback CDX API failed', [
                    'domain' => $targetDomain,
                    'status' => $response->status(),
                ]);
                return $backlinks;
            }

            $data = $response->json();
            if (empty($data) || count($data) < 2) {
                return $backlinks;
            }

            // First row is headers, rest are results
            $results = array_slice($data, 1);

            foreach ($results as $result) {
                if ($backlinks->count() >= $maxResults) {
                    break;
                }

                if (count($result) >= 2) {
                    $timestamp = $result[1];
                    $originalUrl = $result[2];

                    // Fetch the archived page and extract outbound links
                    $snapshotUrl = str_replace('{timestamp}', $timestamp, self::WAYBACK_SNAPSHOT);
                    $snapshotUrl = str_replace('{url}', $originalUrl, $snapshotUrl);

                    $outboundLinks = $this->extractOutboundLinks($snapshotUrl, $targetDomain);

                    foreach ($outboundLinks as $link) {
                        if ($backlinks->count() >= $maxResults) {
                            break 2;
                        }
                        $backlinks->push([
                            'url' => $link['url'],
                            'title' => $link['title'] ?? null,
                            'source_page' => $originalUrl,
                            'snapshot_url' => $snapshotUrl,
                        ]);
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('BacklinkDiscovery failed', [
                'domain' => $targetDomain,
                'error' => $e->getMessage(),
            ]);
        }

        return $backlinks;
    }

    /**
     * Extract outbound links from a page that point to target domain
     */
    private function extractOutboundLinks(string $pageUrl, string $targetDomain): Collection
    {
        $links = collect();

        try {
            $response = Http::withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36')
                ->timeout(15)
                ->retry(1, 500)
                ->get($pageUrl);

            if (!$response->successful()) {
                return $links;
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            // Find all links
            $crawler->filter('a[href]')->each(function (Crawler $node) use (&$links, $targetDomain) {
                try {
                    $href = $node->attr('href');
                    $text = trim($node->text());

                    if (!$href) {
                        return;
                    }

                    // Make relative URLs absolute
                    if (str_starts_with($href, '/')) {
                        $href = 'https://' . parse_url($href, PHP_URL_HOST) . $href;
                    }

                    // Check if it's a valid external link
                    if (!filter_var($href, FILTER_VALIDATE_URL)) {
                        return;
                    }

                    $linkHost = parse_url($href, PHP_URL_HOST);

                    // We want links TO the target domain, not FROM it
                    if ($linkHost && str_contains($linkHost, str_replace('www.', '', $targetDomain))) {
                        $links->push([
                            'url' => $href,
                            'title' => $text ?: null,
                        ]);
                    }
                } catch (Exception $e) {
                    Log::debug('Failed to extract link', ['error' => $e->getMessage()]);
                }
            });
        } catch (Exception $e) {
            Log::warning('Failed to fetch page for link extraction', [
                'url' => $pageUrl,
                'error' => $e->getMessage(),
            ]);
        }

        return $links;
    }
}
