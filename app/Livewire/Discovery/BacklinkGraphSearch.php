<?php

namespace App\Livewire\Discovery;

use App\Models\BacklinkGraphLink;
use App\Models\Prospect;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\WithPagination;

class BacklinkGraphSearch extends Component
{
    use WithPagination;

    #[Validate('required|string|min:3')]
    public string $competitorDomain = '';

    public array $selectedLinks = [];
    public int $projectId = 0;
    public bool $showResults = false;

    public function search()
    {
        $this->validate();
        $this->resetPage();
        $this->showResults = true;
    }

    public function addAsProspect(int $linkId)
    {
        if (!$this->projectId) {
            $this->addError('projectId', 'Please select a project first');
            return;
        }

        try {
            $link = BacklinkGraphLink::find($linkId);
            if (!$link) {
                $this->addError('link', 'Backlink not found');
                return;
            }

            $org = Auth::user()->currentOrganization;
            $project = $org->projects()->find($this->projectId);

            if (!$project) {
                $this->addError('projectId', 'Invalid project');
                return;
            }

            // Create or update prospect from backlink
            Prospect::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'site_url' => $link->source_url,
                ],
                [
                    'contact_email' => '', // To be extracted later
                    'status' => 'new',
                    'source' => 'backlink_graph_search',
                ]
            );

            // Store the anchor text if available
            $recipient = $project->campaigns()
                ->latest()
                ->first()
                ?->recipients()
                ?->first();

            if ($recipient && $link->anchor_text) {
                $recipient->update(['anchor_text' => $link->anchor_text]);
            }

            session()->flash('success', 'Added prospect from backlink');
        } catch (\Exception $e) {
            $this->addError('link', 'Failed to add prospect: ' . $e->getMessage());
        }
    }

    public function bulkAddAsProspects()
    {
        if (!$this->projectId) {
            $this->addError('projectId', 'Please select a project first');
            return;
        }

        if (empty($this->selectedLinks)) {
            $this->addError('selectedLinks', 'Please select at least one backlink');
            return;
        }

        try {
            $org = Auth::user()->currentOrganization;
            $project = $org->projects()->find($this->projectId);

            if (!$project) {
                $this->addError('projectId', 'Invalid project');
                return;
            }

            $links = BacklinkGraphLink::whereIn('id', $this->selectedLinks)->get();
            $count = 0;

            foreach ($links as $link) {
                try {
                    Prospect::updateOrCreate(
                        [
                            'project_id' => $project->id,
                            'site_url' => $link->source_url,
                        ],
                        [
                            'contact_email' => '',
                            'status' => 'new',
                            'source' => 'backlink_graph_search',
                        ]
                    );
                    $count++;
                } catch (\Exception $e) {
                    // Log error and continue
                    \Illuminate\Support\Facades\Log::warning('Failed to add prospect from backlink', [
                        'link_id' => $link->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->selectedLinks = [];
            session()->flash('success', "Added $count prospects from backlinks");
        } catch (\Exception $e) {
            $this->addError('bulk', 'Failed to bulk add prospects: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $projects = $org ? $org->projects : collect();

        $backlinks = collect();
        if ($this->showResults && $this->competitorDomain) {
            $backlinks = BacklinkGraphLink::where('source_domain', 'LIKE', '%' . $this->competitorDomain . '%')
                ->orWhere('source_url', 'LIKE', '%' . $this->competitorDomain . '%')
                ->latest('discovered_at')
                ->paginate(20);
        }

        return view('livewire.discovery.backlink-graph-search', [
            'projects' => $projects,
            'backlinks' => $backlinks,
        ])->layout('components.layouts.app', ['title' => 'Backlink Graph Search']);
    }
}
