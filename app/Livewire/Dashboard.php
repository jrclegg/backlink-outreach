<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\Opportunity;
use App\Models\OutreachMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();
        $org = $user->currentOrganization;
        if (! $org && ($org = $user->organizations()->first())) {
            $user->update(['current_organization_id' => $org->id]);
        }
        $projects = $org?->projects ?? collect();
        $ids = $projects->pluck('id');
        $sent = OutreachMessage::whereHas('conversation', fn ($q) => $q->whereIn('project_id', $ids))->where('status', 'sent')->count();
        $unread = Conversation::whereIn('project_id', $ids)->where('status', 'open')->whereNull('read_at')->whereHas('messages', fn ($q) => $q->where('direction', 'inbound'))->count();
        $opportunities = Opportunity::whereHas('targetPage', fn ($q) => $q->whereIn('project_id', $ids))->where('status', 'new')->count();
        $placed = Opportunity::whereHas('targetPage', fn ($q) => $q->whereIn('project_id', $ids))->where('link_status', 'found')->count();
        $cards = $projects->map(function ($project) {
            return ['project' => $project,
                'unread' => Conversation::where('project_id', $project->id)->whereNull('read_at')->where('status', 'open')->whereHas('messages', fn ($q) => $q->where('direction', 'inbound'))->count(),
                'opportunities' => Opportunity::whereHas('targetPage', fn ($q) => $q->where('project_id', $project->id))->where('status', 'new')->count(),
                'drafts' => OutreachMessage::whereHas('conversation', fn ($q) => $q->where('project_id', $project->id))->where('status', 'draft')->count(),
                'accounts' => $project->emailInboxes()->count(),
            ];
        });
        $recent = OutreachMessage::whereHas('conversation', fn ($q) => $q->whereIn('project_id', $ids))->where('direction', 'inbound')->with('conversation.project')->latest('received_at')->limit(5)->get();

        return view('livewire.dashboard', compact('cards', 'sent', 'unread', 'opportunities', 'placed', 'recent'))->layout('components.layouts.app',['title' => 'Your SEO workspace']);
    }
}
