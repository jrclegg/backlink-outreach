<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ProjectList extends Component
{
    public bool $showForm = false;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|url')]
    public string $website_url = '';

    #[Validate('nullable|string')]
    public string $notes = '';

    public function toggleForm()
    {
        $this->showForm = !$this->showForm;
        if ($this->showForm) {
            $this->reset('name', 'website_url', 'notes');
        }
    }

    public function createProject()
    {
        $this->validate();

        $org = Auth::user()->currentOrganization;

        Project::create([
            'organization_id' => $org->id,
            'name' => $this->name,
            'website_url' => $this->website_url,
            'notes' => $this->notes ?: null,
        ]);

        $this->showForm = false;
        $this->reset('name', 'website_url', 'notes');
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $projects = $org ? $org->projects : collect();

        return view('livewire.projects.project-list', [
            'projects' => $projects,
        ])->layout('components.layouts.app', ['title' => 'Projects']);
    }
}
