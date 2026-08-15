<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProjectList extends Component
{
    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $projects = $org ? $org->projects : collect();

        return view('livewire.projects.project-list', [
            'projects' => $projects,
        ])->layout('components.layouts.app', ['title' => 'Projects']);
    }
}
