<div>
    <div class="mb-8">
        <h1 class="text-display">Backlink Graph Search</h1>
        <p class="text-caption mt-2">Discover prospects by searching backlinks from competitor domains</p>
    </div>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 bg-success-subtle text-success rounded-lg text-caption">
            {{ session('success') }}
        </div>
    @endif

    <!-- Search Form -->
    <div class="max-w-2xl border border-border rounded-lg p-6 mb-8">
        <form wire:submit="search" class="space-y-6">
            <div>
                <label class="text-label block mb-2">Competitor Domain</label>
                <input
                    type="text"
                    wire:model="competitorDomain"
                    placeholder="e.g., competitor.com"
                    class="w-full px-4 py-2 border border-border rounded-lg text-body focus:ring-2 focus:ring-accent focus:border-transparent"
                />
                @error('competitorDomain') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-label block mb-2">Select Project (optional)</label>
                <select wire:model="projectId" class="w-full px-4 py-2 border border-border rounded-lg text-body">
                    <option value="">-- Select a project to add prospects to --</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="w-full px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity">
                Search Backlinks
            </button>
        </form>
    </div>

    <!-- Results -->
    @if($showResults)
        @if($backlinks->count() > 0)
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-heading">{{ $backlinks->total() }} Backlinks Found</h2>
                    @if(!empty($selectedLinks))
                        <button
                            wire:click="bulkAddAsProspects"
                            class="px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity"
                        >
                            Add {{ count($selectedLinks) }} as Prospects
                        </button>
                    @endif
                </div>

                <x-ui.table :columns="['source_url', 'anchor_text', 'discovered_at', 'actions']">
                    @foreach($backlinks as $link)
                        <tr class="border-b border-border hover:bg-surface-muted transition-colors">
                            <td class="px-4 py-3 text-body align-top">
                                <input
                                    type="checkbox"
                                    value="{{ $link->id }}"
                                    wire:model="selectedLinks"
                                    class="mr-2"
                                />
                                <a href="{{ $link->source_url }}" target="_blank" class="text-accent hover:underline truncate max-w-xs">
                                    {{ $link->source_url }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-body">
                                {{ $link->anchor_text ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-caption text-text-muted">
                                {{ $link->discovered_at?->format('M d, Y') ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <button
                                    wire:click="addAsProspect({{ $link->id }})"
                                    class="px-3 py-1 bg-accent-subtle text-accent rounded text-caption hover:opacity-80 transition-opacity"
                                >
                                    Add as Prospect
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $backlinks->links(data: ['alignment' => 'center']) }}
                </div>
            </div>
        @else
            <x-ui.empty-state
                title="No Backlinks Found"
                description="Try searching with a different competitor domain to discover backlink opportunities."
            />
        @endif
    @endif
</div>
