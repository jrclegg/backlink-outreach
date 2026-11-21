<div wire:poll.10s class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('outreach.index') }}" wire:navigate class="text-sm text-teal-700">← All campaigns</a>
            <h1 class="text-display mt-2">{{ $campaign->name }}</h1>
            <p class="text-caption">{{ $campaign->project->name }}@if($campaign->project->client_name) · Client: {{ $campaign->project->client_name }}@endif · {{ $goal }} · from {{ $campaign->inbox?->sender_address ?? 'no account chosen' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('outreach.edit', $campaign) }}" wire:navigate class="btn-secondary">Edit brief</a>
            <a href="{{ route('projects.workspace', ['project' => $campaign->project, 'tab' => 'mail']) }}" wire:navigate class="btn-secondary">Project inbox</a>
        </div>
    </header>
    @if($notice)<div role="status" class="rounded-lg bg-teal-50 border border-teal-200 p-3 text-sm">{{ $notice }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-lg bg-rose-50 p-3 text-rose-800 text-sm">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section class="panel space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold mr-2">Mode</span>
            <button wire:click="setMode('off')" class="{{ $campaign->mode() === 'off' ? 'btn-primary' : 'btn-secondary' }}">Off</button>
            <button wire:click="setMode('approval')" class="{{ $campaign->mode() === 'approval' ? 'btn-primary' : 'btn-secondary' }}">Approval</button>
            <button wire:click="setMode('automatic')" wire:confirm="Send drafts that pass every quality check automatically, up to {{ $campaign->daily_limit }} a day?" class="{{ $campaign->mode() === 'automatic' ? 'btn-primary' : 'btn-secondary' }}">Automatic</button>
            <button wire:click="runNow" wire:loading.attr="disabled" class="btn-secondary ml-auto" @disabled($campaign->mode() === 'off')>Run now</button>
        </div>
        <p class="text-caption">
            @if($campaign->mode() === 'off') Switched off. Use Approval to test it: drafts are prepared for you and nothing sends without you.
            @elseif($campaign->mode() === 'approval') Prepares up to {{ $campaign->daily_limit }} drafts a day from {{ $campaign->send_time }} (London) for you to approve below.
            @else Sends up to {{ $campaign->daily_limit }} emails a day from {{ $campaign->send_time }} (London). Drafts below the quality bar wait for you.
            @endif
            @if($batch) Latest research: {{ $batch->day }} · {{ str_replace('_', ' ', $batch->status) }}@if($batch->error) · <span class="text-rose-700">{{ $batch->error }}</span>@endif @endif
        </p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($stats as $label => $value)<div class="rounded-lg border border-border p-3"><p class="text-caption">{{ $label }}</p><p class="text-heading">{{ $value }}</p></div>@endforeach
        </div>
    </section>

    <nav class="flex flex-wrap gap-2" aria-label="Filter prospects">
        @foreach(['waiting' => 'Waiting for you', 'sent' => 'Sent', 'replied' => 'Replied', 'skipped' => 'Skipped', 'all' => 'All'] as $key => $label)
            <button wire:click="$set('filter','{{ $key }}')" class="{{ $filter === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</button>
        @endforeach
    </nav>

    @forelse($prospects as $p)
        <article class="panel space-y-3" wire:key="prospect-{{ $p->id }}">
            <div class="flex flex-wrap justify-between gap-3">
                <div>
                    <h3 class="text-heading">{{ $p->organization }}</h3>
                    <p class="text-caption break-all">{{ $p->contact_name ? $p->contact_name.' · ' : '' }}{{ $p->email }} · <a href="{{ $p->source_url }}" target="_blank" rel="noopener noreferrer" class="text-teal-700 underline">where the email is published</a></p>
                </div>
                <span class="text-sm">{{ $p->message?->status && $p->message->status !== 'draft' ? ucfirst($p->message->status) : str_replace('_', ' ', ucfirst($p->status)) }}</span>
            </div>
            <p class="text-sm"><span class="font-semibold">Why them:</span> {{ $p->detail }} <a href="{{ $p->detail_url }}" target="_blank" rel="noopener noreferrer" class="text-teal-700 underline">source</a></p>
            @if($p->reason)<p class="text-caption">{{ $p->reason }}</p>@endif
            @if($p->message?->status === 'draft' && in_array($p->status, ['passed', 'needs_review']))
                <label class="block text-sm">Subject<input wire:model="edits.{{ $p->id }}.subject" class="field mt-1"></label>
                <label class="block text-sm">Message<textarea wire:model="edits.{{ $p->id }}.body" rows="10" class="field mt-1"></textarea></label>
                <div class="flex flex-wrap gap-2">
                    <button wire:click="approve({{ $p->id }})" wire:confirm="Send this email to {{ $p->email }}?" wire:loading.attr="disabled" class="btn-primary">Approve & send</button>
                    <button wire:click="skip({{ $p->id }})" wire:loading.attr="disabled" class="btn-secondary">Skip</button>
                </div>
            @elseif($p->message)
                <details class="text-sm"><summary class="cursor-pointer">{{ $p->message->subject }}</summary><p class="whitespace-pre-wrap mt-2">{{ $p->message->body }}</p>@if($p->message->error)<p class="text-rose-700 mt-2">{{ $p->message->error }}</p>@endif</details>
            @endif
        </article>
    @empty
        <section class="panel text-center py-10">
            <h3 class="text-heading">Nothing here yet</h3>
            <p class="text-caption mt-2">{{ $campaign->mode() === 'off' ? 'Switch to Approval and press Run now to see the first drafts.' : 'Press Run now, or wait for the daily start time. Drafts appear here as they are written.' }}</p>
        </section>
    @endforelse
</div>
