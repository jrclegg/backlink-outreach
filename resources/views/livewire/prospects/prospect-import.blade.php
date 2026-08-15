<div>
    <h1 class="text-display mb-8">Import Prospects</h1>

    <div class="max-w-2xl border border-border rounded-lg p-6">
        <form wire:submit="import" class="space-y-6">
            <div>
                <label class="text-label block mb-2">Project</label>
                <select wire:model="projectId" class="w-full px-4 py-2 border border-border rounded-lg text-body">
                    <option value="">Select a project...</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
                @error('projectId') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-label block mb-2">CSV File</label>
                <input type="file" wire:model="file" accept=".csv,.txt" class="w-full px-4 py-2 border border-border rounded-lg text-body" />
                <p class="text-caption mt-2">CSV should have columns: email, name, website (or contact_email, contact_name, site_url)</p>
                @error('file') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
            </div>

            @if($imported > 0 || $skipped > 0)
                <div class="px-4 py-3 bg-success-subtle text-success rounded-lg text-caption">
                    ✓ Imported {{ $imported }} prospects {{ $skipped > 0 ? "(skipped $skipped duplicates)" : '' }}
                </div>
            @endif

            <button type="submit" class="w-full px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity">
                Import Prospects
            </button>
        </form>
    </div>
</div>
