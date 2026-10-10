<?php

namespace App\Livewire\Workspace;

use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class UnassignedMail extends Component
{
    use WithPagination;

    public array $projectsForThread = [];

    public function assign(int $id): void
    {
        $org = Auth::user()->currentOrganization;
        $thread = Conversation::whereNull('project_id')->whereHas('emailInbox', fn ($q) => $q->where('organization_id', $org->id))->findOrFail($id);
        $project = $org->projects()->findOrFail($this->projectsForThread[$id] ?? null);
        $thread->update(['project_id' => $project->id]);
        $project->emailInboxes()->syncWithoutDetaching([$thread->email_inbox_id]);
    }

    public function render()
    {
        $org = Auth::user()->currentOrganization;

        return view('livewire.workspace.unassigned-mail', [
            'threads' => Conversation::whereNull('project_id')->whereHas('emailInbox', fn ($q) => $q->where('organization_id', $org->id))->with(['emailInbox', 'messages' => fn ($q) => $q->orderBy('id')])->latest('last_message_at')->paginate(15),
            'projects' => $org->projects,
        ])->layout('components.layouts.app', ['title' => 'Unassigned mail']);
    }
}
