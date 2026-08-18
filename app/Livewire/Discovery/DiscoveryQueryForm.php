<?php

namespace App\Livewire\Discovery;

use App\Services\Discovery\HybridDiscovery;
use App\Services\Discovery\EmailExtractor;
use App\Models\DiscoveryQuery;
use App\Models\DiscoveryResult;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Validate;
use Livewire\Component;

class DiscoveryQueryForm extends Component
{
    #[Validate('required|string|max:255')]
    public string $search_phrase = '';

    #[Validate('nullable|integer|min:10|max:100')]
    public ?int $result_cap = 25;

    public bool $searching = false;
    public array $results = [];
    public string $message = '';
    public ?int $activeQueryId = null;

    public function search()
    {
        $this->validate();
        $this->searching = true;
        $this->message = '';
        $this->results = [];

        try {
            $org = Auth::user()->currentOrganization;
            $project = $org->projects()->first();
            if (!$project) {
                $project = $org->projects()->create([
                    'name' => 'Default Project',
                    'website_url' => 'https://example.com',
                ]);
            }

            // Create the discovery query record
            $query = DiscoveryQuery::create([
                'project_id' => $project->id,
                'keyword' => $this->search_phrase,
                'footprint_pattern' => 'search',
                'status' => 'running',
                'results_found' => 0,
            ]);

            $this->activeQueryId = $query->id;

            // Perform the search
            $discovery = new HybridDiscovery();
            $prospects = $discovery->findProspects($this->search_phrase, $this->result_cap ?? 25);

            if ($prospects->isEmpty()) {
                $query->update(['status' => 'completed', 'results_found' => 0]);
                $this->searching = false;
                $this->message = 'No results found. Try a different search term.';
                return;
            }

            // Process results
            $emailExtractor = new EmailExtractor();
            $resultsCount = 0;

            foreach ($prospects as $prospect) {
                try {
                    if (!filter_var($prospect['url'], FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    // Check if already exists
                    $existing = DiscoveryResult::where('discovery_query_id', $query->id)
                        ->where('site_url', $prospect['url'])
                        ->exists();

                    if ($existing) {
                        continue;
                    }

                    // Try to extract email
                    $email = null;
                    try {
                        $emails = $emailExtractor->extract($prospect['url']);
                        if ($emails && $emails->isNotEmpty()) {
                            $email = $emails->first();
                        }
                    } catch (\Exception $e) {
                        Log::debug('Email extraction failed', ['url' => $prospect['url']]);
                    }

                    DiscoveryResult::create([
                        'discovery_query_id' => $query->id,
                        'site_url' => $prospect['url'],
                        'page_title' => $prospect['title'] ?? null,
                        'contact_email' => $email,
                        'extraction_status' => $email ? 'found' : 'not_found',
                    ]);

                    $this->results[] = [
                        'url' => $prospect['url'],
                        'title' => $prospect['title'] ?? 'No title',
                        'email' => $email ?? 'Not found',
                    ];

                    $resultsCount++;
                    usleep(300000);
                } catch (\Exception $e) {
                    Log::debug('Failed to process prospect', ['error' => $e->getMessage()]);
                }
            }

            $query->update(['status' => 'completed', 'results_found' => $resultsCount]);
            $this->searching = false;
            $this->message = $resultsCount > 0 ? "Found $resultsCount results" : 'No results found.';
        } catch (\Exception $e) {
            $this->searching = false;
            $this->message = 'Error: ' . $e->getMessage();
            Log::error('Discovery search error', ['error' => $e->getMessage()]);
        }
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $queries = $org ? DiscoveryQuery::whereHas('project', function ($q) use ($org) {
            $q->where('organization_id', $org->id);
        })->latest()->take(10)->get() : collect();

        return view('livewire.discovery.discovery-query-form', [
            'queries' => $queries,
            'results' => $this->results,
        ])->layout('components.layouts.app', ['title' => 'Prospect Discovery']);
    }
}
