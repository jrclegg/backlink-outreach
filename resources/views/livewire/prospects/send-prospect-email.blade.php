<div>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold">Send Email</h2>
                <p class="text-text-muted text-sm mt-1">To: {{ $prospect->site_url }}</p>
            </div>
            <a href="{{ route('prospects.index') }}" wire:navigate class="px-4 py-2 text-text-muted hover:text-text-primary transition-colors">
                ← Back
            </a>
        </div>

        <!-- Form -->
        <div class="bg-surface-inset rounded-lg p-6 border border-border max-w-3xl">
            <form wire:submit="send" class="space-y-6">
                <!-- Email Inbox Selection -->
                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">Send From *</label>
                    <select
                        wire:model="inboxId"
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        :disabled="sending"
                    >
                        <option value="">Select an inbox</option>
                        @foreach($inboxes as $inbox)
                            <option value="{{ $inbox->id }}">{{ $inbox->email_address }}</option>
                        @endforeach
                    </select>
                    @error('inboxId')
                        <p class="text-danger text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- To Email -->
                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">To *</label>
                    <input
                        type="email"
                        wire:model="toEmail"
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        :disabled="sending"
                    />
                    @error('toEmail')
                        <p class="text-danger text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Subject -->
                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">Subject *</label>
                    <input
                        type="text"
                        wire:model="subject"
                        placeholder="e.g., Guest Post Opportunity for {{ $prospect->site_url }}"
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        :disabled="sending"
                    />
                    @error('subject')
                        <p class="text-danger text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email Body -->
                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">Message *</label>
                    <textarea
                        wire:model="body"
                        rows="12"
                        placeholder="Your email message..."
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary font-mono text-sm"
                        :disabled="sending"
                    ></textarea>
                    <p class="text-text-muted text-xs mt-1">{{ strlen($body) }} characters</p>
                    @error('body')
                        <p class="text-danger text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Messages -->
                @if($successMessage)
                    <div class="px-4 py-3 bg-success-subtle border border-success text-success-dark rounded-lg text-sm">
                        ✓ {{ $successMessage }}
                    </div>
                @endif

                @if($errorMessage)
                    <div class="px-4 py-3 bg-danger-subtle border border-danger text-danger-dark rounded-lg text-sm">
                        ✗ {{ $errorMessage }}
                    </div>
                @endif

                <!-- Form Actions -->
                <div class="flex gap-3 pt-4">
                    <button
                        type="submit"
                        :disabled="sending"
                        class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        @if($sending)
                            <span>Sending...</span>
                        @else
                            Send Email
                        @endif
                    </button>
                    <a
                        href="{{ route('prospects.index') }}"
                        wire:navigate
                        class="px-6 py-2 border border-border text-text-primary rounded-lg hover:bg-surface-inset transition-colors font-medium"
                    >
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Info -->
        <div class="bg-blue-subtle border border-blue rounded-lg p-4 text-sm text-blue-dark">
            <strong>Note:</strong> This email will be tracked for opens and clicks. Replies will appear in your inbox automatically.
        </div>
    </div>
</div>
