@props(['label', 'action', 'pendingLabel' => null, 'successMessage' => null, 'errorMessage' => null])

@php
    $pendingLabel = $pendingLabel ?? $label . '…';
@endphp

<div x-data="asyncAction({ action: '{{ $action }}', successMessage: '{{ $successMessage }}', errorMessage: '{{ $errorMessage }}' })" class="space-y-3">
    <button
        @click="execute()"
        :disabled="loading"
        class="px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity disabled:opacity-50"
        x-text="loading ? '{{ $pendingLabel }}' : '{{ $label }}'"
    >
        {{ $label }}
    </button>

    @if($successMessage)
        <div x-show="success" class="px-4 py-3 bg-success-subtle text-success rounded-lg text-caption">
            {{ $successMessage }}
        </div>
    @endif

    @if($errorMessage)
        <div x-show="error" class="px-4 py-3 bg-danger-subtle text-danger rounded-lg text-caption">
            <span x-text="errorMessage"></span>
        </div>
    @endif
</div>

<script>
function asyncAction(config) {
    return {
        loading: false,
        success: false,
        error: false,
        errorMessage: config.errorMessage,
        action: config.action,
        successMessage: config.successMessage,

        async execute() {
            this.loading = true;
            this.success = false;
            this.error = false;

            try {
                await Livewire.dispatch(this.action);
                if (this.successMessage) {
                    this.success = true;
                    setTimeout(() => { this.success = false; }, 3000);
                }
            } catch (e) {
                this.error = true;
                this.errorMessage = e.message || 'An error occurred';
                setTimeout(() => { this.error = false; }, 5000);
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
