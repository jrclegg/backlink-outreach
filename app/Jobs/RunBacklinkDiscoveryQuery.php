<?php

namespace App\Jobs;

use App\Models\DiscoveryQuery;
use App\Models\DiscoveryResult;
use App\Services\Discovery\BacklinkDiscovery;
use App\Services\Discovery\EmailExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunBacklinkDiscoveryQuery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private DiscoveryQuery $query)
    {
    }

    public function handle(): void
    {
        $this->query->update(['status' => 'running']);

        try {
            $discovery = new BacklinkDiscovery();

            // Extract domain from keyword field (now contains competitor domain)
            $competitorDomain = $this->query->keyword;

            // Find backlinks to the competitor domain
            $backlinks = $discovery->findBacklinks($competitorDomain, $this->query->result_cap ?? 25);

            $emailExtractor = new EmailExtractor();
            $resultsCount = 0;

            foreach ($backlinks as $backlink) {
                try {
                    // Try to extract email from the backlink page
                    $emails = $emailExtractor->extract($backlink['url']);

                    $email = $emails->first();

                    DiscoveryResult::create([
                        'discovery_query_id' => $this->query->id,
                        'site_url' => $backlink['url'],
                        'page_title' => $backlink['title'] ?? null,
                        'contact_email' => $email ?? null,
                        'extraction_status' => $email ? 'found' : 'not_found',
                    ]);

                    $resultsCount++;
                } catch (\Exception $e) {
                    // Log but continue with other results
                    \Illuminate\Support\Facades\Log::debug('Failed to process backlink', [
                        'url' => $backlink['url'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->query->update([
                'status' => 'completed',
                'results_found' => $resultsCount,
            ]);
        } catch (\Exception $e) {
            $this->query->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
