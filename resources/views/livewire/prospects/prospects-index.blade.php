<div>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold">Prospects</h2>
                <p class="text-text-muted text-sm mt-1">Manage and track your outreach targets</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('prospects.create') }}" wire:navigate class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">
                    + Add Prospect
                </a>
                <a href="{{ route('prospects.import') }}" wire:navigate class="px-4 py-2 bg-secondary text-white rounded-lg hover:bg-secondary-dark transition-colors">
                    Import CSV
                </a>
                <a href="{{ route('discover.index') }}" wire:navigate class="px-4 py-2 bg-tertiary text-white rounded-lg hover:bg-tertiary-dark transition-colors">
                    Discover
                </a>
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="bg-surface-inset rounded-lg p-4 space-y-4">
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">Search</label>
                    <input
                        type="text"
                        wire:model.live="searchQuery"
                        placeholder="Email, domain, or name..."
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">Status</label>
                    <select
                        wire:model.live="statusFilter"
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">Sort by</label>
                    <select
                        wire:change="sortBy($event.target.value)"
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                        <option value="created_at" {{ $sortBy === 'created_at' ? 'selected' : '' }}>Date Added</option>
                        <option value="contact_email" {{ $sortBy === 'contact_email' ? 'selected' : '' }}>Email</option>
                        <option value="site_url" {{ $sortBy === 'site_url' ? 'selected' : '' }}>Website</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Session Messages -->
        @if(session('message'))
            <div class="bg-success-subtle border border-success text-success-dark px-4 py-3 rounded-lg">
                {{ session('message') }}
            </div>
        @endif

        <!-- Prospects Table -->
        <div class="bg-surface-inset rounded-lg overflow-hidden border border-border">
            @if($prospects->count())
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface border-b border-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-text-primary">
                                    <button wire:click="sortBy('site_url')" class="hover:text-primary">
                                        Website
                                        @if($sortBy === 'site_url')
                                            {{ $sortDirection === 'asc' ? '↑' : '↓' }}
                                        @endif
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-text-primary">
                                    Contact Email
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-text-primary">
                                    Status
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-text-primary">
                                    Engagement
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-text-primary">
                                    Activity
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-text-primary">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($prospects as $prospect)
                                @php
                                    $latestRecipient = $prospect->campaignRecipients()
                                        ->orderByDesc('created_at')
                                        ->first();
                                    $lastActivity = $latestRecipient ? (
                                        $latestRecipient->replied_at ?: (
                                            $latestRecipient->first_clicked_at ?: (
                                                $latestRecipient->opened_at ?: $latestRecipient->sent_at
                                            )
                                        )
                                    ) : null;
                                    $replyCount = $latestRecipient ? $latestRecipient->replies()->count() : 0;
                                @endphp
                                <tr class="hover:bg-surface transition-colors">
                                    <td class="px-4 py-3 text-sm">
                                        <div class="font-medium text-text-primary">{{ $prospect->site_url }}</div>
                                        <div class="text-text-muted text-xs">{{ $prospect->contact_name ?? 'Unknown' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-text-muted">
                                        <a href="mailto:{{ $prospect->contact_email }}" class="hover:text-primary">
                                            {{ $prospect->contact_email }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <button
                                            wire:click="toggleProspectStatus({{ $prospect->id }})"
                                            class="px-3 py-1 rounded-full text-xs font-medium transition-colors
                                                {{ $prospect->status === 'new' ? 'bg-gray-subtle text-gray' : '' }}
                                                {{ $prospect->status === 'contacted' ? 'bg-blue-subtle text-blue' : '' }}
                                                {{ $prospect->status === 'responded' ? 'bg-success-subtle text-success' : '' }}
                                                {{ $prospect->status === 'qualified' ? 'bg-green-subtle text-green-dark' : '' }}
                                                {{ $prospect->status === 'rejected' ? 'bg-danger-subtle text-danger' : '' }}
                                            "
                                        >
                                            {{ ucfirst($prospect->status) }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-text-muted">
                                        @if($latestRecipient)
                                            <div class="flex items-center gap-3 text-xs">
                                                @if($latestRecipient->sent_at)
                                                    <span class="flex items-center gap-1">
                                                        📧 1
                                                    </span>
                                                @endif
                                                @if($latestRecipient->open_count)
                                                    <span class="flex items-center gap-1 text-blue">
                                                        👁 {{ $latestRecipient->open_count }}
                                                    </span>
                                                @endif
                                                @if($latestRecipient->click_count)
                                                    <span class="flex items-center gap-1 text-success">
                                                        🔗 {{ $latestRecipient->click_count }}
                                                    </span>
                                                @endif
                                                @if($replyCount)
                                                    <span class="flex items-center gap-1 text-green-dark font-medium">
                                                        💬 {{ $replyCount }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-text-muted">
                                        @if($lastActivity)
                                            <time datetime="{{ $lastActivity->toIso8601String() }}" title="{{ $lastActivity->format('Y-m-d H:i') }}">
                                                {{ $lastActivity->diffForHumans() }}
                                            </time>
                                        @else
                                            <span>Never</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                wire:click="deleteProspect({{ $prospect->id }})"
                                                onclick="confirm('Delete this prospect?') || event.stopImmediatePropagation()"
                                                class="px-2 py-1 text-danger hover:bg-danger-subtle rounded transition-colors text-xs"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="bg-surface px-4 py-3 border-t border-border flex items-center justify-between">
                    <div class="text-sm text-text-muted">
                        Showing {{ $prospects->firstItem() }} to {{ $prospects->lastItem() }} of {{ $prospects->total() }}
                    </div>
                    <div class="flex gap-2">
                        @if($prospects->onFirstPage())
                            <button disabled class="px-3 py-1 bg-surface-inset text-text-muted rounded text-sm cursor-not-allowed">← Prev</button>
                        @else
                            <button wire:click="previousPage" class="px-3 py-1 hover:bg-surface-inset rounded text-sm">← Prev</button>
                        @endif

                        @if($prospects->hasMorePages())
                            <button wire:click="nextPage" class="px-3 py-1 hover:bg-surface-inset rounded text-sm">Next →</button>
                        @else
                            <button disabled class="px-3 py-1 bg-surface-inset text-text-muted rounded text-sm cursor-not-allowed">Next →</button>
                        @endif
                    </div>
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <p class="text-text-muted mb-4">No prospects found</p>
                    <div class="flex gap-2 justify-center">
                        <a href="{{ route('prospects.import') }}" wire:navigate class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors text-sm">
                            Import CSV
                        </a>
                        <a href="{{ route('discover.index') }}" wire:navigate class="px-4 py-2 bg-secondary text-white rounded-lg hover:bg-secondary-dark transition-colors text-sm">
                            Discover
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
