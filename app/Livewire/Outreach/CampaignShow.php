<?php

namespace App\Livewire\Outreach;

use App\Jobs\SendReviewedMessage;
use App\Models\LeadCampaign;
use App\Models\LeadProspect;
use App\Models\OutreachMessage;
use App\Services\Outreach\Assistant;
use App\Services\Outreach\CampaignGoals;
use App\Services\Outreach\LeadOutreach;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

// One campaign: switch it off / approval / automatic, run it now, and approve, edit or skip its drafts.
class CampaignShow extends Component
{
    #[Locked]
    public int $campaignId;

    #[Url]
    public string $filter = 'waiting';

    public array $edits = [];

    public string $notice = '';

    public function mount(LeadCampaign $campaign): void
    {
        abort_unless($campaign->project->organization_id === Auth::user()->current_organization_id, 404);
        $this->campaignId = $campaign->id;
    }

    private function campaign(): LeadCampaign
    {
        return LeadCampaign::whereHas('project', fn ($q) => $q->where('organization_id', Auth::user()->current_organization_id))->findOrFail($this->campaignId);
    }

    private function prospect(int $id): LeadProspect
    {
        return $this->campaign()->prospects()->with('message')->findOrFail($id);
    }

    public function setMode(string $mode): void
    {
        abort_unless(in_array($mode, ['off', 'approval', 'automatic']), 422);
        $campaign = $this->campaign();
        if ($mode !== 'off' && ! $campaign->project->emailInboxes()->whereKey($campaign->email_inbox_id)->where('is_active', true)->exists()) {
            $this->addError('mode', 'Choose an active sending account for this campaign first (Edit brief).');

            return;
        }
        $campaign->update(['enabled' => $mode !== 'off', 'auto_send' => $mode === 'automatic']);
        $this->notice = match ($mode) {
            'off' => 'Campaign switched off. Nothing new will be researched or sent.',
            'approval' => 'Approval mode: drafts are prepared each day for you to approve. Nothing is sent without you.',
            'automatic' => 'Automatic mode: drafts that pass every quality check are sent, up to the daily limit.',
        };
    }

    public function runNow(): void
    {
        $campaign = $this->campaign();
        if (! $campaign->enabled) {
            $this->addError('run', 'Switch the campaign on (approval mode is safest) before running it.');

            return;
        }
        if (! app(Assistant::class)->configured($campaign->project->organization)) {
            $this->addError('run', 'AI is not connected. Check AI settings.');

            return;
        }
        $this->notice = app(LeadOutreach::class)->runNow($campaign)
            ? 'Research started. Drafts appear here as they are written, usually within a few minutes.'
            : 'Today\'s research is still running. Drafts will appear as they are written.';
    }

    public function approve(int $id): void
    {
        $p = $this->prospect($id);
        $message = $p->message;
        if (! $message || $message->status !== 'draft') {
            $this->addError('approve', 'This draft has already been sent or approved.');

            return;
        }
        if (! app(LeadOutreach::class)->eligible($p)) {
            $this->addError('approve', $p->organization.' has already been contacted, excluded or opted out.');

            return;
        }
        $subject = trim($this->edits[$id]['subject'] ?? $message->subject);
        $body = trim($this->edits[$id]['body'] ?? $message->body);
        if ($subject === '' || $body === '') {
            $this->addError('approve', 'The subject and message cannot be empty.');

            return;
        }
        DB::transaction(function () use ($message, $subject, $body) {
            if (! OutreachMessage::whereKey($message->id)->where('status', 'draft')->update(['subject' => mb_substr($subject, 0, 255), 'body' => $body, 'status' => 'queued', 'approved_at' => now()])) {
                return;
            }
            SendReviewedMessage::dispatch($message->fresh())->afterCommit();
        });
        unset($this->edits[$id]);
        $this->notice = 'Approved. Sending to '.$p->email.'.';
    }

    public function skip(int $id): void
    {
        $p = $this->prospect($id);
        if ($p->message && $p->message->status !== 'draft') {
            return;
        }
        $p->update(['status' => 'skipped', 'reason' => 'Skipped by you.']);
        $p->message?->conversation?->update(['status' => 'archived']);
        unset($this->edits[$id]);
        $this->notice = $p->organization.' skipped.';
    }

    public function render()
    {
        $campaign = $this->campaign()->load('project', 'inbox');
        $today = now('Europe/London')->startOfDay();
        $messages = fn () => OutreachMessage::whereIn('id', $campaign->prospects()->whereNotNull('outreach_message_id')->select('outreach_message_id'));
        $waiting = fn ($q) => $q->whereIn('status', ['passed', 'needs_review'])->whereHas('message', fn ($m) => $m->where('status', 'draft'));
        $prospects = $campaign->prospects()->with('message.conversation')
            ->when($this->filter === 'waiting', $waiting)
            ->when($this->filter === 'sent', fn ($q) => $q->whereHas('message', fn ($m) => $m->whereIn('status', ['queued', 'sending', 'sent', 'failed'])))
            ->when($this->filter === 'replied', fn ($q) => $q->whereHas('message.conversation.messages', fn ($m) => $m->where('direction', 'inbound')))
            ->when($this->filter === 'skipped', fn ($q) => $q->whereIn('status', ['skipped', 'excluded']))
            ->latest('id')->limit(50)->get();
        foreach ($prospects as $p) {
            if ($p->message?->status === 'draft' && ! isset($this->edits[$p->id])) {
                $this->edits[$p->id] = ['subject' => $p->message->subject, 'body' => $p->message->body];
            }
        }

        return view('livewire.outreach.campaign-show', [
            'campaign' => $campaign,
            'goal' => CampaignGoals::get($campaign->goal)['label'],
            'prospects' => $prospects,
            'batch' => $campaign->batches()->latest('day')->first(),
            'stats' => [
                'Sent today' => $messages()->where('sent_at', '>=', $today)->count(),
                'Waiting for you' => $campaign->prospects()->where($waiting)->count(),
                'Sent in total' => $messages()->where('status', 'sent')->count(),
                'Replies' => $campaign->prospects()->whereHas('message.conversation.messages', fn ($m) => $m->where('direction', 'inbound'))->count(),
            ],
        ])->layout('components.layouts.app', ['title' => $campaign->name]);
    }
}
