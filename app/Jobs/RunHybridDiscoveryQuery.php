<?php

namespace App\Jobs;

use App\Models\DiscoveryQuery;
use App\Models\DiscoveryResult;
use App\Services\Discovery\HybridDiscovery;
use App\Services\Discovery\EmailExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunHybridDiscoveryQuery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(private DiscoveryQuery $query)
    {
    }

    public function handle(): void
    {
        $this->query->update(['status' => 'running']);

        try {
            $discovery = new HybridDiscovery();
            $competitorDomain = $this->query->keyword;
            $maxResults = $this->query->result_cap ?? 25;

            Log::info('Starting discovery', [
                'domain' => $competitorDomain,
                'max_results' => $maxResults,
            ]);

            // Find prospects using hybrid search
            $prospects = $discovery->findProspects($competitorDomain, $maxResults);

            if ($prospects->isEmpty()) {
                Log::warning('No prospects found', ['domain' => $competitorDomain]);
                $this->query->update([
                    'status' => 'completed',
                    'results_found' => 0,
                    'error' => 'No results found. Try a different keyword or domain.',
                ]);
                return;
            }

            $emailExtractor = new EmailExtractor();
            $resultsCount = 0;

            foreach ($prospects as $prospect) {
                try {
                    // Validate URL
                    if (!filter_var($prospect['url'], FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    // Check for existing result
                    $existing = DiscoveryResult::where('discovery_query_id', $this->query->id)
                        ->where('site_url', $prospect['url'])
                        ->exists();

                    if ($existing) {
                        continue;
                    }

                    // Extract email from the prospect's website
                    $email = null;
                    $extraction_status = 'not_found';

                    try {
                        $emails = $emailExtractor->extract($prospect['url']);
                        if ($emails && $emails->isNotEmpty()) {
                            $email = $emails->first();
                            $extraction_status = 'found';
                        }
                    } catch (\Exception $e) {
                        Log::debug('Email extraction failed', [
                            'url' => $prospect['url'],
                            'error' => $e->getMessage(),
                        ]);
                    }

                    DiscoveryResult::create([
                        'discovery_query_id' => $this->query->id,
                        'site_url' => $prospect['url'],
                        'page_title' => $prospect['title'] ?? null,
                        'contact_email' => $email,
                        'extraction_status' => $extraction_status,
                    ]);

                    $resultsCount++;

                    // Respect rate limits
                    usleep(300000);
                } catch (\Exception $e) {
                    Log::debug('Failed to process prospect', [
                        'url' => $prospect['url'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->query->update([
                'status' => 'completed',
                'results_found' => $resultsCount,
            ]);

            Log::info('Discovery completed', [
                'results' => $resultsCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Discovery failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->query->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
