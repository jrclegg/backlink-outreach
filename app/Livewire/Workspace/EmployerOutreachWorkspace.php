<?php

namespace App\Livewire\Workspace;

use App\Jobs\DraftEmployerEmail;
use App\Jobs\PrepareEmployerBatch;
use App\Models\EmployerBatchItem;
use App\Models\EmployerCampaign;
use App\Models\Project;
use App\Services\Outreach\AcademicEmployerLeads;
use App\Services\Outreach\EmployerOutreach;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EmployerOutreachWorkspace extends Component
{
    #[Locked]
    public int $projectId;

    public string $audience = 'Academic employers hiring ecologists, prioritising recent adverts on paid job boards.';

    public string $offer = '';

    public string $postingUrl = '';

    public ?int $inboxId = null;

    public int $weeklyLimit = 10;

    public string $cadence = 'weekly';

    public ?string $startsOn = null;

    public bool $enabled = false;

    public bool $autoSend = false;

    public bool $discover = true;

    public int $weekday = 1;

    public string $sendTime = '09:00';

    public string $notice = '';

    #[Locked]
    public ?int $actionItemId = null;

    public string $employer = '';

    public string $email = '';

    public string $sourceUrl = '';

    public string $jobAdvertUrl = '';

    public string $relevance = '';

    public array $subjects = [];

    public array $bodies = [];

    private function project(): Project
    {
        return Auth::user()->currentOrganization->projects()->findOrFail($this->projectId);
    }

    private function campaign(): EmployerCampaign
    {
        return EmployerCampaign::where('project_id', $this->project()->id)->firstOrFail();
    }

    public function mount(Project $project): void
    {
        $this->projectId = $project->id;
        $project = $this->project();
        $c = EmployerCampaign::firstOrCreate(['project_id' => $project->id], ['audience' => $this->audience, 'offer' => 'A specialist job board for ecology researchers. Describe your paid listing features and confirmed price here.', 'posting_url' => $project->website_url, 'email_inbox_id' => $project->emailInboxes()->where('is_active', true)->value('email_inboxes.id')]);
        $this->audience = $c->audience;
        $this->offer = $c->offer;
        $this->postingUrl = $c->posting_url;
        $this->inboxId = $c->email_inbox_id;
        $this->weeklyLimit = $c->weekly_limit;
        $this->cadence = $c->cadence;
        $this->startsOn = $c->starts_on;
        $this->enabled = $c->enabled;
        $this->autoSend = $c->auto_send;
        $this->discover = $c->discover;
        $this->weekday = $c->weekday;
        $this->sendTime = $c->send_time;
    }

    public function saveSettings(): void
    {
        $this->validate(['cadence' => 'required|in:daily,weekly', 'startsOn' => 'nullable|date_format:Y-m-d']);
        $this->validate(['audience' => 'required|string|max:5000', 'offer' => 'required|string|max:5000', 'postingUrl' => 'required|url:http,https|max:2048', 'inboxId' => 'required|integer', 'weeklyLimit' => 'required|integer|min:1|max:50', 'weekday' => 'required|integer|min:0|max:6', 'sendTime' => 'required|date_format:H:i']);
        $account = $this->project()->emailInboxes()->where('is_active', true)->find($this->inboxId);
        if (! $account) {
            throw ValidationException::withMessages(['inboxId' => 'Choose an active email account assigned to this project.']);
        }
        abort_unless($account->organization_id === $this->project()->organization_id, 403);
        $this->campaign()->update(['audience' => $this->audience, 'offer' => $this->offer, 'posting_url' => $this->postingUrl, 'email_inbox_id' => $account->id, 'weekly_limit' => $this->weeklyLimit, 'cadence' => $this->cadence, 'starts_on' => $this->startsOn, 'enabled' => $this->enabled, 'discover' => $this->discover, 'weekday' => $this->weekday, 'send_time' => $this->sendTime, 'auto_send' => $this->autoSend]);
        $this->notice = $this->autoSend ? 'Settings saved. Automatic outreach sends only emails that pass live evidence and quality checks.' : 'Settings saved. Emails wait for your approval.';
    }

    public function prepare(): void
    {
        $this->saveSettings();
        $batch = app(EmployerOutreach::class)->start($this->campaign());
        $this->notice = 'Current batch: '.$batch->status.'. It appears below as research and drafts finish.';
    }

    public function retryBatch(int $id): void
    {
        $batch = $this->campaign()->batches()->findOrFail($id);
        if (! in_array($batch->status, ['failed', 'needs_contacts'])) {
            return;
        }
        $batch->update(['status' => 'queued', 'error' => null]);
        PrepareEmployerBatch::dispatch($batch);
    }

    public function addContact(): void
    {
        $this->email = strtolower(trim($this->email));
        $this->validate(['employer' => 'required|string|max:255', 'email' => 'required|email|max:255', 'sourceUrl' => 'required|url:http,https|max:2048', 'jobAdvertUrl' => 'required|url:http,https|max:2048', 'relevance' => 'required|string|max:5000']);
        $this->campaign()->contacts()->firstOrCreate(['email' => $this->email], ['employer' => $this->employer, 'source_url' => $this->sourceUrl, 'job_advert_url' => $this->jobAdvertUrl, 'relevance' => $this->relevance, 'status' => 'approved']);
        $this->reset('employer', 'email', 'sourceUrl', 'jobAdvertUrl', 'relevance');
        $this->notice = 'Contact saved to the employer pipeline.';
    }

    public function importLeads(): void
    {
        try {
            $n = app(AcademicEmployerLeads::class)->import($this->campaign());
        } catch (\RuntimeException $e) {
            $this->addError('leads', $e->getMessage());

            return;
        }
        $this->notice = $n.' new scraped vacancies added for employer research. These are hiring signals, not confirmed purchases.';
    }

    public function exclude(int $id): void
    {
        $this->campaign()->contacts()->findOrFail($id)->update(['status' => 'excluded']);
    }

    private function item(int $id): EmployerBatchItem
    {
        return EmployerBatchItem::whereHas('batch', fn ($q) => $q->where('employer_campaign_id', $this->campaign()->id))->findOrFail($id);
    }

    public function saveDraft(int $id): void
    {
        $item = $this->item($id);
        $m = $item->message;
        abort_unless($m && $m->status === 'draft', 409);
        $this->validate(["subjects.$id" => 'required|string|max:255|not_regex:/[\r\n]/', "bodies.$id" => 'required|string|min:10|max:10000']);
        $m->update(['subject' => $this->subjects[$id], 'body' => $this->bodies[$id]]);
        $this->notice = 'Draft saved.';
    }

    public function approveEmail(int $id): void
    {
        $item = $this->item($id);
        $this->actionItemId = $item->id;
        if (! $item->message || $item->message->status !== 'draft') {
            $this->notice = 'This email is no longer a draft awaiting approval.';

            return;
        }
        $this->saveDraft($id);
        $this->notice = app(EmployerOutreach::class)->approve($item)
            ? 'Reviewed email queued for sending.'
            : 'This contact needs a job advert link or is no longer eligible for outreach. The email was not queued.';
    }

    public function approveBatch(int $id): void
    {
        $batch = $this->campaign()->batches()->findOrFail($id);
        if ($batch->items()->whereIn('status', ['queued', 'drafting'])->exists()) {
            $this->notice = 'Wait for all drafts to finish before approving this batch.';

            return;
        }
        $count = 0;
        foreach ($batch->items()->with('message')->get() as $item) {
            if (! $item->message || $item->message->status !== 'draft') {
                continue;
            }
            if (isset($this->subjects[$item->id],$this->bodies[$item->id])) {
                $this->saveDraft($item->id);
            }
            if (app(EmployerOutreach::class)->approve($item)) {
                $count++;
            }
        }
        $this->notice = $count.' reviewed emails queued for sending.';
    }

    public function retryDraft(int $id): void
    {
        $item = $this->item($id);
        if ($item->status !== 'failed' || $item->outreach_message_id) {
            return;
        }
        $item->update(['status' => 'queued', 'error' => null]);
        DraftEmployerEmail::dispatch($item);
    }

    public function render()
    {
        $campaign = $this->campaign();
        $batches = $campaign->batches()->with('items.contact', 'items.message')->latest()->limit(8)->get();
        foreach ($batches as $batch) {
            foreach ($batch->items as $item) {
                if ($item->message && ! isset($this->subjects[$item->id])) {
                    $this->subjects[$item->id] = $item->message->subject;
                    $this->bodies[$item->id] = $item->message->body;
                }
            }
        }

        return view('livewire.workspace.employer-outreach', ['project' => $this->project(), 'campaign' => $campaign, 'accounts' => $this->project()->emailInboxes()->where('is_active', true)->get(), 'contacts' => $campaign->contacts()->latest()->limit(100)->get(), 'batches' => $batches, 'leads' => $campaign->leads()->orderByDesc('priority')->latest()->limit(20)->get()])->layout('components.layouts.app', ['title' => 'Employer outreach']);
    }
}
