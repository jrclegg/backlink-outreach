<?php

namespace App\Livewire\Campaigns;

use App\Models\Campaign;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CampaignShow extends Component
{
    public Campaign $campaign;

    public function mount(Campaign $campaign)
    {
        $org = Auth::user()->currentOrganization;
        if ($campaign->project->organization_id !== $org->id) {
            abort(403);
        }
        $this->campaign = $campaign;
    }

    public function render()
    {
        $stats = [
            'total' => $this->campaign->recipients()->count(),
            'sent' => $this->campaign->recipients()->where('status', 'sent')->count(),
            'opened' => $this->campaign->recipients()->whereNotNull('opened_at')->count(),
            'clicked' => $this->campaign->recipients()->whereNotNull('first_clicked_at')->count(),
            'replied' => $this->campaign->recipients()->whereNotNull('replied_at')->count(),
        ];

        $recipients = $this->campaign->recipients()->paginate(20);

        return view('livewire.campaigns.campaign-show', [
            'campaign' => $this->campaign,
            'stats' => $stats,
            'recipients' => $recipients,
        ])->layout('components.layouts.app', ['title' => $this->campaign->name]);
    }
}
