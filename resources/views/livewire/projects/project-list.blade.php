<div>
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-display">Projects</h1>
            <p class="text-caption mt-2">Manage your backlink outreach projects</p>
        </div>
        <button wire:click="toggleForm" class="px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity">
            + New Project
        </button>
    </div>

    <!-- Create Project Form -->
    @if($showForm)
        <div class="border border-border rounded-lg p-6 mb-8 bg-surface-muted">
            <h2 class="text-heading mb-4">Create New Project</h2>
            <form wire:submit="createProject" class="space-y-4">
                <div>
                    <label class="text-label block mb-2">Project Name</label>
                    <input type="text" wire:model="name" placeholder="e.g., Tech Blog Backlinks" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                    @error('name') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-label block mb-2">Website URL</label>
                    <input type="url" wire:model="website_url" placeholder="https://example.com" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                    @error('website_url') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-label block mb-2">Notes (Optional)</label>
                    <textarea wire:model="notes" placeholder="Any notes about this project..." class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" rows="3"></textarea>
                    @error('notes') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="flex-1 px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity">
                        Create Project
                    </button>
                    <button type="button" wire:click="toggleForm" class="flex-1 px-4 py-2 border border-border rounded-lg text-body hover:bg-surface-inset transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if($projects->count() > 0)
        <div class="grid gap-4">
            @foreach($projects as $project)
                <div class="border border-border rounded-lg p-6 hover:bg-surface-inset transition-colors">
                    <h3 class="text-heading"><a href="{{ route('projects.workspace', $project) }}" wire:navigate class="text-teal-700 hover:underline">{{ $project->name }} →</a></h3>
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
        />
    @endif
</div>
