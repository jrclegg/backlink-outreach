<?php

namespace App\Livewire\Discovery;

use App\Models\DiscoveryQuery;
use App\Models\Prospect;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DiscoveryResultsList extends Component
{
    public DiscoveryQuery $query;
    public array $selected = [];
    public bool $selectAll = false;

    public function mount(DiscoveryQuery $query)
    {
        $this->query = $query;
    }

    public function toggleSelectAll()
    {
        $this->selectAll = !$this->selectAll;
        if ($this->selectAll) {
            $this->selected = $this->query->results()->pluck('id')->map(fn($id) => (string)$id)->toArray();
        } else {
            $this->selected = [];
        }
    }

    public function addToProspects()
    {
        if (empty($this->selected)) {
            return;
        }

        $org = Auth::user()->currentOrganization;
        $project = $org->projects()->first();

        if (!$project) {
            $project = $org->projects()->create([
                'name' => 'Default Project',
                'website_url' => 'https://example.com',
            ]);
        }

        $results = $this->query->results()->whereIn('id', $this->selected)->get();

        foreach ($results as $result) {
            // Check if prospect already exists
            $existing = Prospect::where('project_id', $project->id)
                ->where('contact_email', $result->contact_email ?? '')
                ->exists();

            if (!$existing && $result->contact_email) {
                Prospect::create([
                    'project_id' => $project->id,
                    'site_url' => $result->site_url,
                    'contact_email' => $result->contact_email,
                    'source' => 'discovery',
                    'discovery_query_id' => $this->query->id,
                ]);
            }
        }

        $this->selected = [];
        $this->selectAll = false;
        session()->flash('message', 'Added to prospects!');
    }

    public function render()
    {
        return view('livewire.discovery.discovery-results-list', [
            'results' => $this->query->results()->paginate(25),
        ])->layout('components.layouts.app', ['title' => 'Discovery Results']);
    }
}
