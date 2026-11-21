<?php

namespace App\Livewire\Outreach;

use App\Models\LeadCampaign;
use App\Services\Outreach\CampaignGoals;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

// Every AI outreach campaign across your projects and clients.
class CampaignIndex extends Component
{
    public function render()
    {
        $today = now('Europe/London')->startOfDay();
        $campaigns = LeadCampaign::whereHas('project', fn ($q) => $q->where('organization_id', Auth::user()->current_organization_id))
            ->with('project', 'inbox')
            ->withCount([
                'prospects as waiting_count' => fn ($q) => $q->whereIn('status', ['passed', 'needs_review'])->whereHas('message', fn ($m) => $m->where('status', 'draft')),
                'prospects as sent_today_count' => fn ($q) => $q->whereHas('message', fn ($m) => $m->where('sent_at', '>=', $today)),
                'prospects as sent_count' => fn ($q) => $q->whereHas('message', fn ($m) => $m->where('status', 'sent')),
            ])->get()
            ->sortBy(fn ($c) => [$c->project->client_name ? 1 : 0, $c->project->name, $c->name])->values();

        return view('livewire.outreach.campaign-index', ['campaigns' => $campaigns, 'goals' => CampaignGoals::labels()])
            ->layout('components.layouts.app', ['title' => 'AI campaigns']);
    }
}
