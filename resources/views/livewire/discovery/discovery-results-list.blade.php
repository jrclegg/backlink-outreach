<div>
    <div class="mb-8">
        <a href="{{ route('discover.index') }}" wire:navigate class="text-accent text-body mb-4 inline-block hover:underline">← Back to Queries</a>
        <h1 class="text-display">{{ $this->query->keyword }} + "{{ $this->query->footprint_pattern }}"</h1>
        <p class="text-caption mt-2">{{ $this->query->results_found }} prospects found</p>
    </div>

    <!-- Results Table -->
    @if($results->count() > 0)
        <div class="border border-border rounded-lg overflow-hidden mb-8">
            <table class="w-full">
                <thead class="bg-surface-muted border-b border-border">
                    <tr>
                        <th class="px-4 py-3 text-left">
                            <input type="checkbox" wire:model.live="selectAll" />
                        </th>
                        <th class="px-4 py-3 text-left text-label">Website</th>
                        <th class="px-4 py-3 text-left text-label">Title</th>
                        <th class="px-4 py-3 text-left text-label">Email</th>
                        <th class="px-4 py-3 text-left text-label">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results as $result)
                        <tr class="border-b border-border hover:bg-surface-muted transition-colors">
                            <td class="px-4 py-3">
                                <input type="checkbox" wire:model.live="selected" value="{{ $result->id }}" />
                            </td>
                            <td class="px-4 py-3 text-body">
                                <a href="{{ $result->site_url }}" target="_blank" class="text-accent hover:underline">{{ parse_url($result->site_url, PHP_URL_HOST) }}</a>
                            </td>
                            <td class="px-4 py-3 text-body">{{ $result->page_title ?? '-' }}</td>
                            <td class="px-4 py-3 text-body">{{ $result->contact_email ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <x-ui.status-pill :status="$result->extraction_status" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(count($selected) > 0)
            <div class="fixed bottom-0 left-0 right-0 bg-surface border-t border-border px-8 py-4">
                <div class="max-w-6xl mx-auto flex justify-between items-center">
                    <p class="text-body">{{ count($selected) }} prospect(s) selected</p>
                    <button wire:click="addToProspects" class="px-6 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity">
                        Add to Prospects
                    </button>
                </div>
            </div>
        @endif

        <!-- Pagination -->
        <div class="mt-8">
            {{ $results->links() }}
        </div>
    @else
        <x-ui.empty-state
            title="No Results"
            description="This query returned no results. Try a different keyword or footprint pattern."
        />
    @endif
</div>
