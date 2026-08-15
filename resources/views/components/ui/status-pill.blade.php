@php
    $statusColors = [
        // Prospect statuses
        'new' => ['bg' => 'bg-surface-inset', 'text' => 'text-text-primary'],
        'contacted' => ['bg' => 'bg-accent-subtle', 'text' => 'text-accent'],
        'replied' => ['bg' => 'bg-success-subtle', 'text' => 'text-success'],
        'bounced' => ['bg' => 'bg-danger-subtle', 'text' => 'text-danger'],
        'suppressed' => ['bg' => 'bg-zinc-100', 'text' => 'text-zinc-600'],
        'do_not_contact' => ['bg' => 'bg-zinc-100', 'text' => 'text-zinc-600'],

        // Discovery statuses
        'pending' => ['bg' => 'bg-warning-subtle', 'text' => 'text-warning'],
        'found' => ['bg' => 'bg-surface-inset', 'text' => 'text-text-primary'],
        'not_found' => ['bg' => 'bg-zinc-100', 'text' => 'text-text-muted'],
        'failed' => ['bg' => 'bg-danger-subtle', 'text' => 'text-danger'],

        // Campaign statuses
        'draft' => ['bg' => 'bg-surface-inset', 'text' => 'text-text-primary'],
        'queued' => ['bg' => 'bg-warning-subtle', 'text' => 'text-warning'],
        'sending' => ['bg' => 'bg-accent-subtle', 'text' => 'text-accent'],
        'sent' => ['bg' => 'bg-success-subtle', 'text' => 'text-success'],
        'paused' => ['bg' => 'bg-zinc-100', 'text' => 'text-zinc-600'],

        // Reply intents
        'interested' => ['bg' => 'bg-success-subtle', 'text' => 'text-success'],
        'declined' => ['bg' => 'bg-surface-inset', 'text' => 'text-text-muted'],
        'question' => ['bg' => 'bg-warning-subtle', 'text' => 'text-warning'],
        'other' => ['bg' => 'bg-zinc-100', 'text' => 'text-text-muted'],
        'unclassified' => ['bg' => 'bg-zinc-100', 'text' => 'text-text-muted'],

        // Backlink statuses
        'lost' => ['bg' => 'bg-danger-subtle', 'text' => 'text-danger'],
        'error' => ['bg' => 'bg-danger-subtle', 'text' => 'text-danger'],
    ];

    $colors = $statusColors[$status] ?? ['bg' => 'bg-surface-inset', 'text' => 'text-text-primary'];
    $displayText = str_replace('_', ' ', ucfirst($status));
@endphp

<span class="inline-block px-2 py-1 rounded text-caption font-medium {{ $colors['bg'] }} {{ $colors['text'] }}">
    {{ $displayText }}
</span>
