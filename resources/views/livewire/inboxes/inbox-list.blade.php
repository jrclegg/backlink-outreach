<div class="page" wire:poll.15s>
    <header class="page-header">
        <div>
            <h1 class="page-title">Email accounts</h1>
            <p class="page-subtitle">The inboxes your campaigns send from and sync replies into.</p>
        </div>
        <a href="{{ route('inboxes.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" />Connect account</a>
    </header>
    @if(session('sync'))<div role="status" class="alert alert-success">{{ session('sync') }}</div>@endif

    <div class="panel-flush">
        @forelse($inboxes as $inbox)
            <div class="row flex-wrap items-start" wire:key="inbox-{{ $inbox->id }}">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500"><x-icon name="mail" class="size-4" /></div>
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2"><p class="text-sm font-medium">{{ $inbox->name }}</p><span class="badge {{ $inbox->sync_error ? 'badge-red' : ($inbox->is_active ? 'badge-green' : 'badge-zinc') }}">{{ $inbox->sync_error ? 'Sync problem' : ($inbox->is_active ? 'Active' : 'Paused') }}</span></div>
                    <p class="text-xs text-zinc-500 break-all">{{ $inbox->email_address }}@if($inbox->from_address && $inbox->from_address !== $inbox->email_address) · sends as {{ $inbox->from_address }}@endif</p>
                    <p class="text-xs text-zinc-500">Synced {{ $inbox->last_polled_at?->diffForHumans() ?? 'not yet' }} · {{ number_format($importedCounts[$inbox->id] ?? 0) }} emails imported</p>
                    @if($inbox->sync_error)<p class="text-xs text-rose-700">{{ $inbox->sync_error }}</p>@endif
                    @if($inbox->projects->isNotEmpty())
                        <div class="flex flex-wrap gap-x-3 gap-y-1 pt-1">@foreach($inbox->projects as $project)<a href="{{ route('projects.workspace', ['project' => $project, 'tab' => 'mail']) }}" wire:navigate class="link text-xs">{{ $project->name }} inbox</a>@endforeach</div>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button wire:click="sync({{ $inbox->id }})" wire:loading.attr="disabled" class="btn-ghost btn-sm"><x-icon name="refresh" class="size-3.5" />Sync</button>
                    <a href="{{ route('inboxes.edit', $inbox->id) }}" wire:navigate class="btn-secondary btn-sm">Edit</a>
                </div>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <p class="text-sm font-semibold">No email accounts yet</p>
                <p class="mt-1 text-sm text-zinc-500">Connect an IONOS, Gmail, Yahoo or Zoho account to start sending and receiving.</p>
            </div>
        @endforelse
    </div>
</div>
