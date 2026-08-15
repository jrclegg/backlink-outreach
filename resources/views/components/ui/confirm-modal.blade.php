@props(['title', 'message', 'confirmLabel' => 'Confirm', 'cancelLabel' => 'Cancel', 'action' => null, 'isOpen' => false])

<flux:modal :show="$isOpen" maxWidth="md">
    <flux:heading size="lg" class="mb-4">{{ $title }}</flux:heading>

    <div class="text-body text-text-primary mb-6">
        {{ $message }}
    </div>

    <div class="flex gap-3 justify-end">
        <flux:button variant="ghost" @click="show = false">
            {{ $cancelLabel }}
        </flux:button>

        @if($action)
            <flux:button wire:click="{{ $action }}" variant="primary">
                {{ $confirmLabel }}
            </flux:button>
        @else
            {{ $slot }}
        @endif
    </div>
</flux:modal>
