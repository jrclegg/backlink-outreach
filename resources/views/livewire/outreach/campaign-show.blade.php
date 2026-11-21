<div wire:poll.10s class="page">
    <header class="space-y-3">
        <a href="{{ route('outreach.index') }}" wire:navigate class="crumb"><x-icon name="arrow-left" class="size-3.5" />AI campaigns</a>
        <div class="page-header">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="page-title">{{ $campaign->name }}</h1>
                    <x-mode-badge :mode="$campaign->mode()" />
                </div>
                <p class="page-subtitle">{{ $campaign->project->name }}@if($campaign->project->client_name) · Client: {{ $campaign->project->client_name }}@endif · {{ $goal }} · {{ $campaign->inbox?->sender_address ?? 'No sending account' }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('projects.workspace', ['project' => $campaign->project, 'tab' => 'mail']) }}" wire:navigate class="btn-secondary"><x-icon name="inbox" />Inbox</a>
                <a href="{{ route('outreach.edit', $campaign) }}" wire:navigate class="btn-secondary"><x-icon name="pencil" />Edit brief</a>
            </div>
        </div>
    </header>

    @if($notice)<div role="status" class="alert alert-success"><x-icon name="check" class="size-4 mt-0.5 shrink-0" />{{ $notice }}</div>@endif
    @if($errors->any())<div role="alert" class="alert alert-error"><div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif

    <section class="panel-flush">
        <div class="flex flex-wrap items-center gap-3 px-5 py-4">
            <div class="segmented" role="group" aria-label="Campaign mode">
                <button wire:click="setMode('off')" class="{{ $campaign->mode() === 'off' ? 'is-active' : '' }}" aria-pressed="{{ $campaign->mode() === 'off' ? 'true' : 'false' }}">Off</button>
                <button wire:click="setMode('approval')" class="{{ $campaign->mode() === 'approval' ? 'is-active' : '' }}" aria-pressed="{{ $campaign->mode() === 'approval' ? 'true' : 'false' }}">Approval</button>
                <button wire:click="setMode('automatic')" wire:confirm="Send drafts that pass every quality check automatically, up to {{ $campaign->daily_limit }} a day?" class="{{ $campaign->mode() === 'automatic' ? 'is-active' : '' }}" aria-pressed="{{ $campaign->mode() === 'automatic' ? 'true' : 'false' }}">Automatic</button>
            </div>
            <p class="text-sm text-zinc-500 flex-1 min-w-48">
                @if($campaign->mode() === 'off') Switched off. Choose Approval to test it safely.
                @elseif($campaign->mode() === 'approval') Up to {{ $campaign->daily_limit }} drafts a day from {{ $campaign->send_time }}, sent only when you approve.
                @else Sends up to {{ $campaign->daily_limit }} a day from {{ $campaign->send_time }}. Weaker drafts wait for you.
                @endif
            </p>
            <button wire:click="runNow" wire:loading.attr="disabled" class="btn-primary" @disabled($campaign->mode() === 'off')><x-icon name="play" class="size-3.5" /><span wire:loading.remove wire:target="runNow">Run now</span><span wire:loading wire:target="runNow">Starting…</span></button>
        </div>
        @if($batch)
            <div class="flex flex-wrap items-center gap-2 border-t border-border bg-zinc-50/60 px-5 py-2.5 text-xs text-zinc-500">
                <span>Latest research {{ \Carbon\Carbon::parse($batch->day)->isToday() ? 'today' : \Carbon\Carbon::parse($batch->day)->format('j M') }}</span>
                <span class="badge {{ match($batch->status) { 'failed' => 'badge-red', 'researching', 'drafting', 'queued' => 'badge-amber', 'ready' => 'badge-green', default => 'badge-zinc' } }}">{{ ucfirst(str_replace('_', ' ', $batch->status)) }}</span>
                @if($batch->error)<span class="text-rose-700">{{ $batch->error }}</span>@endif
            </div>
        @endif
        <div class="stat-grid border-t border-border lg:divide-x">
            @foreach($stats as $label => $value)
                <div class="stat"><p class="stat-label">{{ $label }}</p><p class="stat-value">{{ number_format($value) }}</p></div>
            @endforeach
        </div>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="segmented overflow-x-auto max-w-full" role="group" aria-label="Filter prospects">
            @foreach(['waiting' => 'To approve', 'sent' => 'Sent', 'replied' => 'Replied', 'skipped' => 'Skipped', 'all' => 'All'] as $key => $label)
                <button wire:click="$set('filter','{{ $key }}')" class="{{ $filter === $key ? 'is-active' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
        <p class="text-xs text-zinc-500">Showing the latest {{ $prospects->count() }}</p>
    </div>

    <div class="panel-flush">
        @forelse($prospects as $p)
            @php
                $state = $p->message?->status && $p->message->status !== 'draft' ? $p->message->status : $p->status;
                $badge = match ($state) { 'sent' => 'badge-green', 'queued', 'sending' => 'badge-teal', 'failed' => 'badge-red', 'needs_review' => 'badge-amber', 'passed' => 'badge-teal', 'skipped', 'excluded' => 'badge-zinc', default => 'badge-zinc' };
                $stateLabel = match ($state) { 'passed' => 'Ready', 'needs_review' => 'Needs a look', 'queued' => 'Sending', default => ucfirst(str_replace('_', ' ', $state)) };
            @endphp
            {{-- Alpine keeps each row's open state across Livewire's 10-second refresh. --}}
            <article class="border-t border-border first:border-t-0" wire:key="prospect-{{ $p->id }}" x-data="{ open: {{ $loop->first && $filter === 'waiting' ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="flex w-full items-center gap-4 px-5 py-4 text-left hover:bg-zinc-50 transition-colors" :aria-expanded="open">
                    <x-icon name="chevron-right" class="size-4 shrink-0 text-zinc-400 transition-transform" ::class="open && 'rotate-90'" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $p->organization }}</p>
                        <p class="truncate text-xs text-zinc-500">{{ $p->message?->subject ? $p->message->subject.' · ' : '' }}{{ $p->email }}</p>
                    </div>
                    <span class="badge {{ $badge }}">{{ $stateLabel }}</span>
                </button>
                <div x-show="open" x-collapse.duration.150ms x-cloak class="space-y-4 px-5 pb-5 sm:pl-14">
                <p class="text-xs text-zinc-500 break-all">{{ $p->contact_name ? $p->contact_name.' · ' : '' }}{{ $p->email }} · <a href="{{ $p->source_url }}" target="_blank" rel="noopener noreferrer" class="link font-normal">where the email is published</a></p>
                <div class="inset text-zinc-700">
                    <p><span class="font-medium text-zinc-900">Why them</span> · {{ $p->detail }} <a href="{{ $p->detail_url }}" target="_blank" rel="noopener noreferrer" class="link font-normal">page</a></p>
                </div>
                @if($p->reason && $p->status === 'needs_review')
                    <div class="alert alert-warning text-xs"><p>{{ $p->reason }}</p></div>
                @elseif($p->reason && $p->status !== 'passed')
                    <p class="text-xs text-zinc-500">{{ $p->reason }}</p>
                @endif

                @if($p->message?->status === 'draft' && in_array($p->status, ['passed', 'needs_review']))
                    <div class="space-y-3">
                        <label class="label">Subject<input wire:model="edits.{{ $p->id }}.subject" class="field"></label>
                        <label class="label">Message<textarea wire:model="edits.{{ $p->id }}.body" rows="11" class="field"></textarea></label>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-border pt-4">
                        <button wire:click="skip({{ $p->id }})" wire:loading.attr="disabled" class="btn-ghost">Skip</button>
                        <button wire:click="approve({{ $p->id }})" wire:confirm="Send this email to {{ $p->email }}?" wire:loading.attr="disabled" class="btn-primary"><x-icon name="check" />Approve & send</button>
                    </div>
                @elseif($p->message)
                    <div class="text-sm">
                        <p class="font-medium">{{ $p->message->subject }}</p>
                        <p class="mt-2 whitespace-pre-wrap rounded-lg bg-zinc-50 p-4 text-zinc-700">{{ $p->message->body }}</p>
                        @if($p->message->error)<p class="mt-2 text-rose-700">{{ $p->message->error }}</p>@endif
                    </div>
                @endif
                </div>
            </article>
        @empty
            <div class="py-14 text-center">
                <div class="mx-auto flex size-11 items-center justify-center rounded-xl bg-zinc-100 text-zinc-500"><x-icon name="inbox" class="size-5" /></div>
                <p class="mt-4 text-sm font-semibold">Nothing here</p>
                <p class="mt-1 text-sm text-zinc-500">{{ $campaign->mode() === 'off' ? 'Switch to Approval and press Run now to see the first drafts.' : 'Press Run now, or wait for the daily start time. Drafts appear here as they are written.' }}</p>
            </div>
        @endforelse
    </div>
</div>
