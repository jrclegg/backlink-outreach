<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Collection;
use Exception;
use Illuminate\Support\Facades\Log;

class GoogleScrapeSource
{
    private const GOOGLE_SEARCH_URL = 'https://www.google.com/search';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private ?ProxyPool $proxyPool = null;

    public function __construct(?ProxyPool $proxyPool = null)
    {
        $this->proxyPool = $proxyPool;
    }

    /**
     * Search Google for a query and extract URLs from results
     */
    public function search(string $query, int $maxResults = 50): Collection
    {
        $results = collect();

        try {
            $pages = ceil($maxResults / 10);

            for ($page = 0; $page < $pages; $page++) {
                // Random delay between requests to avoid blocking
                sleep(random_int(5, 15));

                $startIndex = $page * 10;
                $urls = $this->fetchPage($query, $startIndex);

                foreach ($urls as $url) {
                    if ($results->count() >= $maxResults) {
                        break 2;
                    }
                    $results->push($url);
                }
            }
        } catch (Exception $e) {
            Log::error('GoogleScrapeSource search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Fetch a single page of Google results
     */
    private function fetchPage(string $query, int $start = 0): Collection
    {
        $urls = collect();

        try {
            $client = Http::withUserAgent(self::USER_AGENT)
                ->timeout(30)
                ->retry(2, 1000);

            // Add proxy if available
            if ($this->proxyPool && $this->proxyPool->hasProxies()) {
                $proxy = $this->proxyPool->getRandomProxy();
                $client = $client->withOptions([
                    'proxy' => $proxy,
                ]);
            }

            $response = $client->get(self::GOOGLE_SEARCH_URL, [
                'q' => $query,
                'start' => $start,
                'num' => 10,
            ]);

            if (!$response->successful()) {
                Log::warning('GoogleScrapeSource page fetch failed', [
                    'query' => $query,
                    'start' => $start,
                    'status' => $response->status(),
                ]);
                return $urls;
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            // Extract URLs from search results
            $crawler->filter('a[href*="/url?q="]')->each(function (Crawler $node) use (&$urls) {
                try {
                    $href = $node->attr('href');
                    if (!$href) {
                        return;
                    }

                    // Extract URL from Google's redirect format
                    if (preg_match('/\/url\?q=([^&]+)/', $href, $matches)) {
                        $url = urldecode($matches[1]);

                        // Filter out Google's own results and other irrelevant URLs
                        if ($this->isValidUrl($url)) {
                            $urls->push($url);
                        }
                    }
                } catch (Exception $e) {
                    Log::debug('Failed to extract URL from search result', [
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        } catch (Exception $e) {
            Log::error('GoogleScrapeSource fetch page error', [
                'query' => $query,
                'start' => $start,
                'error' => $e->getMessage(),
            ]);
        }

        return $urls;
    }

    /**
     * Validate if URL is suitable for prospect discovery
     */
    private function isValidUrl(string $url): bool
    {
        // Must be valid URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST) ?? '';

        // Exclude Google domains
        if (str_contains($host, 'google.')) {
            return false;
        }

        // Exclude social media platforms
        $excluded = ['facebook.com', 'twitter.com', 'instagram.com', 'linkedin.com', 'youtube.com', 'tiktok.com'];
        foreach ($excluded as $domain) {
            if (str_contains($host, $domain)) {
                return false;
            }
        }

        // Exclude known non-business domains
        $nonBusiness = ['wikipedia.org', 'reddit.com', 'github.com', 'stackoverflow.com'];
        foreach ($nonBusiness as $domain) {
            if (str_contains($host, $domain)) {
                return false;
            }
        }

        return true;
    }
}
