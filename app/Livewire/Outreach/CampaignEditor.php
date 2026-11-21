<?php

namespace App\Livewire\Outreach;

use App\Models\LeadCampaign;
use App\Models\Project;
use App\Services\Outreach\CampaignBriefWriter;
use App\Services\Outreach\CampaignGoals;
use App\Services\Outreach\PublicPage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

// Create or edit a general AI outreach campaign: describe it in plain English, let AI write the brief, edit, save.
class CampaignEditor extends Component
{
    #[Locked]
    public ?int $campaignId = null;

    #[Url(as: 'project')]
    public ?int $projectId = null;

    public bool $newProject = false;

    public string $newProjectName = '';

    public string $newProjectUrl = '';

    public string $newClientName = '';

    public ?int $inboxId = null;

    public string $goal = 'sales';

    public string $description = '';

    public string $link = '';

    public bool $hasBrief = false;

    public array $questions = [];

    public string $name = '';

    public string $audience = '';

    public string $offer = '';

    public string $productUrl = '';

    public string $sender = '';

    public string $focuses = '';

    public string $fitRules = '';

    public string $emailRules = '';

    public string $excludedDomains = '';

    public string $examples = '';

    public int $minWords = 70;

    public int $maxWords = 130;

    public int $dailyLimit = 10;

    public string $sendTime = '10:00';

    public function mount(?LeadCampaign $campaign = null): void
    {
        if ($campaign?->exists) {
            abort_unless($campaign->project->organization_id === Auth::user()->current_organization_id, 404);
            $this->campaignId = $campaign->id;
            $this->projectId = $campaign->project_id;
            $this->inboxId = $campaign->email_inbox_id;
            $this->goal = $campaign->goal;
            $this->description = $campaign->brief ?? '';
            $this->fill([
                'name' => $campaign->name, 'audience' => $campaign->audience, 'offer' => $campaign->offer, 'productUrl' => $campaign->product_url,
                'sender' => $campaign->sender, 'focuses' => implode("\n", $campaign->focuses ?? []), 'fitRules' => $campaign->fit_rules ?? '',
                'emailRules' => $campaign->email_rules ?? '', 'excludedDomains' => implode("\n", $campaign->excluded_domains ?? []), 'examples' => $campaign->examples ?? '',
                'minWords' => $campaign->min_words, 'maxWords' => $campaign->max_words, 'dailyLimit' => $campaign->daily_limit, 'sendTime' => $campaign->send_time,
            ]);
            $this->hasBrief = true;

            return;
        }
        if ($this->projectId && ! $this->projects()->whereKey($this->projectId)->exists()) {
            $this->projectId = null;
        }
        $this->inboxId = $this->defaultInbox();
    }

    private function projects()
    {
        return Auth::user()->currentOrganization->projects();
    }

    private function inboxes()
    {
        return Auth::user()->currentOrganization->emailInboxes()->where('is_active', true);
    }

    private function defaultInbox(): ?int
    {
        $project = $this->projectId ? $this->projects()->find($this->projectId) : null;

        return $project?->emailInboxes()->where('is_active', true)->value('email_inboxes.id') ?? $this->inboxId;
    }

    public function updatedProjectId(): void
    {
        $this->newProject = $this->projectId === 0;
        if (! $this->newProject) {
            $this->inboxId = $this->defaultInbox();
        }
    }

    private function project(): Project
    {
        if ($this->newProject) {
            $this->validate(['newProjectName' => 'required|string|max:255', 'newProjectUrl' => 'required|url:http,https|max:2048', 'newClientName' => 'nullable|string|max:255']);
            $project = $this->projects()->create(['name' => $this->newProjectName, 'website_url' => $this->newProjectUrl, 'client_name' => trim($this->newClientName) ?: null]);
            $this->projectId = $project->id;
            $this->newProject = false;

            return $project;
        }
        $this->validate(['projectId' => 'required|integer|min:1'], ['projectId.*' => 'Choose a project or add a new one.']);

        return $this->projects()->findOrFail($this->projectId);
    }

    public function writeBrief(CampaignBriefWriter $writer): void
    {
        $this->validate(['description' => 'required|string|min:20|max:10000', 'goal' => 'required|in:'.implode(',', array_keys(CampaignGoals::ALL)), 'link' => 'nullable|url:http,https|max:2048']);
        $project = $this->project();
        try {
            $b = $writer->write($project, $this->description, $this->goal, $this->link ?: null);
        } catch (\Throwable $e) {
            $this->addError('description', $e instanceof \RuntimeException ? $e->getMessage() : 'The AI could not write the brief. Check AI settings and try again.');

            return;
        }
        $this->fill([
            'name' => $b['name'], 'goal' => $b['goal'], 'audience' => $b['audience'], 'offer' => $b['offer'], 'productUrl' => $b['product_url'], 'sender' => $b['sender'],
            'focuses' => implode("\n", $b['focuses']), 'fitRules' => $b['fit_rules'], 'emailRules' => $b['email_rules'],
            'excludedDomains' => implode("\n", $b['excluded_domains']), 'minWords' => $b['min_words'], 'maxWords' => $b['max_words'], 'questions' => $b['questions'],
        ]);
        $this->hasBrief = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255', 'goal' => 'required|in:'.implode(',', array_keys(CampaignGoals::ALL)),
            'audience' => 'required|string|max:5000', 'offer' => 'required|string|max:10000', 'productUrl' => 'required|url:http,https|max:2048',
            'sender' => 'required|string|max:2000', 'focuses' => 'nullable|string|max:5000', 'fitRules' => 'nullable|string|max:5000',
            'emailRules' => 'nullable|string|max:5000', 'excludedDomains' => 'nullable|string|max:5000', 'examples' => 'nullable|string|max:20000',
            'minWords' => 'required|integer|min:30|max:250', 'maxWords' => 'required|integer|min:40|max:300|gt:minWords',
            'dailyLimit' => 'required|integer|min:1|max:50', 'sendTime' => 'required|date_format:H:i', 'inboxId' => 'required|integer',
        ], ['inboxId.*' => 'Choose the email account to send from.']);
        $project = $this->project();
        $inbox = $this->inboxes()->findOrFail($this->inboxId);
        $lines = fn (string $s) => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $s))));
        $values = [
            'project_id' => $project->id, 'email_inbox_id' => $inbox->id, 'name' => $this->name, 'goal' => $this->goal, 'brief' => $this->description ?: null,
            'audience' => $this->audience, 'offer' => $this->offer, 'product_url' => $this->productUrl, 'sender' => $this->sender,
            'focuses' => $lines($this->focuses) ?: null, 'fit_rules' => $this->fitRules ?: null, 'email_rules' => $this->emailRules ?: null,
            'excluded_domains' => array_map(fn ($d) => strtolower(preg_replace('#^(https?://)?(www\.)?#i', '', rtrim($d, '/'))), $lines($this->excludedDomains)) ?: null,
            'examples' => $this->examples ?: null, 'min_words' => $this->minWords, 'max_words' => $this->maxWords,
            'daily_limit' => $this->dailyLimit, 'send_time' => $this->sendTime,
        ];
        $campaign = DB::transaction(function () use ($project, $inbox, $values) {
            // The sending account must be assigned to the project so replies land in its inbox.
            $project->emailInboxes()->syncWithoutDetaching([$inbox->id]);
            if ($this->campaignId) {
                $campaign = LeadCampaign::whereIn('project_id', $this->projects()->pluck('id'))->findOrFail($this->campaignId);
                $campaign->update($values);

                return $campaign;
            }

            // New campaigns start switched off; turn on approval mode from the campaign page to test them.
            return LeadCampaign::create($values + ['enabled' => false, 'auto_send' => false]);
        });

        return $this->redirectRoute('outreach.show', $campaign, navigate: true);
    }

    public function render()
    {
        return view('livewire.outreach.campaign-editor', [
            'projects' => $this->projects()->orderBy('name')->get(),
            'inboxes' => $this->inboxes()->orderBy('email_address')->get(),
            'goals' => CampaignGoals::labels(),
            'validLink' => PublicPage::validUrl($this->productUrl),
        ])->layout('components.layouts.app', ['title' => $this->campaignId ? 'Edit campaign' : 'New campaign']);
    }
}
