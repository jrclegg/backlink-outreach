<?php

namespace App\Livewire;

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $org = Auth::user()->currentOrganization;

        if (!$org) {
            // If user has no current org, try to set one
            $org = Auth::user()->organizations()->first();
            if ($org) {
                Auth::user()->update(['current_organization_id' => $org->id]);
            }
        }

        return view('livewire.dashboard', [
            'organization' => $org,
            'projects' => $org ? $org->projects : [],
        ])->layout('components.layouts.app', ['title' => 'Dashboard']);
    }
}
