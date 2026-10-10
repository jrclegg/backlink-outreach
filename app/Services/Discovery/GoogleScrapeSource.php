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
        // DEMO MODE: Return mock results for testing
        // In production, replace with actual Google scraping or use a paid SERP API
        // Note: Google actively blocks bots and requires proxies + careful rate limiting
        return $this->getMockResults($maxResults);
    }

    private function getMockResults(int $maxResults): Collection
    {
        $mockResults = [
            ['url' => 'https://coworkingspaces-cardiff.com/blog', 'title' => 'Cardiff Coworking Spaces Guide'],
            ['url' => 'https://businesscardiff.co.uk/coworking', 'title' => 'Coworking Options in Cardiff'],
            ['url' => 'https://cardiffenterprise.com/spaces', 'title' => 'Enterprise Coworking Cardiff'],
            ['url' => 'https://techcardiff.io/community', 'title' => 'Tech Community Cardiff'],
            ['url' => 'https://cardiff-startup-hub.com', 'title' => 'Cardiff Startup Hub'],
            ['url' => 'https://workspaces-wales.com/cardiff', 'title' => 'Shared Workspaces Cardiff'],
            ['url' => 'https://cardiff-business-lounge.co.uk', 'title' => 'Business Lounge Cardiff'],
            ['url' => 'https://flexspace-cardiff.com', 'title' => 'Flex Space Solutions'],
            ['url' => 'https://cardiff-office-hub.com', 'title' => 'Office Hub Cardiff'],
            ['url' => 'https://workspace-collective-wales.com', 'title' => 'Workspace Collective Wales'],
        ];

        return collect($mockResults)->take($maxResults);
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
