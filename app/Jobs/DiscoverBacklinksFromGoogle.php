<?php

namespace App\Jobs;

use App\Models\DiscoveryQuery;
use App\Models\BacklinkGraphLink;
use App\Services\Discovery\GoogleScrapeSource;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DiscoverBacklinksFromGoogle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 300; // 5 minutes

    public function __construct(
        public DiscoveryQuery $query,
    ) {}

    public function handle(): void
    {
        try {
            // Search for backlinks using the discovery query
            $googleScraper = new GoogleScrapeSource();
            $searchQuery = $this->buildSearchQuery();

            Log::info('Starting backlink discovery', [
                'query_id' => $this->query->id,
                'search_query' => $searchQuery,
            ]);

            $urls = $googleScraper->search($searchQuery, 50);

            if ($urls->isEmpty()) {
                $this->query->update([
                    'status' => 'failed',
                    'error' => 'No results found',
                ]);
                return;
            }

            // Store discovered backlinks
            foreach ($urls as $url) {
                try {
                    BacklinkGraphLink::firstOrCreate(
                        [
                            'discovery_query_id' => $this->query->id,
                            'source_url' => $url,
                        ],
                        [
                            'source_domain' => parse_url($url, PHP_URL_HOST),
                            'target_url' => $this->query->project->website_url,
                            'target_domain' => parse_url($this->query->project->website_url, PHP_URL_HOST),
                            'discovered_at' => now(),
                        ]
                    );
                } catch (Exception $e) {
                    Log::warning('Failed to store backlink', [
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->query->update([
                'status' => 'completed',
                'results_found' => $urls->count(),
            ]);

            Log::info('Backlink discovery completed', [
                'query_id' => $this->query->id,
                'results_found' => $urls->count(),
            ]);
        } catch (Exception $e) {
            Log::error('Backlink discovery failed', [
                'query_id' => $this->query->id,
                'error' => $e->getMessage(),
            ]);

            $this->query->update([
                'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }
    }

    /**
     * Build a Google search query from the discovery parameters
     */
    private function buildSearchQuery(): string
    {
        $domain = parse_url($this->query->project->website_url, PHP_URL_HOST);

        // Use keyword + footprint pattern if available
        if ($this->query->footprint_pattern) {
            return "{$this->query->keyword} {$this->query->footprint_pattern}";
        }

        // Default: search for the keyword
        return $this->query->keyword;
    }
}
