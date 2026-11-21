<div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-display">AI campaigns</h1>
            <p class="text-caption mt-1">Each campaign researches prospects, writes a personal email to each, checks it, and sends from your inbox, for any project or client.</p>
        </div>
        <a href="{{ route('outreach.create') }}" wire:navigate class="btn-primary">+ New campaign</a>
    </header>
    @forelse($campaigns as $c)
        <a href="{{ route('outreach.show', $c) }}" wire:navigate wire:key="campaign-{{ $c->id }}" class="panel block hover:border-teal-500 transition-colors">
            <div class="flex flex-wrap justify-between gap-3">
                <div>
                    <h2 class="text-heading">{{ $c->name }}</h2>
                    <p class="text-caption">{{ $c->project->name }}@if($c->project->client_name) · Client: {{ $c->project->client_name }}@endif · {{ $goals[$c->goal] ?? $c->goal }} · {{ $c->inbox?->sender_address ?? 'no sending account' }}</p>
                </div>
                <span class="text-sm font-semibold {{ $c->mode() === 'off' ? 'text-text-muted' : 'text-teal-700' }}">{{ ucfirst($c->mode()) }}</span>
            </div>
            <p class="text-sm mt-3">{{ $c->waiting_count }} waiting for you · {{ $c->sent_today_count }} sent today · {{ $c->sent_count }} sent in total · up to {{ $c->daily_limit }}/day</p>
        </a>
    @empty
        <section class="panel text-center py-10">
            <h2 class="text-heading">No campaigns yet</h2>
            <p class="text-caption mt-2">Create one by describing what you want in plain English.</p>
        </section>
    @endforelse
</div>
