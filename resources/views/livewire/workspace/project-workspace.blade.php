<div wire:init="loadSitePages" wire:poll.8s class="workspace space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><a href="{{ route('projects.index') }}" wire:navigate class="text-sm text-teal-700">← All projects</a><h1 class="text-display mt-2">{{ $project->name }}</h1><p class="text-caption">{{ $project->website_url }}</p></div>
        <div class="flex gap-2"><a href="{{ route('projects.employer-outreach',$project) }}" wire:navigate class="btn-primary">Employer outreach</a><a href="{{ route('ai.settings') }}" wire:navigate class="btn-secondary">AI settings</a><a href="{{ route('inboxes.create') }}" wire:navigate class="btn-secondary">Connect email</a></div>
    </header>
    @if($notice)<div role="status" class="rounded-lg bg-teal-50 border border-teal-200 p-3 text-sm">{{ $notice }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-lg bg-rose-50 p-3 text-rose-800 text-sm">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <nav class="flex flex-wrap gap-2 border-b border-border pb-3" aria-label="Project sections">
        @foreach(['research'=>'Research & opportunities','mail'=>'Project inbox','context'=>'Project context'] as $key=>$label)
            <button wire:click="$set('tab','{{ $key }}')" class="{{ $tab===$key ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</button>
        @endforeach
    </nav>
    @if($tab==='context')
        <section class="panel"><h2 class="text-heading">Help AI understand this project</h2><p class="text-caption mt-1">Audience, geography, useful resources, your voice, and any outreach preferences.</p>
            <form wire:submit="saveContext" class="space-y-3 mt-4"><textarea aria-label="Project context" wire:model="projectNotes" rows="8" class="field"></textarea><button class="btn-primary">Save context</button></form>
        </section>
    @elseif($tab==='research')
        <div class="grid lg:grid-cols-3 gap-5">
            <section class="panel lg:col-span-2 space-y-4"><h2 class="text-heading">Find the right links for a specific page</h2><p class="text-caption">AI researches relevance and realistic placement routes, with sources you can inspect.</p>
                <label class="block text-sm">Target page<select wire:model.live="pageId" class="field mt-1"><option value="">Choose a page</option>@foreach($pages as $page)<option value="{{ $page->id }}">{{ $page->title }} — {{ $page->url }}</option>@endforeach</select></label>
                <label class="block text-sm">What should AI look for?<textarea wire:model="instructions" rows="3" class="field mt-1" placeholder="For example: relevant local resource pages with a clear submission route. Avoid paid placements."></textarea></label>
                <button wire:click="research" wire:loading.attr="disabled" class="btn-primary" @disabled(!$pageId)>Research opportunities</button>
                <p class="text-caption">Research runs in the background and uses {{ $usesCodex ? 'your Codex allowance' : 'your API account' }}. Nothing is emailed.</p>
            </section>
            <section class="panel"><h2 class="text-heading">Choose a website page</h2><form wire:submit="addPage" class="space-y-3 mt-4">
                <label class="block text-sm">Website page<select wire:model.live="selectedSitePage" class="field mt-1" @disabled(!$pagesLoaded)><option value="">{{ $pagesLoaded ? 'Choose a page from your website' : 'Loading website pages…' }}</option>@foreach($sitePages as $sitePage)<option value="{{ $sitePage['url'] }}">{{ $sitePage['title'] }}{{ $sitePage['title'] !== $sitePage['url'] ? ' — '.$sitePage['url'] : '' }}</option>@endforeach</select></label>
                @if($pagesLoaded && !$sitePages)<p class="text-caption">We couldn’t find website pages automatically. You can add a URL below.</p>@endif
                <details wire:ignore.self><summary class="text-sm cursor-pointer">Page details / enter a URL manually</summary><div class="space-y-3 mt-3">
                <label class="block text-sm">Page URL<input type="url" wire:model="pageUrl" class="field mt-1" placeholder="{{ $project->website_url }}/useful-page"></label>
                <label class="block text-sm">Page title<input wire:model="pageTitle" class="field mt-1"></label>
                </div></details>
                <label class="block text-sm">Why is it useful?<textarea wire:model="pageBrief" rows="3" class="field mt-1" placeholder="Who it helps and what makes it worth linking to"></textarea></label>
                <button class="btn-secondary">Save target page</button>
            </form></section>
        </div>
        @foreach($runs as $run)
            <details wire:ignore.self class="panel text-sm" wire:key="run-{{ $run->id }}"><summary class="cursor-pointer">Research {{ $run->created_at->format('j M, H:i') }} · <strong>{{ $run->status }}</strong></summary>
                @if($run->error)<p class="text-rose-700 mt-3">{{ $run->error }}</p>@endif
                @if($run->status==='queued')<p class="text-caption mt-3">Waiting for the research worker.</p>@endif
                @if($run->status==='completed')<p class="text-caption mt-3">Only opportunities with live source URLs are saved; research may return no suitable matches.</p>@endif
                @if($run->report)<p class="whitespace-pre-wrap mt-4">{{ $run->report }}</p>@endif
                <div class="mt-3 space-y-1">@foreach($run->sources ?? [] as $source)<a class="block text-teal-700 break-words" href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer">{{ $source['title'] ?: $source['url'] }}</a>@endforeach</div>
            </details>
        @endforeach
        <div class="flex flex-wrap justify-between items-end gap-4"><div><h2 class="text-heading">Opportunities <span class="text-text-muted">{{ $opportunities->count() }}</span></h2><p class="text-caption">AI assessments are suggestions. Inspect the evidence before contacting a site.</p></div>
            <label class="text-sm">Draft from<select wire:model="inboxId" class="field mt-1"><option value="">Choose account</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->email_address }}</option>@endforeach</select></label></div>
        @forelse($opportunities as $op)
            <article class="panel space-y-4" wire:key="op-{{ $op->id }}">
                <div class="flex justify-between gap-4"><div><span class="text-xs uppercase tracking-wide text-teal-700">{{ str_replace('_',' ',$op->type) }}</span><h3 class="text-heading mt-1"><a href="{{ $op->url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ $op->title }} ↗</a></h3><p class="text-caption break-all">{{ $op->url }}</p></div><span class="text-sm">{{ ucfirst($op->status) }}</span></div>
                <div class="grid md:grid-cols-2 gap-4 text-sm"><div><h4 class="font-semibold mb-1">Why it fits</h4><p>{{ $op->relevance }}</p></div><div><h4 class="font-semibold mb-1">Why it may be attainable</h4><p>{{ $op->attainability }}</p></div></div>
                <div class="text-sm"><h4 class="font-semibold mb-1">Outreach angle</h4><p>{{ $op->angle }}</p></div>
                <div class="text-sm space-y-1"><h4 class="font-semibold">Evidence</h4>@foreach($op->sources as $source)<a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="block text-teal-700">{{ $source['title'] ?: $source['url'] }}</a>@if($source['quote'] ?? '')<p class="text-text-muted">“{{ $source['quote'] }}”</p>@endif @endforeach</div>
                <p class="text-sm">Contact suggestion: {{ $op->contact_email ?: 'No published email found — add a verified address when drafting.' }} @if($op->contact_url)<a href="{{ $op->contact_url }}" target="_blank" rel="noopener noreferrer" class="text-teal-700 underline">Contact / submission page</a>@endif</p>
                <div class="flex flex-wrap gap-2"><button wire:click="draftOpportunity({{ $op->id }})" wire:loading.attr="disabled" class="btn-primary">Draft outreach</button><button wire:click="setOpportunityStatus({{ $op->id }},'rejected')" class="btn-secondary">Dismiss</button><button wire:click="setOpportunityStatus({{ $op->id }},'new')" class="btn-secondary">Reopen</button></div>
                <details wire:ignore.self class="border-t border-border pt-3"><summary class="text-sm cursor-pointer">Notes & backlink tracking · {{ str_replace('_',' ',$op->link_status) }}</summary>
                    <div class="space-y-3 mt-3"><p class="text-caption">{{ $op->notes }}</p><textarea wire:model="opportunityNotes.{{ $op->id }}" aria-label="Opportunity notes" rows="2" class="field" placeholder="Add or replace notes"></textarea><button wire:click="saveOpportunity({{ $op->id }})" class="btn-secondary">Save notes</button>
                        <input type="url" wire:model="placements.{{ $op->id }}" class="field" aria-label="Placement URL" placeholder="{{ $op->placement_url ?: $op->url }}"><button wire:click="checkLink({{ $op->id }})" class="btn-secondary">Check for target-page link</button><p class="text-caption">Last checked: {{ $op->checked_at?->diffForHumans() ?? 'Never' }}. Enter the actual placement page if different.</p></div>
                </details>
            </article>
        @empty
            <section class="panel text-center py-10"><h3 class="text-heading">Start with a page worth linking to</h3><p class="text-caption mt-2">Choose a website page above and save it as a target, then ask AI to research opportunities.</p></section>
        @endforelse
    @elseif($tab==='mail')
        <div class="flex flex-wrap gap-3 items-center"><button wire:click="compose" class="btn-primary">New email</button><button wire:click="sync" wire:loading.attr="disabled" class="btn-secondary">Sync inboxes</button><a href="{{ route('mail.unassigned') }}" wire:navigate class="text-sm text-teal-700">Unassigned mail</a></div>
        @forelse($accounts as $account)<div class="text-caption">{{ $account->email_address }} · Last sync {{ $account->last_polled_at?->diffForHumans() ?? 'not yet run' }} @if($account->sync_error)<span class="text-rose-700">{{ $account->sync_error }}</span>@endif</div>@empty<p class="panel">Connect an email account and assign it to this project to get started.</p>@endforelse
        <div class="grid lg:grid-cols-3 gap-5">
            <section class="panel space-y-3"><input wire:model.live.debounce.300ms="search" class="field" placeholder="Search conversations" aria-label="Search conversations"><select wire:model.live="mailFilter" class="field" aria-label="Conversation filter"><option value="all">Open conversations</option><option value="unread">Unread</option><option value="archived">Archived</option></select>
                @forelse($threads as $item)<button wire:click="openThread({{ $item->id }})" wire:key="thread-{{ $item->id }}" class="w-full text-left p-3 rounded-lg border {{ $threadId===$item->id ? 'border-teal-500 bg-teal-50' : 'border-border' }}"><p class="text-sm font-semibold break-words">{{ $item->subject }}</p>@if($item->failed_messages_count)<p class="text-sm font-semibold text-rose-700 mt-1">Sending failed · {{ $item->failed_messages_count }} {{ $item->failed_messages_count === 1 ? 'email' : 'emails' }}</p>@endif<p class="text-caption break-all">{{ $item->contact_email ?: 'Draft opportunity' }}</p><p class="text-xs text-text-muted mt-1">{{ $item->emailInbox->email_address }} @if(!$item->read_at) · Unread @endif</p></button>@empty<p class="text-caption py-5">No conversations here yet.</p>@endforelse
                {{ $threads->links() }}
            </section>
            <section class="panel lg:col-span-2 space-y-4">
                @if($thread)<div class="flex justify-between gap-3"><h2 class="text-heading">{{ $thread->subject }}</h2><button wire:click="archiveThread" class="btn-secondary">{{ $thread->status==='archived' ? 'Reopen' : 'Archive' }}</button></div>
                    @foreach($thread->messages as $message)
                        @if($message->status!=='draft')<article wire:key="message-{{ $message->id }}" class="rounded-lg p-4 {{ $message->status==='failed' ? 'bg-rose-50 border border-rose-300' : ($message->direction==='inbound' ? 'bg-zinc-50 border border-border' : 'bg-teal-50') }}">@if($message->status==='failed')<p role="alert" class="font-semibold text-rose-800 mb-2">Sending failed — delivery not confirmed</p>@elseif($message->status==='queued')<p role="status" class="font-semibold text-amber-800 mb-2">Waiting to send — not sent yet</p>@elseif($message->status==='sending')<p role="status" class="font-semibold text-amber-800 mb-2">Sending — waiting for server confirmation</p>@elseif($message->status==='sent')<p class="font-semibold text-teal-800 mb-2">Accepted by sending server</p>@endif<div class="text-xs text-text-muted mb-2">{{ $message->from_email }} → {{ $message->to_email }} · {{ $message->status }} · {{ ($message->received_at ?? $message->sent_at ?? $message->created_at)->format('j M H:i') }}</div><p class="font-medium text-sm">{{ $message->subject }}</p><p class="whitespace-pre-wrap break-words text-sm mt-2">{{ $message->body }}</p>@if($message->error)<p class="text-rose-700 text-sm mt-2">{{ $message->error }}</p>@endif</article>@endif
                    @endforeach
                @else<h2 class="text-heading">Compose an email</h2>@endif
                <form wire:submit="saveDraft" class="space-y-3 border-t border-border pt-4">
                    <label class="block text-sm">From<select wire:model="inboxId" class="field mt-1" @disabled($thread)>@if($thread)<option value="{{ $thread->email_inbox_id }}">{{ $thread->emailInbox->email_address }}</option>@else<option value="">Choose account</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->email_address }}</option>@endforeach @endif</select></label>
                    <label class="block text-sm">To<input type="email" wire:model="to" class="field mt-1" required></label><label class="block text-sm">Subject<input wire:model="subject" class="field mt-1" required></label>
                    <label class="block text-sm">Message<textarea wire:model="body" rows="10" class="field mt-1" required></textarea></label>
                    <label class="block text-sm">Instructions for AI<input wire:model="draftInstructions" class="field mt-1" placeholder="Shorter, warmer, or explain what you want to say"></label>
                    <div class="flex flex-wrap gap-2"><button type="button" wire:click="generateDraft" wire:loading.attr="disabled" class="btn-secondary">{{ $body ? 'Revise with AI' : 'Draft with AI' }}</button><button type="submit" wire:loading.attr="disabled" class="btn-secondary">Save draft</button><button type="button" wire:click="send" wire:confirm="Send this reviewed email to the address shown?" wire:loading.attr="disabled" class="btn-primary">Approve & send</button></div>
                    <p class="text-caption">AI never sends automatically. Review the address, claims and links before approving.</p>
                </form>
            </section>
        </div>
    @endif
    <div wire:loading wire:target="research,generateDraft,draftOpportunity,saveDraft,send,sync,checkLink" role="status" class="fixed bottom-5 right-5 bg-zinc-900 text-white rounded-lg px-4 py-3 text-sm shadow-lg">Working…</div>
</div>
