<div>
    <div class="mb-8">
        <h1 class="text-display">Prospect Discovery</h1>
        <p class="text-caption mt-2">Search for websites and convert them to prospects</p>
    </div>

    <!-- Search Form -->
    <div class="border border-border rounded-lg p-6 mb-8 bg-surface-inset">
        <h2 class="text-heading mb-4">Search for Prospects</h2>
        <form wire:submit="search" class="space-y-4">
            <div>
                <label class="text-label block mb-2">Search Phrase</label>
                <input
                    type="text"
                    wire:model="search_phrase"
                    placeholder="e.g., write for us technology, guest posts marketing, contribute blog"
                    class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-primary"
                    :disabled="searching"
                />
                <p class="text-caption text-text-muted mt-1">Enter any search phrase to find websites. Results show URL and auto-extracted contact emails.</p>
                @error('search_phrase') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-label block mb-2">Max Results</label>
                <input
                    type="number"
                    wire:model="result_cap"
                    placeholder="25"
                    min="10"
                    max="100"
                    class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-primary"
                    :disabled="searching"
                />
                <p class="text-caption text-text-muted mt-1">Number of results to find (10-100)</p>
            </div>

            <div class="flex gap-4">
                <button
                    type="submit"
                    :disabled="searching"
                    class="px-6 py-2 bg-primary text-white rounded-lg text-body hover:bg-primary-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                >
                    @if($searching)
                        <span>Searching...</span>
                    @else
                        Search
                    @endif
                </button>
            </div>

            @if($message)
                <div class="px-4 py-3 bg-success-subtle text-success-dark rounded-lg text-caption border border-success">
                    {{ $message }}
                </div>
            @endif
        </form>
    </div>

    <!-- Search Results -->
    @if(!empty($results))
        <div class="mb-8">
            <h2 class="text-heading mb-4">Search Results ({{ count($results) }})</h2>
            <div class="space-y-3">
                @foreach($results as $result)
                    <div class="border border-border rounded-lg p-4 bg-surface hover:bg-surface-inset transition-colors">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-body font-medium truncate">{{ $result['title'] }}</h3>
                                <p class="text-caption text-text-muted mt-1 truncate">{{ $result['url'] }}</p>
                                <p class="text-caption mt-2">
                                    <span class="font-medium">Email:</span>
                                    @if($result['email'] !== 'Not found')
                                        <a href="mailto:{{ $result['email'] }}" class="text-primary hover:underline">{{ $result['email'] }}</a>
                                    @else
                                        <span class="text-text-muted">{{ $result['email'] }}</span>
                                    @endif
                                </p>
                            </div>
                            <a href="{{ route('prospects.create') }}?url={{ urlencode($result['url']) }}&email={{ urlencode($result['email'] !== 'Not found' ? $result['email'] : '') }}"
                               wire:navigate
                               class="px-3 py-2 bg-primary text-white rounded text-caption hover:bg-primary-dark transition-colors whitespace-nowrap">
                                Add as Prospect
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Recent Queries -->
    @if($queries->count() > 0)
        <h2 class="text-heading mb-4">Recent Searches</h2>
        <div class="space-y-3">
            @foreach($queries as $query)
                <a href="{{ route('discover.results', $query) }}" wire:navigate class="block border border-border rounded-lg p-4 bg-surface hover:bg-surface-inset transition-colors">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="text-body font-medium">{{ $query->keyword }}</h3>
                            <p class="text-caption text-text-muted mt-1">{{ $query->created_at->format('M d, H:i') }}</p>
                        </div>
                        <span class="px-2 py-1 bg-success-subtle text-success text-caption rounded">
                            {{ $query->results_found }} found
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
