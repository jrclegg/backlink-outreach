<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Models\LeadCampaign;
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
        $recent = OutreachMessage::whereHas('conversation', fn ($q) => $q->whereIn('project_id', $ids))->where('direction', 'inbound')->with('conversation.project')->latest('received_at')->limit(6)->get();
        $week = now('Europe/London')->subDays(7);
        $campaigns = LeadCampaign::whereIn('project_id', $ids)->with('project')
            ->withCount(['prospects as waiting_count' => fn ($q) => $q->whereIn('status', ['passed', 'needs_review'])->whereHas('message', fn ($m) => $m->where('status', 'draft'))])
            ->get()->sortByDesc(fn ($c) => [$c->enabled, $c->waiting_count])->values();
        $sentWeek = OutreachMessage::whereHas('conversation', fn ($q) => $q->whereIn('project_id', $ids))->where('status', 'sent')->where('sent_at', '>=', $week)->count();
        $repliesWeek = OutreachMessage::whereHas('conversation', fn ($q) => $q->whereIn('project_id', $ids))->where('direction', 'inbound')->where('received_at', '>=', $week)->count();

        return view('livewire.dashboard', compact('cards', 'sent', 'unread', 'opportunities', 'placed', 'recent', 'campaigns', 'sentWeek', 'repliesWeek'))->layout('components.layouts.app', ['title' => 'Home']);
    }
}
