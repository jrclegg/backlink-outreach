<div>
    <div class="mb-8">
        <h1 class="text-display">Suppression List</h1>
        <p class="text-caption mt-2">Manage emails that have unsubscribed or should not receive campaigns</p>
    </div>

    @if (session('success'))
        <div class="mb-6 px-4 py-3 bg-success-subtle text-success rounded-lg text-caption">
            {{ session('success') }}
        </div>
    @endif

    <!-- Search Bar -->
    <div class="mb-6">
        <input
            type="text"
            wire:model.live="searchEmail"
            placeholder="Search by email..."
            class="w-full max-w-md px-4 py-2 border border-border rounded-lg text-body focus:ring-2 focus:ring-accent focus:border-transparent"
        />
    </div>

    <!-- Suppressions Table -->
    @if($suppressions->count() > 0)
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-heading">{{ $suppressions->total() }} Suppressed Emails</h2>
            </div>

            <x-ui.table :columns="['email', 'reason', 'added_on', 'actions']">
                @foreach($suppressions as $suppression)
                    <tr class="border-b border-border hover:bg-surface-muted transition-colors">
                        <td class="px-4 py-3 text-body">
                            {{ $suppression->email }}
                        </td>
                        <td class="px-4 py-3 text-caption text-text-muted">
                            <x-ui.status-pill :status="$suppression->reason" />
                        </td>
                        <td class="px-4 py-3 text-caption text-text-muted">
                            {{ $suppression->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3">
                            <button
                                wire:click="removeSuppression({{ $suppression->id }})"
                                wire:confirm="Are you sure you want to remove this suppression?"
                                class="px-3 py-1 bg-danger-subtle text-danger rounded text-caption hover:opacity-80 transition-opacity"
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $suppressions->links(data: ['alignment' => 'center']) }}
            </div>
        </div>
    @else
        <x-ui.empty-state
            title="No Suppressions"
            description="No emails are currently suppressed. Suppressions are created when users unsubscribe from your campaigns."
        />
    @endif
</div>
