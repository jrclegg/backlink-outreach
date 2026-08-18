<?php

namespace App\Livewire\Prospects;

use App\Models\Project;
use App\Models\Prospect;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProspectCreate extends Component
{
    public $projectId;
    public $siteUrl = '';
    public $contactName = '';
    public $contactEmail = '';
    public $niche = '';
    public $notes = '';

    public function mount()
    {
        $org = Auth::user()->currentOrganization;
        $project = $org->projects()->first();
        if ($project) {
            $this->projectId = $project->id;
        }
    }

    public function save()
    {
        $this->validate([
            'siteUrl' => 'required|url',
            'contactEmail' => 'required|email',
            'contactName' => 'nullable|string|max:255',
            'niche' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $org = Auth::user()->currentOrganization;
        $project = $this->projectId ? Project::find($this->projectId) : $org->projects()->first();

        if (!$project || $project->organization_id !== $org->id) {
            $this->addError('projectId', 'Invalid project');
            return;
        }

        // Check if prospect already exists
        $existing = Prospect::where('project_id', $project->id)
            ->where('contact_email', $this->contactEmail)
            ->exists();

        if ($existing) {
            $this->addError('contactEmail', 'This email already exists in your prospects');
            return;
        }

        Prospect::create([
            'project_id' => $project->id,
            'site_url' => $this->siteUrl,
            'contact_email' => $this->contactEmail,
            'contact_name' => $this->contactName,
            'niche' => $this->niche,
            'notes' => $this->notes,
            'status' => 'new',
            'source' => 'manual',
        ]);

        session()->flash('message', 'Prospect added successfully!');
        return redirect()->route('prospects.index');
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $projects = $org ? $org->projects : collect();

        return view('livewire.prospects.prospect-create', [
            'projects' => $projects,
        ])->layout('components.layouts.app', ['title' => 'Add Prospect']);
    }
}
