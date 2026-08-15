<div>
    <div class="mb-8">
        <h1 class="text-display">{{ $campaign->name }}</h1>
        <p class="text-caption mt-2">{{ $campaign->project->name }} - {{ $campaign->emailInbox->name }}</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-5 gap-4 mb-8">
        <div class="border border-border rounded-lg p-4 text-center">
            <div class="text-display text-accent">{{ $stats['sent'] }}</div>
            <div class="text-caption mt-2">Sent</div>
        </div>
        <div class="border border-border rounded-lg p-4 text-center">
            <div class="text-display text-accent">{{ $stats['opened'] }}</div>
            <div class="text-caption mt-2">Opened</div>
        </div>
        <div class="border border-border rounded-lg p-4 text-center">
            <div class="text-display text-accent">{{ $stats['clicked'] }}</div>
            <div class="text-caption mt-2">Clicked</div>
        </div>
        <div class="border border-border rounded-lg p-4 text-center">
            <div class="text-display text-accent">{{ $stats['replied'] }}</div>
            <div class="text-caption mt-2">Replied</div>
        </div>
        <div class="border border-border rounded-lg p-4 text-center">
            <div class="text-display text-accent">{{ $stats['total'] }}</div>
            <div class="text-caption mt-2">Total</div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="border border-border rounded-lg p-6 mb-8">
        <div class="text-caption mb-2">Progress: {{ $stats['sent'] }} / {{ $stats['total'] }} sent</div>
        <div class="w-full bg-surface-inset rounded-full h-2">
            <div class="bg-accent h-2 rounded-full" style="width: {{ $stats['total'] > 0 ? ($stats['sent'] / $stats['total'] * 100) : 0 }}%"></div>
        </div>
    </div>

    <!-- Recipients Table -->
    <div class="border border-border rounded-lg overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="bg-surface-muted border-b border-border">
                    <th class="px-4 py-3 text-left text-label">Email</th>
                    <th class="px-4 py-3 text-left text-label">Status</th>
                    <th class="px-4 py-3 text-left text-label">Sent</th>
                    <th class="px-4 py-3 text-left text-label">Opened</th>
                    <th class="px-4 py-3 text-left text-label">Replied</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recipients as $recipient)
                    <tr class="border-b border-border hover:bg-surface-inset">
                        <td class="px-4 py-3 text-body">{{ $recipient->prospect->contact_email }}</td>
                        <td class="px-4 py-3">
                            <x-ui.status-pill :status="$recipient->status" />
                        </td>
                        <td class="px-4 py-3 text-caption">{{ $recipient->sent_at ? $recipient->sent_at->format('M d') : '—' }}</td>
                        <td class="px-4 py-3 text-caption">{{ $recipient->opened_at ? $recipient->opened_at->format('M d') : '—' }}</td>
                        <td class="px-4 py-3 text-caption">{{ $recipient->replied_at ? $recipient->replied_at->format('M d') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $recipients->links() }}
</div>
