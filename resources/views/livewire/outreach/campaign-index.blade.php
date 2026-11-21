<div class="page">
    <header class="page-header">
        <div>
            <h1 class="page-title">AI campaigns</h1>
            <p class="page-subtitle">Each campaign finds prospects, writes a personal email to each, checks it and sends from your inbox.</p>
        </div>
        <a href="{{ route('outreach.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" />New campaign</a>
    </header>

    <div class="panel-flush">
        @forelse($campaigns as $c)
            <a href="{{ route('outreach.show', $c) }}" wire:navigate wire:key="campaign-{{ $c->id }}" class="row flex-wrap sm:flex-nowrap">
                <div class="min-w-0 flex-1 basis-full sm:basis-auto">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-medium">{{ $c->name }}</p>
                        @if($c->project->client_name)<span class="badge badge-violet badge-plain">{{ $c->project->client_name }}</span>@endif
                    </div>
                    <p class="truncate text-xs text-zinc-500 mt-0.5">{{ $c->project->name }} · {{ $goals[$c->goal] ?? $c->goal }} · {{ $c->inbox?->sender_address ?? 'No sending account' }}</p>
                </div>
                <div class="flex items-center gap-5 text-xs text-zinc-500 tabular-nums">
                    <span title="Drafts waiting for you"><span class="font-semibold {{ $c->waiting_count ? 'text-amber-700' : 'text-zinc-900' }}">{{ $c->waiting_count }}</span> to approve</span>
                    <span><span class="font-semibold text-zinc-900">{{ $c->sent_today_count }}</span>/{{ $c->daily_limit }} today</span>
                    <span class="hidden md:inline"><span class="font-semibold text-zinc-900">{{ number_format($c->sent_count) }}</span> sent</span>
                </div>
                <x-mode-badge :mode="$c->mode()" class="sm:w-24 justify-center" />
                <x-icon name="chevron-right" class="hidden sm:block size-4 text-zinc-300" />
            </a>
        @empty
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex size-11 items-center justify-center rounded-xl bg-zinc-100 text-zinc-500"><x-icon name="campaigns" class="size-5" /></div>
                <p class="mt-4 text-sm font-semibold">No campaigns yet</p>
                <p class="mt-1 text-sm text-zinc-500">Describe what you want in plain English. AI writes the brief.</p>
                <a href="{{ route('outreach.create') }}" wire:navigate class="btn-primary mt-5"><x-icon name="plus" />New campaign</a>
            </div>
        @endforelse
    </div>
</div>
