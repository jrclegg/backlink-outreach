<?php

namespace App\Livewire\Workspace;

use App\Jobs\CheckOpportunityLink;
use App\Jobs\PollInboxForReplies;
use App\Jobs\ResearchTargetPage;
use App\Jobs\SendReviewedMessage;
use App\Models\Conversation;
use App\Models\Opportunity;
use App\Models\OutreachMessage;
use App\Models\Project;
use App\Models\ResearchRun;
use App\Models\TargetPage;
use App\Services\Outreach\Assistant;
use App\Services\Outreach\PublicPage;
use App\Services\Outreach\SitePages;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectWorkspace extends Component
{
    use WithPagination;

    #[Locked]
    public int $projectId;

    #[Locked]
    public string $composeToken;

    #[Url]
    public string $tab = 'research';

    #[Locked]
    public array $sitePages = [];

    public string $selectedSitePage = '';

    public bool $pagesLoaded = false;

    public string $pageUrl = '';

    public string $pageTitle = '';

    public string $pageBrief = '';

    public string $instructions = '';

    public ?int $pageId = null;

    public ?int $inboxId = null;

    public ?int $threadId = null;

    public ?int $draftId = null;

    public string $to = '';

    public string $subject = '';

    public string $body = '';

    public string $draftInstructions = '';

    public string $search = '';

    public string $mailFilter = 'all';

    public string $notice = '';

    public string $projectNotes = '';

    public array $placements = [];

    public array $opportunityNotes = [];

    public function mount(Project $project): void
    {
        $this->projectId = $project->id;
        $this->composeToken = (string) Str::uuid();
        $this->projectNotes = $this->project()->notes ?? '';
        $this->pageId = TargetPage::where('project_id', $this->projectId)->value('id');
        $this->inboxId = $this->accounts()->value('email_inboxes.id');
    }

    private function project(): Project
    {
        return Auth::user()->currentOrganization->projects()->findOrFail($this->projectId);
    }

    private function accounts()
    {
        return $this->project()->emailInboxes()->where('is_active', true);
    }

    private function page(): TargetPage
    {
        return TargetPage::where('project_id', $this->project()->id)->findOrFail($this->pageId);
    }

    private function opportunity(int $id): Opportunity
    {
        return Opportunity::whereHas('targetPage', fn ($q) => $q->where('project_id', $this->project()->id))->findOrFail($id);
    }

    private function thread(): Conversation
    {
        return Conversation::where('project_id', $this->project()->id)->whereHas('emailInbox', fn ($q) => $q->where('organization_id', Auth::user()->current_organization_id))->findOrFail($this->threadId);
    }

    public function updatedMailFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function saveContext(): void
    {
        $this->validate(['projectNotes' => 'nullable|string|max:10000']);
        $this->project()->update(['notes' => $this->projectNotes]);
        $this->notice = 'Project context saved.';
    }

    public function loadSitePages(): void
    {
        $project = $this->project();
        $this->sitePages = \Cache::remember('site-pages-'.hash('sha256', $project->website_url), now()->addHour(),
            fn () => app(SitePages::class)->discover($project->website_url));
        $this->pagesLoaded = true;
    }

    public function updatedSelectedSitePage(): void
    {
        $page = collect($this->sitePages)->firstWhere('url', $this->selectedSitePage);
        $this->pageUrl = $page['url'] ?? '';
        $this->pageTitle = $page['title'] ?? '';
        $this->pageBrief = '';
    }

    public function addPage(): void
    {
        $this->validate(['pageUrl' => 'required|url:http,https|max:2048', 'pageTitle' => 'required|string|max:255', 'pageBrief' => 'nullable|string|max:10000']);
        $project = $this->project();
        if (strtolower(preg_replace('/^www\./', '', parse_url($this->pageUrl, PHP_URL_HOST))) !== strtolower(preg_replace('/^www\./', '', parse_url($project->website_url, PHP_URL_HOST)))) {
            $this->addError('pageUrl', 'Choose a target page on this project’s website.');

            return;
        }
        $page = TargetPage::firstOrCreate(['project_id' => $project->id, 'url' => $this->pageUrl], ['title' => $this->pageTitle, 'brief' => $this->pageBrief]);
        $this->pageId = $page->id;
        $this->reset('pageUrl', 'pageTitle', 'pageBrief', 'selectedSitePage');
        $this->notice = 'Target page saved.';
    }

    public function research(): void
    {
        $this->validate(['pageId' => 'required|integer', 'instructions' => 'nullable|string|max:5000']);
        $page = $this->page();
        if (! app(Assistant::class)->configured($this->project()->organization)) {
            $this->addError('research', app(Assistant::class)->usesCodex() ? 'Codex is not signed in with ChatGPT. Check AI settings for setup instructions.' : 'Add an API key in AI settings first.');

            return;
        }
        $lock = \Cache::lock('research-page-'.$page->id, 10);
        if (! $lock->get()) {
            return;
        }
        try {
            if ($page->researchRuns()->whereIn('status', ['queued', 'running'])->exists()) {
                $this->notice = 'Research is already queued or running.';

                return;
            }
            $run = $page->researchRuns()->create(['instructions' => $this->instructions]);
            ResearchTargetPage::dispatch($run);
            $this->notice = 'Research queued. Results will appear here when ready.';
        } finally {
            $lock->release();
        }
    }

    public function sync(): void
    {
        $accounts = $this->accounts()->get();
        if ($accounts->isEmpty()) {
            $this->notice = 'No active email account is connected to this project. Connect your mailbox and assign it to this project before syncing.';

            return;
        }
        foreach ($accounts as $inbox) {
            PollInboxForReplies::dispatch($inbox);
        }
        $this->notice = 'Inbox sync queued.';
    }

    public function openThread(int $id): void
    {
        $this->threadId = $id;
        $thread = $this->thread();
        $thread->update(['read_at' => now()]);
        $this->tab = 'mail';
        $this->inboxId = $thread->email_inbox_id;
        $draft = $thread->messages()->where('status', 'draft')->latest('id')->first();
        $this->draftId = $draft?->id;
        $this->composeToken = $draft ? explode('@', $draft->message_id)[0] : (string) Str::uuid();
        $this->to = $draft?->to_email ?? $thread->contact_email;
        $this->subject = $draft?->subject ?? (str_starts_with(strtolower($thread->subject), 're:') ? $thread->subject : 'Re: '.$thread->subject);
        $this->body = $draft?->body ?? '';
        $this->draftInstructions = '';
        $this->resetValidation();
    }

    public function compose(): void
    {
        $this->project();
        $this->reset('threadId', 'draftId', 'to', 'subject', 'body', 'draftInstructions');
        $this->composeToken = (string) Str::uuid();
        $this->tab = 'mail';
        $this->inboxId = $this->accounts()->value('email_inboxes.id');
    }

    public function draftOpportunity(int $id): void
    {
        $opportunity = $this->opportunity($id);
        $inbox = $this->accounts()->find($this->inboxId);
        if (! $inbox) {
            $this->addError('draft', 'Choose a connected sending account first.');

            return;
        }
        $thread = $opportunity->conversations()->where('email_inbox_id', $inbox->id)->first();
        if (! $thread) {
            $thread = Conversation::create(['project_id' => $this->projectId, 'email_inbox_id' => $inbox->id, 'opportunity_id' => $opportunity->id, 'contact_email' => $opportunity->contact_email ?? '', 'subject' => 'A resource for '.$opportunity->title]);
        }
        $this->openThread($thread->id);
        $this->generateDraft();
    }

    public function generateDraft(): void
    {
        $project = $this->project();
        $thread = $this->threadId ? $this->thread() : null;
        $inbox = $thread?->emailInbox ?? $this->accounts()->find($this->inboxId);
        if (! $inbox) {
            $this->addError('draft', 'Choose a connected sending account.');

            return;
        }
        try {
            $context = ['project' => $project->only('name', 'website_url', 'notes'), 'sender' => $inbox->from_name, 'recipient' => $this->to, 'instructions' => $this->draftInstructions, 'existing_draft' => $this->body];
            if ($thread) {
                $context['conversation'] = $thread->messages()->whereIn('status', ['sent', 'received'])->orderByDesc('id')->limit(12)->get(['direction', 'subject', 'body'])->reverse()->values()->toArray();
                $context['opportunity'] = $thread->opportunity?->toArray();
                $context['target_page'] = $thread->opportunity?->targetPage?->toArray();
            }
            $draft = app(Assistant::class)->json($project->organization, 'Write a concise, personal plain-text outreach email or reply using this context. Propose a useful, specific addition linking to the target page. Do not claim to have read something without evidence, promise payment, or invent prior contact. Respect the latest incoming reply. Return JSON only with subject and body strings. '.json_encode($context));
            if (! is_string($draft['subject'] ?? null) || ! is_string($draft['body'] ?? null) || ! $draft['body']) {
                throw new \RuntimeException('AI returned an incomplete draft.');
            }
            $this->subject = mb_substr($draft['subject'], 0, 255);
            $this->body = $draft['body'];
            $this->notice = 'Draft generated. Review the recipient and wording before sending.';
        } catch (\Throwable $e) {
            $this->addError('draft', $e instanceof \RuntimeException ? $e->getMessage() : 'AI drafting failed. Please try again.');
        }
    }

    public function saveDraft(): ?OutreachMessage
    {
        $this->validate(['to' => 'required|email|max:255', 'subject' => 'required|string|max:255', 'body' => 'required|string|max:30000', 'inboxId' => 'required|integer']);
        $project = $this->project();
        $thread = $this->threadId ? $this->thread() : null;
        $inbox = $thread?->emailInbox ?? $this->accounts()->findOrFail($this->inboxId);
        abort_unless($inbox->organization_id === $project->organization_id && $inbox->is_active, 403);
        $messageId = $this->composeToken.'@'.parse_url($project->website_url, PHP_URL_HOST);
        $previous = OutreachMessage::where('email_inbox_id', $inbox->id)->where('message_id', $messageId)->first();
        if ($previous) {
            abort_unless($previous->conversation->project_id === $project->id, 403);
            if ($previous->status !== 'draft') {
                $this->addError('draft', 'This email has already been approved. Open the conversation to start another message.');

                return null;
            }
            $thread = $previous->conversation;
            $this->threadId = $thread->id;
            $this->draftId = $previous->id;
        }
        if ($thread && $thread->messages()->whereIn('status', ['queued', 'sending'])->exists()) {
            $this->addError('draft', 'Wait for the pending send to finish.');

            return null;
        }

        return DB::transaction(function () use ($thread, $inbox, $project, $messageId) {
            if (! $thread) {
                $thread = Conversation::create(['project_id' => $project->id, 'email_inbox_id' => $inbox->id, 'contact_email' => strtolower($this->to), 'subject' => $this->subject]);
                $this->threadId = $thread->id;
            }
            $parent = $thread->messages()->whereIn('status', ['sent', 'received'])->latest('id')->first();
            $values = ['email_inbox_id' => $inbox->id, 'direction' => 'outbound', 'status' => 'draft', 'from_email' => $inbox->sender_address, 'to_email' => strtolower($this->to), 'subject' => $this->subject, 'body' => $this->body, 'in_reply_to' => $parent?->message_id, 'references' => $parent ? trim(($parent->references ?? '').' <'.$parent->message_id.'>') : null];
            if ($this->draftId) {
                $message = $thread->messages()->where('status', 'draft')->findOrFail($this->draftId);
                if (! OutreachMessage::whereKey($message->id)->where('status', 'draft')->update($values)) {
                    throw ValidationException::withMessages(['draft' => 'This draft was already approved in another request.']);
                }
                $message->refresh();
            } else {
                $message = $thread->messages()->create($values + ['message_id' => $messageId]);
            }
            $thread->update(['contact_email' => strtolower($this->to)]);
            $this->draftId = $message->id;
            $this->notice = 'Draft saved. Nothing has been sent.';

            return $message;
        });
    }

    public function send(): void
    {
        $lock = \Cache::lock('compose-'.Auth::id().'-'.$this->projectId, 15);
        if (! $lock->get()) {
            return;
        }
        try {
            $message = $this->saveDraft();
            if (! $message) {
                return;
            }
            if (! OutreachMessage::whereKey($message->id)->where('status', 'draft')->update(['status' => 'queued', 'approved_at' => now()])) {
                $this->addError('draft', 'This draft was already approved.');

                return;
            }
            $message->refresh();
            SendReviewedMessage::dispatch($message);
            $this->draftId = null;
            $this->composeToken = (string) Str::uuid();
            $this->body = '';
            $this->notice = 'Reviewed email queued for sending.';
        } finally {
            $lock->release();
        }
    }

    public function archiveThread(): void
    {
        $thread = $this->thread();
        $thread->update(['status' => $thread->status === 'archived' ? 'open' : 'archived']);
    }

    public function setOpportunityStatus(int $id, string $status): void
    {
        abort_unless(in_array($status, ['new', 'contacted', 'replied', 'placed', 'rejected']), 422);
        $this->opportunity($id)->update(['status' => $status]);
    }

    public function saveOpportunity(int $id): void
    {
        $this->validate(["opportunityNotes.$id" => 'nullable|string|max:10000']);
        $this->opportunity($id)->update(['notes' => $this->opportunityNotes[$id] ?? null]);
        $this->notice = 'Opportunity notes saved.';
    }

    public function checkLink(int $id): void
    {
        $op = $this->opportunity($id);
        $url = $this->placements[$id] ?? $op->placement_url ?? $op->url;
        if (strlen($url) > 2048 || ! PublicPage::validUrl($url)) {
            $this->addError('placement', 'Enter a valid public placement page URL.');

            return;
        }
        $op->update(['placement_url' => $url]);
        CheckOpportunityLink::dispatch($op);
        $this->notice = 'Backlink check queued.';
    }

    public function render()
    {
        $project = $this->project();
        $pages = TargetPage::where('project_id', $project->id)->get();
        $opportunities = Opportunity::whereHas('targetPage', fn ($q) => $q->where('project_id', $project->id))->when($this->pageId, fn ($q) => $q->where('target_page_id', $this->pageId))->latest()->get();
        $runs = ResearchRun::whereHas('targetPage', fn ($q) => $q->where('project_id', $project->id))->when($this->pageId, fn ($q) => $q->where('target_page_id', $this->pageId))->latest()->limit(5)->get();
        $threads = Conversation::where('project_id', $project->id)->when($this->mailFilter === 'archived', fn ($q) => $q->where('status', 'archived'), fn ($q) => $q->where('status', 'open'))
            ->when($this->mailFilter === 'unread', fn ($q) => $q->whereNull('read_at')->whereHas('messages', fn ($m) => $m->where('direction', 'inbound')))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('subject', 'like', '%'.$this->search.'%')->orWhere('contact_email', 'like', '%'.$this->search.'%')))
            ->withCount(['messages as failed_messages_count' => fn ($q) => $q->where('direction', 'outbound')->where('status', 'failed')])
            ->with('emailInbox')->orderByDesc('last_message_at')->orderByDesc('id')->paginate(15);
        $thread = $this->threadId ? $this->thread()->load(['messages' => fn ($q) => $q->orderBy('id')]) : null;

        return view('livewire.workspace.project-workspace', compact('project', 'pages', 'opportunities', 'runs', 'threads', 'thread') + ['usesCodex' => app(Assistant::class)->usesCodex(), 'accounts' => $this->accounts()->get()])->layout('components.layouts.app', ['title' => $project->name]);
    }
}
