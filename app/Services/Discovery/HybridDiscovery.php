<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Symfony\Component\DomCrawler\Crawler;
use Exception;
use Illuminate\Support\Facades\Log;

class HybridDiscovery
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Find prospects by searching for sites in the same niche
     */
    public function findProspects(string $query, int $maxResults = 50): Collection
    {
        $prospects = collect();

        try {
            // Try multiple search strategies with fallbacks

            // Strategy 1: If query doesn't have outreach keywords, add them
            if (!$this->hasOutreachKeywords($query)) {
                // Try "write for us" variant
                $results = $this->searchDuckDuckGo('"write for us" ' . $query, $maxResults);
                if ($results->isNotEmpty()) {
                    return $results->take($maxResults);
                }

                // Try "guest post" variant
                $results = $this->searchDuckDuckGo('"guest post" ' . $query, $maxResults);
                if ($results->isNotEmpty()) {
                    return $results->take($maxResults);
                }

                // Try "contribute" variant
                $results = $this->searchDuckDuckGo('"contribute" ' . $query, $maxResults);
                if ($results->isNotEmpty()) {
                    return $results->take($maxResults);
                }

                // Try "submit" variant
                $results = $this->searchDuckDuckGo('"submit article" ' . $query, $maxResults);
                if ($results->isNotEmpty()) {
                    return $results->take($maxResults);
                }

                // Final fallback: just search the query as-is
                return $this->searchDuckDuckGo($query, $maxResults)->take($maxResults);
            }

            // If query already has keywords, search as-is
            $results = $this->searchDuckDuckGo($query, $maxResults);
            if ($results->isNotEmpty()) {
                return $results->take($maxResults);
            }

            // Fallback to Bing
            return $this->searchBing($query, $maxResults)->take($maxResults);
        } catch (Exception $e) {
            Log::error('HybridDiscovery failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
        }

        return $prospects;
    }

    /**
     * Check if query contains outreach-related keywords
     */
    private function hasOutreachKeywords(string $query): bool
    {
        $keywords = ['write for us', 'guest post', 'guest author', 'contribute', 'submit article', 'submit post', 'intitle:'];
        foreach ($keywords as $keyword) {
            if (stripos($query, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Search using Bing (more lenient than Google)
     */
    private function searchBing(string $query, int $maxResults): Collection
    {
        $results = collect();

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout(15)
                ->retry(1, 1000)
                ->get('https://www.bing.com/search', [
                    'q' => $query,
                    'count' => min($maxResults, 50),
                ]);

            if (!$response->successful()) {
                return $results;
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            // Extract links from Bing results
            $crawler->filter('h2 a, .b_title')->each(function (Crawler $node) use (&$results, $maxResults) {
                if ($results->count() >= $maxResults) {
                    return;
                }

                try {
                    $href = $node->attr('href');
                    $title = trim($node->text());

                    if (!$href || !filter_var($href, FILTER_VALIDATE_URL)) {
                        return;
                    }

                    // Skip irrelevant domains
                    $host = parse_url($href, PHP_URL_HOST);
                    if ($this->isValidProspect($host)) {
                        $results->push([
                            'url' => $href,
                            'title' => $title,
                            'source' => 'bing',
                        ]);
                    }
                } catch (Exception $e) {
                    Log::debug('Failed to parse Bing result', ['error' => $e->getMessage()]);
                }
            });
        } catch (Exception $e) {
            Log::debug('Bing search failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Search using DuckDuckGo (very lenient, no blocking)
     */
    private function searchDuckDuckGo(string $query, int $maxResults): Collection
    {
        $results = collect();

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout(15)
                ->retry(1, 1000)
                ->get('https://duckduckgo.com/html', [
                    'q' => $query,
                    'kl' => 'en-us',
                ]);

            if (!$response->successful()) {
                return $results;
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            // Extract links from DuckDuckGo results
            $crawler->filter('.result__title a, .result__a')->each(function (Crawler $node) use (&$results, $maxResults) {
                if ($results->count() >= $maxResults) {
                    return;
                }

                try {
                    $href = $node->attr('href');
                    $title = trim($node->text());

                    if (!$href) {
                        return;
                    }

                    // Clean up DuckDuckGo redirect URLs
                    if (strpos($href, 'uddg=') !== false) {
                        preg_match('/uddg=(.+?)(&|$)/', $href, $matches);
                        if (isset($matches[1])) {
                            $href = urldecode($matches[1]);
                        }
                    }

                    if (!filter_var($href, FILTER_VALIDATE_URL)) {
                        return;
                    }

                    // Skip irrelevant domains
                    $host = parse_url($href, PHP_URL_HOST);
                    if ($this->isValidProspect($host)) {
                        $results->push([
                            'url' => $href,
                            'title' => $title,
                            'source' => 'duckduckgo',
                        ]);
                    }
                } catch (Exception $e) {
                    Log::debug('Failed to parse DuckDuckGo result', ['error' => $e->getMessage()]);
                }
            });
        } catch (Exception $e) {
            Log::debug('DuckDuckGo search failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Check if a domain is a valid prospect (filter out junk)
     */
    private function isValidProspect(?string $host): bool
    {
        if (!$host) {
            return false;
        }

        // Exclude irrelevant domains
        $excluded = [
            'google', 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube',
            'reddit', 'wikipedia', 'github', 'stackoverflow', 'medium', 'substack',
            'quora', 'pinterest', 'bing.com',
        ];

        foreach ($excluded as $domain) {
            if (str_contains($host, $domain)) {
                return false;
            }
        }

        return true;
    }
}
