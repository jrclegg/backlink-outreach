<div>
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-display">Email Inboxes</h1>
            <p class="text-caption mt-2">Manage your SMTP and IMAP credentials</p>
        </div>
        <a href="{{ route('inboxes.create') }}" wire:navigate class="px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity">
            + Add Inbox
        </a>
    </div>

    @if($inboxes->count() > 0)
        <div class="grid gap-4">
            @foreach($inboxes as $inbox)
                <div class="border border-border rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-heading">{{ $inbox->name }}</h3>
                            <p class="text-caption mt-1">{{ $inbox->email_address }}</p>
                        </div>
                        <x-ui.status-pill :status="$inbox->is_active ? 'connected' : 'disconnected'" />
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <x-ui.empty-state
            title="No Email Inboxes Connected"
            description="Connect your first email inbox to start sending outreach campaigns."
            actionLabel="Connect Inbox"
        />
    @endif
</div>
