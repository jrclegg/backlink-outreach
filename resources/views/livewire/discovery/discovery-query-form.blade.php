<div>
    <div class="mb-8">
        <h1 class="text-display">Prospect Discovery</h1>
        <p class="text-caption mt-2">Find prospects in the same niche as your competitor</p>
    </div>

    <!-- Search Form -->
    <div class="border border-border rounded-lg p-6 mb-8 bg-surface-muted">
        <h2 class="text-heading mb-4">Find Linking Sites</h2>
        <form wire:submit="search" class="space-y-4">
            <div>
                <label class="text-label block mb-2">Competitor or Niche Domain</label>
                <input type="text" wire:model="competitor_domain" placeholder="e.g., competitor.com or techblog.com" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                <p class="text-caption text-text-muted mt-1">Enter a competitor or well-known site in your niche. We'll find other sites in the same space that accept backlinks.</p>
                @error('competitor_domain') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="text-label block mb-2">Max Results</label>
                <input type="number" wire:model="result_cap" placeholder="25" min="10" max="100" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                <p class="text-caption text-text-muted mt-1">Maximum backlinks to discover (10-100)</p>
            </div>

            <div class="flex gap-4">
                <button type="submit" :disabled="searching" class="px-6 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity disabled:opacity-50">
                    @if($searching)
                        <span>Searching...</span>
                    @else
                        Find Prospects
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

    <!-- How it works -->
    <div class="border border-border rounded-lg p-6 mb-8 bg-surface">
        <h3 class="text-heading mb-3">How It Works</h3>
        <div class="space-y-2 text-caption text-text-muted">
            <p>✓ Enter a competitor or niche domain</p>
            <p>✓ We search the web for related sites in the same space</p>
            <p>✓ Find websites that accept guest posts and backlinks</p>
            <p>✓ Automatically extract contact emails</p>
            <p>✓ Build campaigns and send outreach</p>
        </div>
    </div>

    <!-- Recent Queries -->
    @if($queries->count() > 0)
        <h2 class="text-heading mb-4">Recent Queries</h2>
        <div class="space-y-3">
            @foreach($queries as $query)
                <a href="{{ route('discover.results', $query) }}" wire:navigate class="block border border-border rounded-lg p-4 bg-surface hover:bg-surface-muted transition-colors">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="text-body font-medium">{{ $query->keyword }}</h3>
                            <p class="text-caption text-text-muted mt-1">{{ $query->created_at->format('M d, H:i') }}</p>
                        </div>
                        <x-ui.status-pill :status="$query->status" />
                    </div>
                    <p class="text-caption">{{ $query->results_found }} prospects found</p>
                </a>
            @endforeach
        </div>
    @else
        <x-ui.empty-state
            title="No Queries Yet"
            description="Start a backlink discovery to find high-quality prospects already proven to place links."
        />
    @endif
</div>
