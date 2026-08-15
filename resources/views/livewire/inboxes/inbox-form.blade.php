<div>
    <div class="max-w-2xl">
        <h1 class="text-display mb-2">Connect Email Inbox</h1>
        <p class="text-caption mb-8">Get started in seconds with one-click provider connections</p>

        @if($showOAuthOptions)
            <!-- OAuth Options Stage -->
            <div class="space-y-6">
                <!-- Basic Info Section -->
                <div class="border border-border rounded-lg p-6 space-y-4">
                    <h3 class="text-heading">Inbox Details</h3>

                    <div>
                        <label class="text-label block mb-2">Inbox Name</label>
                        <input type="text" wire:model="name" placeholder="e.g., Main Outreach" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                        @error('name') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-label block mb-2">From Name</label>
                        <input type="text" wire:model="from_name" placeholder="Your Name" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                        @error('from_name') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- OAuth Provider Selection -->
                <div class="border border-border rounded-lg p-6 space-y-4">
                    <h3 class="text-heading">Choose Email Provider</h3>
                    <p class="text-caption text-secondary mb-4">Select your email provider for instant setup. Your credentials are never stored on our servers.</p>

                    <div class="space-y-3">
                        <!-- Gmail Button -->
                        <button type="button" wire:click="connectWithOAuth('gmail')" class="w-full px-6 py-4 border-2 border-border rounded-lg hover:border-accent hover:bg-surface-inset transition-all text-left flex items-center gap-3">
                            <svg class="w-6 h-6 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20.4 6.5c.1.1.2.1.3.2v11.6c0 1.1-.9 2-2 2H5.3c-1.1 0-2-.9-2-2V6.7c0-.1.2-.2.3-.2h16.8zm-9 8.3l-6.4-5.1c-.2-.2-.3-.4-.3-.6 0-.5.4-.9.9-.9h11.6c.5 0 .9.4.9.9 0 .2-.1.4-.3.6l-6.4 5.1zm9-9.3H3.6c-.9 0-1.6.7-1.6 1.6v11.5c0 .9.7 1.6 1.6 1.6h16.8c.9 0 1.6-.7 1.6-1.6V6.5c0-.9-.7-1.6-1.6-1.6zm-8.5 6.8l6.4-5.1c.2-.2.3-.4.3-.6 0-.5-.4-.9-.9-.9H5.3c-.5 0-.9.4-.9.9 0 .2.1.4.3.6l6.4 5.1z"/>
                            </svg>
                            <div class="flex-1">
                                <div class="font-semibold">Connect Gmail</div>
                                <div class="text-caption text-secondary">One-click OAuth connection</div>
                            </div>
                            <svg class="w-5 h-5 text-secondary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>

                        <!-- Outlook Button -->
                        <button type="button" wire:click="connectWithOAuth('outlook')" class="w-full px-6 py-4 border-2 border-border rounded-lg hover:border-accent hover:bg-surface-inset transition-all text-left flex items-center gap-3">
                            <svg class="w-6 h-6 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M10.5 1.5H3c-.825 0-1.5.675-1.5 1.5v18c0 .825.675 1.5 1.5 1.5h18c.825 0 1.5-.675 1.5-1.5V10.5M10.5 1.5v9m0 0H1.5M10.5 10.5l10.5-9"/>
                            </svg>
                            <div class="flex-1">
                                <div class="font-semibold">Connect Outlook</div>
                                <div class="text-caption text-secondary">One-click OAuth connection</div>
                            </div>
                            <svg class="w-5 h-5 text-secondary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <!-- Manual Entry Toggle -->
                    <div class="mt-6 pt-6 border-t border-border">
                        <button type="button" wire:click="toggleManualForm" class="text-caption text-secondary hover:text-body transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6" />
                            </svg>
                            <span>Or manually configure SMTP/IMAP</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if($showManualForm)
            <form wire:submit="save" class="space-y-8">
                <!-- Basic Info Section -->
                <div class="border border-border rounded-lg p-6 space-y-4">
                    <h3 class="text-heading">Inbox Details</h3>

                    <div>
                        <label class="text-label block mb-2">Inbox Name</label>
                        <input type="text" wire:model="name" placeholder="e.g., Main Outreach" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                        @error('name') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-label block mb-2">Email Address</label>
                        <input type="email" wire:model="email_address" placeholder="hello@example.com" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                        @error('email_address') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-label block mb-2">From Name</label>
                        <input type="text" wire:model="from_name" placeholder="Your Name" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                        @error('from_name') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- SMTP Section (Stage 1) -->
                <div class="border border-border rounded-lg p-6 space-y-4">
                    <h3 class="text-heading">SMTP Configuration (Outgoing Mail)</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-label block mb-2">Host</label>
                            <input type="text" wire:model="smtp_host" placeholder="smtp.gmail.com" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                            @error('smtp_host') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-label block mb-2">Port</label>
                            <input type="number" wire:model="smtp_port" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                            @error('smtp_port') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-label block mb-2">Username</label>
                        <input type="text" wire:model="smtp_username" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                        @error('smtp_username') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-label block mb-2">Password</label>
                        <input type="password" wire:model="smtp_password" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                        @error('smtp_password') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="text-label block mb-2">Encryption</label>
                        <select wire:model="smtp_encryption" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent">
                            <option value="tls">TLS (Port 587)</option>
                            <option value="ssl">SSL (Port 465)</option>
                        </select>
                    </div>

                    <!-- Test SMTP Button -->
                    <button type="button" wire:click="testSmtp" :disabled="testing_smtp" class="w-full px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity disabled:opacity-50">
                        @if($testing_smtp)
                            <span>Testing connection…</span>
                        @else
                            Test SMTP Connection
                        @endif
                    </button>

                    <!-- SMTP Status -->
                    @if($smtp_tested)
                        <div class="px-4 py-3 bg-success-subtle text-success rounded-lg text-caption">
                            ✓ SMTP connection successful
                        </div>
                    @elseif($smtp_error)
                        <div class="px-4 py-3 bg-danger-subtle text-danger rounded-lg text-caption">
                            {{ $smtp_error }}
                        </div>
                    @endif
                </div>

                <!-- IMAP Section (Stage 2 - only visible after SMTP success) -->
                @if($smtp_tested)
                    <div class="border border-border rounded-lg p-6 space-y-4 animate-in fade-in">
                        <h3 class="text-heading">IMAP Configuration (Incoming Mail)</h3>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-label block mb-2">Host</label>
                                <input type="text" wire:model="imap_host" placeholder="imap.gmail.com" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                                @error('imap_host') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="text-label block mb-2">Port</label>
                                <input type="number" wire:model="imap_port" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                                @error('imap_port') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="text-label block mb-2">Username</label>
                            <input type="text" wire:model="imap_username" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                            @error('imap_username') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-label block mb-2">Password</label>
                            <input type="password" wire:model="imap_password" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                            @error('imap_password') <p class="text-danger text-caption mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-label block mb-2">Encryption</label>
                            <select wire:model="imap_encryption" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent">
                                <option value="ssl">SSL (Port 993)</option>
                                <option value="tls">TLS (Port 143)</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-label block mb-2">Folder</label>
                            <input type="text" wire:model="imap_folder" placeholder="INBOX" class="w-full px-4 py-2 border border-border rounded-lg text-body focus:outline-none focus:ring-2 focus:ring-accent" />
                        </div>

                        <!-- Test IMAP Button -->
                        <button type="button" wire:click="testImap" :disabled="testing_imap" class="w-full px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity disabled:opacity-50">
                            @if($testing_imap)
                                <span>Testing connection…</span>
                            @else
                                Test IMAP Connection
                            @endif
                        </button>

                        <!-- IMAP Status -->
                        @if($imap_tested)
                            <div class="px-4 py-3 bg-success-subtle text-success rounded-lg text-caption">
                                ✓ IMAP connection successful
                            </div>
                        @elseif($imap_error)
                            <div class="px-4 py-3 bg-danger-subtle text-danger rounded-lg text-caption">
                                {{ $imap_error }}
                            </div>
                        @endif
                    </div>

                    <!-- Send Test Email Section (Stage 3) -->
                    @if($imap_tested)
                        <div class="border border-border rounded-lg p-6 space-y-4 animate-in fade-in">
                            <h3 class="text-heading">Send Test Email</h3>
                            <p class="text-caption">Send a test email to verify everything is working correctly.</p>

                            <button type="button" wire:click="sendTestEmail" :disabled="testing_email" class="w-full px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity disabled:opacity-50">
                                @if($testing_email)
                                    <span>Sending…</span>
                                @else
                                    Send Test Email
                                @endif
                            </button>

                            <!-- Email Status -->
                            @if($email_sent)
                                <div class="px-4 py-3 bg-success-subtle text-success rounded-lg text-caption">
                                    ✓ Test email sent successfully! Check your inbox.
                                </div>
                            @elseif($email_error)
                                <div class="px-4 py-3 bg-danger-subtle text-danger rounded-lg text-caption">
                                    {{ $email_error }}
                                </div>
                            @endif
                        </div>

                        <!-- Save Button (only available after test email sent) -->
                        @if($email_sent)
                            <div class="flex gap-4">
                                <button type="submit" class="flex-1 px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90 transition-opacity">
                                    Save Inbox
                                </button>
                                <a href="{{ route('inboxes.index') }}" wire:navigate class="flex-1 px-4 py-2 border border-border rounded-lg text-body hover:bg-surface-inset transition-colors text-center">
                                    Cancel
                                </a>
                            </div>
                        @endif
                    @endif
                @endif

                <!-- Back to OAuth Options Button -->
                <div class="flex gap-4">
                    <button type="button" wire:click="toggleManualForm" class="flex-1 px-4 py-2 border border-border rounded-lg text-body hover:bg-surface-inset transition-colors">
                        Back to OAuth Options
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
