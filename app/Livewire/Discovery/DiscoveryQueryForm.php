<?php

namespace App\Livewire\Discovery;

use App\Jobs\RunHybridDiscoveryQuery;
use App\Models\DiscoveryQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Validate;
use Livewire\Component;

class DiscoveryQueryForm extends Component
{
    #[Validate('required|string|max:255')]
    public string $competitor_domain = '';

    #[Validate('nullable|integer|min:10|max:100')]
    public ?int $result_cap = 25;

    public bool $searching = false;
    public ?int $activeQueryId = null;
    public string $message = '';

    public function search()
    {
        $this->validate();
        $this->searching = true;
        $this->message = '';

        try {
            $org = Auth::user()->currentOrganization;

            // Ensure we have a default project
            $project = $org->projects()->first();
            if (!$project) {
                $project = $org->projects()->create([
                    'name' => 'Default Project',
                    'website_url' => 'https://example.com',
                ]);
            }

            // Create the discovery query
            $query = DiscoveryQuery::create([
                'project_id' => $project->id,
                'keyword' => $this->competitor_domain,
                'footprint_pattern' => 'backlink_discovery',
                'status' => 'running',
                'results_found' => 0,
            ]);

            $this->activeQueryId = $query->id;

            // Run discovery synchronously for better UX
            $this->message = 'Searching for prospects in the ' . $this->competitor_domain . ' niche...';

            // Use sync queue for immediate execution
            config(['queue.default' => 'sync']);
            RunHybridDiscoveryQuery::dispatch($query);

            // Refresh query to get results
            $query->refresh();

            $this->searching = false;
            if ($query->results_found > 0) {
                $this->message = 'Found ' . $query->results_found . ' prospects! View the results to add them to your outreach list.';
                return redirect()->route('discover.results', $query);
            } else {
                $this->message = 'No prospects found. Try a different domain or keyword.';
            }
        } catch (\Exception $e) {
            $this->searching = false;
            $this->message = 'Error: ' . $e->getMessage();
            \Illuminate\Support\Facades\Log::error('Discovery search error', ['error' => $e->getMessage()]);
        }
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $projects = $org ? $org->projects : collect();

        // Get all queries across all projects in this org
        $queries = $org ? DiscoveryQuery::whereHas('project', function ($q) use ($org) {
            $q->where('organization_id', $org->id);
        })->latest()->take(10)->get() : collect();

        return view('livewire.discovery.discovery-query-form', [
            'projects' => $projects,
            'queries' => $queries,
        ])->layout('components.layouts.app', ['title' => 'Prospect Discovery']);
    }
}
