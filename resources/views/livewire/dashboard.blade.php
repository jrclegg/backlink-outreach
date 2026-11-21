<div class="page" wire:poll.15s>
    <header class="page-header">
        <div>
            <h1 class="page-title">{{ now('Europe/London')->hour < 12 ? 'Good morning' : (now('Europe/London')->hour < 18 ? 'Good afternoon' : 'Good evening') }}, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
            <p class="page-subtitle">Here's what your outreach is doing.</p>
        </div>
        <a href="{{ route('outreach.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" />New campaign</a>
    </header>

    <section class="panel-flush">
        <div class="stat-grid lg:divide-x">
            @foreach(['Waiting for your approval' => $campaigns->sum('waiting_count'), 'Campaigns running' => $campaigns->where('enabled', true)->count(), 'Sent in the last 7 days' => $sentWeek, 'Replies in the last 7 days' => $repliesWeek] as $label => $value)
                <div class="stat"><p class="stat-label">{{ $label }}</p><p class="stat-value">{{ number_format($value) }}</p></div>
            @endforeach
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-5">
        <section class="min-w-0 space-y-3 lg:col-span-3">
            <div class="flex items-center justify-between"><h2 class="section-title">AI campaigns</h2><a href="{{ route('outreach.index') }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-900">View all</a></div>
            <div class="panel-flush">
                @forelse($campaigns->take(6) as $c)
                    <a href="{{ route('outreach.show', $c) }}" wire:navigate class="row" wire:key="dc-{{ $c->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $c->name }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $c->project->name }}@if($c->project->client_name) · {{ $c->project->client_name }}@endif</p>
                        </div>
                        @if($c->waiting_count)<span class="badge badge-amber">{{ $c->waiting_count }} to approve</span>@endif
                        <x-mode-badge :mode="$c->mode()" />
                    </a>
                @empty
                    <div class="px-5 py-10 text-center">
                        <p class="text-sm font-medium">No campaigns yet</p>
                        <p class="mt-1 text-sm text-zinc-500">Describe one in plain English and AI does the rest.</p>
                        <a href="{{ route('outreach.create') }}" wire:navigate class="btn-primary mt-4"><x-icon name="plus" />New campaign</a>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="min-w-0 space-y-3 lg:col-span-2">
            <h2 class="section-title">Latest replies</h2>
            <div class="panel-flush">
                @forelse($recent as $message)
                    <a href="{{ route('projects.workspace', ['project' => $message->conversation->project_id, 'tab' => 'mail']) }}" wire:navigate class="row" wire:key="rm-{{ $message->id }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-3"><p class="truncate text-sm font-medium">{{ $message->subject ?: '(no subject)' }}</p><span class="shrink-0 text-xs text-zinc-400">{{ $message->received_at?->diffForHumans(short: true) }}</span></div>
                            <p class="truncate text-xs text-zinc-500">{{ $message->from_email }} · {{ $message->conversation->project->name }}</p>
                        </div>
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-zinc-500">Replies appear here once your email accounts sync.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="space-y-3">
        <div class="flex items-center justify-between"><h2 class="section-title">Projects</h2><a href="{{ route('projects.index') }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-900">Manage</a></div>
        <div class="panel-flush">
            @forelse($cards as $card)
                <div class="row flex-wrap" wire:key="dp-{{ $card['project']->id }}">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('projects.workspace', $card['project']) }}" wire:navigate class="text-sm font-medium hover:underline underline-offset-2">{{ $card['project']->name }}</a>
                        <p class="truncate text-xs text-zinc-500">{{ preg_replace('#^https?://#', '', $card['project']->website_url) }}@if($card['project']->client_name) · Client: {{ $card['project']->client_name }}@endif</p>
                    </div>
                    <div class="flex items-center gap-5 text-xs text-zinc-500 tabular-nums">
                        <span><span class="font-semibold text-zinc-900">{{ number_format($card['unread']) }}</span> unread</span>
                        <span><span class="font-semibold text-zinc-900">{{ number_format($card['drafts']) }}</span> drafts</span>
                    </div>
                    <a href="{{ route('projects.workspace', ['project' => $card['project'], 'tab' => 'mail']) }}" wire:navigate class="btn-secondary btn-sm">Inbox</a>
                </div>
            @empty
                <div class="px-5 py-10 text-center">
                    <p class="text-sm font-medium">Add your first website</p>
                    <p class="mt-1 text-sm text-zinc-500">Create a project and connect an email account to start.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
