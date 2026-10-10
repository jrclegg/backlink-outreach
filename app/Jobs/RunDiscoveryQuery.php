<?php

namespace App\Jobs;

use App\Models\DiscoveryQuery;
use App\Models\DiscoveryResult;
use App\Services\Discovery\GoogleScrapeSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDiscoveryQuery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private DiscoveryQuery $query)
    {
    }

    public function handle(): void
    {
        $this->query->update(['status' => 'running']);

        try {
            $source = new GoogleScrapeSource();
            $query = "\"{$this->query->footprint_pattern}\" {$this->query->keyword}";

            $results = $source->search($query, $this->query->result_cap ?? 25);

            foreach ($results as $result) {
                DiscoveryResult::create([
                    'discovery_query_id' => $this->query->id,
                    'site_url' => $result['url'] ?? $result['link'] ?? null,
                    'page_title' => $result['title'] ?? null,
                    'extraction_status' => 'pending',
                ]);
            }

            $this->query->update([
                'status' => 'completed',
                'results_found' => count($results),
            ]);
        } catch (\Exception $e) {
            $this->query->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
