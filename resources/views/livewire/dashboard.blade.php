<div>
    <div class="mb-8">
        <h1 class="text-display">Dashboard</h1>
        @if($organization)
            <p class="text-caption mt-2">{{ $organization->name }}</p>
        @endif
    </div>

    @if($projects->count() > 0)
        <div class="grid gap-6">
            @foreach($projects as $project)
                <div class="border border-border rounded-lg p-6">
                    <h2 class="text-heading">{{ $project->name }}</h2>
                    <p class="text-caption mt-2">{{ $project->website_url }}</p>
                </div>
            @endforeach
        </div>
    @else
        <x-ui.empty-state
            title="No Projects Yet"
            description="Create your first project to get started with prospect discovery and campaigns."
            actionLabel="Create Project"
            actionUrl="{{ route('projects.index') }}"
        />
    @endif
</div>
