<div class="page">
    <header class="page-header">
        <div>
            <h1 class="page-title">Projects</h1>
            <p class="page-subtitle">Your websites and your clients. Each one has its own inbox and campaigns.</p>
        </div>
        <button wire:click="toggleForm" class="{{ $showForm ? 'btn-secondary' : 'btn-primary' }}">@if($showForm) Cancel @else<x-icon name="plus" />New project @endif</button>
    </header>

    @if($showForm)
        <form wire:submit="createProject" class="panel space-y-4">
            <p class="section-title">New project</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="label">Name<input type="text" wire:model="name" placeholder="e.g. Beat Darwin" class="field" />@error('name')<span class="hint block text-rose-700">{{ $message }}</span>@enderror</label>
                <label class="label">Website<input type="url" wire:model="website_url" placeholder="https://example.com" class="field" />@error('website_url')<span class="hint block text-rose-700">{{ $message }}</span>@enderror</label>
            </div>
            <label class="label">Client name <span class="font-normal text-zinc-500">(leave blank if it's your own)</span><input type="text" wire:model="client_name" placeholder="e.g. Smith Recruitment" class="field" /></label>
            <label class="label">Notes <span class="font-normal text-zinc-500">(optional)</span><textarea wire:model="notes" rows="3" class="field" placeholder="Audience, voice, anything the AI should know"></textarea></label>
            <div class="flex justify-end gap-2"><button type="button" wire:click="toggleForm" class="btn-ghost">Cancel</button><button type="submit" class="btn-primary">Create project</button></div>
        </form>
    @endif

    <div class="panel-flush">
        @forelse($projects as $project)
            <a href="{{ route('projects.workspace', $project) }}" wire:navigate class="row" wire:key="project-{{ $project->id }}">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-sm font-semibold text-zinc-600">{{ mb_strtoupper(mb_substr($project->name, 0, 1)) }}</div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2"><p class="truncate text-sm font-medium">{{ $project->name }}</p>@if($project->client_name)<span class="badge badge-violet badge-plain">{{ $project->client_name }}</span>@endif</div>
                    <p class="truncate text-xs text-zinc-500">{{ preg_replace('#^https?://#', '', $project->website_url) }}@if($project->notes) · {{ \Illuminate\Support\Str::limit($project->notes, 80) }}@endif</p>
                </div>
                <x-icon name="chevron-right" class="size-4 text-zinc-300" />
            </a>
        @empty
            <div class="px-6 py-16 text-center">
                <p class="text-sm font-semibold">No projects yet</p>
                <p class="mt-1 text-sm text-zinc-500">Create a project for each website or client you do outreach for.</p>
            </div>
        @endforelse
    </div>
</div>
