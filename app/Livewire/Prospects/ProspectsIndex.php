<?php

namespace App\Livewire\Prospects;

use App\Models\Project;
use App\Models\Prospect;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ProspectsIndex extends Component
{
    use WithPagination;

    public $projectId;
    public $searchQuery = '';
    public $statusFilter = '';
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $selectedProspectId = null;

    public function mount()
    {
        $org = Auth::user()->currentOrganization;
        $project = $org->projects()->first();
        if ($project) {
            $this->projectId = $project->id;
        }
    }

    public function updatingSearchQuery()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function sortBy($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function toggleProspectStatus(Prospect $prospect)
    {
        $statuses = ['new', 'contacted', 'responded', 'qualified', 'rejected'];
        $currentIndex = array_search($prospect->status, $statuses);
        $nextIndex = ($currentIndex + 1) % count($statuses);
        $prospect->update(['status' => $statuses[$nextIndex]]);
    }

    public function deleteProspect(Prospect $prospect)
    {
        if ($prospect->project_id == $this->projectId) {
            $prospect->delete();
            session()->flash('message', 'Prospect deleted.');
        }
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $project = $this->projectId ? Project::find($this->projectId) : $org->projects()->first();

        if (!$project || $project->organization_id !== $org->id) {
            abort(403);
        }

        $query = $project->prospects();

        if ($this->searchQuery) {
            $query->where(function ($q) {
                $q->where('site_url', 'like', "%{$this->searchQuery}%")
                  ->orWhere('contact_email', 'like', "%{$this->searchQuery}%")
                  ->orWhere('contact_name', 'like', "%{$this->searchQuery}%");
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $query->orderBy($this->sortBy, $this->sortDirection);

        $prospects = $query->paginate(25);

        return view('livewire.prospects.prospects-index', [
            'prospects' => $prospects,
            'project' => $project,
            'statuses' => ['new', 'contacted', 'responded', 'qualified', 'rejected'],
        ])->layout('components.layouts.app', ['title' => 'Prospects']);
    }
}
