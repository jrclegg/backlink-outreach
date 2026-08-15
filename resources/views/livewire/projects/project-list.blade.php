<div>
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-display">Projects</h1>
            <p class="text-caption mt-2">Manage your backlink outreach projects</p>
        </div>
        <button class="px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity">
            + New Project
        </button>
    </div>

    @if($projects->count() > 0)
        <div class="grid gap-4">
            @foreach($projects as $project)
                <div class="border border-border rounded-lg p-6 hover:bg-surface-inset transition-colors">
                    <h3 class="text-heading">{{ $project->name }}</h3>
                    <p class="text-caption mt-2">{{ $project->website_url }}</p>
                    @if($project->notes)
                        <p class="text-caption mt-2">{{ $project->notes }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <x-ui.empty-state
            title="No Projects Created"
            description="Start by creating a project for each website you want to build backlinks for."
            actionLabel="Create First Project"
        />
    @endif
</div>
