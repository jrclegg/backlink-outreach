<?php

namespace App\Livewire\Inboxes;

use App\Jobs\PollInboxForReplies;
use App\Models\OutreachMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class InboxList extends Component
{
    public function sync(int $id): void
    {
        $inbox = Auth::user()->currentOrganization->emailInboxes()->findOrFail($id);
        PollInboxForReplies::dispatch($inbox);
        session()->flash('sync', 'Inbox sync queued.');
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;
        $inboxes = $org ? $org->emailInboxes()->get() : collect();

        $importedCounts = OutreachMessage::whereIn('email_inbox_id', $inboxes->pluck('id'))
            ->where('direction', 'inbound')->selectRaw('email_inbox_id, count(*) as total')
            ->groupBy('email_inbox_id')->pluck('total', 'email_inbox_id');

        return view('livewire.inboxes.inbox-list', [
            'importedCounts' => $importedCounts,
            'inboxes' => $inboxes,
        ])->layout('components.layouts.app', ['title' => 'Email Inboxes']);
    }
}
