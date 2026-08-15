@props(['icon' => null, 'title', 'description', 'action' => null, 'actionLabel' => null, 'actionUrl' => null])

<div class="flex flex-col items-center justify-center py-12 px-6">
    @if($icon)
        <div class="mb-4 text-5xl">
            {{ $icon }}
        </div>
    @endif

    <h3 class="text-heading mb-2">{{ $title }}</h3>

    <p class="text-caption mb-6 max-w-sm text-center">
        {{ $description }}
    </p>

    @if($action || $actionLabel)
        @if($actionUrl)
            <a href="{{ $actionUrl }}" wire:navigate class="px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity">
                {{ $actionLabel ?? 'Get Started' }}
            </a>
        @elseif($action)
            {{ $action }}
        @endif
    @endif
</div>
