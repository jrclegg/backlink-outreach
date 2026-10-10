<?php

namespace App\Services\Discovery;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use Exception;
use Illuminate\Support\Facades\Log;

class EmailExtractor
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

    /**
     * Extract email addresses from a URL
     */
    public function extract(string $url): Collection
    {
        $emails = collect();

        try {
            // Try main page
            $emails = $this->extractFromUrl($url);

            if ($emails->isNotEmpty()) {
                return $emails;
            }

            // Try common contact page paths
            $paths = ['/contact', '/contact-us', '/about', '/about-us', '/team', '/mailto'];
            $baseUrl = $this->getBaseUrl($url);

            foreach ($paths as $path) {
                $emails = $this->extractFromUrl($baseUrl . $path);
                if ($emails->isNotEmpty()) {
                    return $emails;
                }
            }
        } catch (Exception $e) {
            Log::debug('EmailExtractor error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return $emails;
    }

    /**
     * Extract emails from a specific URL
     */
    private function extractFromUrl(string $url): Collection
    {
        $emails = collect();

        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout(10)
                ->retry(1, 500)
                ->get($url);

            if (!$response->successful()) {
                return $emails;
            }

            $html = $response->body();

            // Extract emails using regex
            $emailRegex = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
            if (preg_match_all($emailRegex, $html, $matches)) {
                $foundEmails = collect($matches[0])
                    ->filter(fn($email) => $this->isValidBusinessEmail($email))
                    ->unique();

                $emails = $emails->merge($foundEmails);
            }

            // Also try to find mailto links
            try {
                $crawler = new Crawler($html);
                $crawler->filter('a[href*="mailto:"]')->each(function (Crawler $node) use (&$emails) {
                    $href = $node->attr('href');
                    if (preg_match('/mailto:([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $href, $matches)) {
                        if ($this->isValidBusinessEmail($matches[1])) {
                            $emails->push($matches[1]);
                        }
                    }
                });
            } catch (Exception $e) {
                // Crawler parsing failed, continue with regex results
            }
        } catch (Exception $e) {
            Log::debug('Failed to fetch URL for email extraction', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return $emails->unique();
    }

    /**
     * Check if email is a legitimate business email (not noreply, etc.)
     */
    private function isValidBusinessEmail(string $email): bool
    {
        $email = strtolower($email);

        // Filter out common non-contact emails
        $patterns = [
            'noreply',
            'no-reply',
            'donotreply',
            'mailer',
            'postmaster',
            'admin@',
            'webmaster@',
            'test@',
            'fake@',
            'example@',
            'localhost@',
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($email, $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get base URL from a full URL
     */
    private function getBaseUrl(string $url): string
    {
        $parts = parse_url($url);
        return $parts['scheme'] . '://' . $parts['host'];
    }
}
