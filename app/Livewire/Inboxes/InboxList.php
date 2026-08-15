<?php

namespace App\Livewire\Inboxes;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class InboxList extends Component
{
    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $inboxes = $org ? $org->emailInboxes : collect();

        return view('livewire.inboxes.inbox-list', [
            'inboxes' => $inboxes,
        ])->layout('components.layouts.app', ['title' => 'Email Inboxes']);
    }
}
