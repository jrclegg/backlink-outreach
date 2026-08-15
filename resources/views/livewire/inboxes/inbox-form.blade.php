<div>
    <div class="max-w-2xl">
        <h1 class="text-display mb-2">Connect Email Inbox</h1>
        <p class="text-caption mb-8">Set up SMTP for sending and IMAP for receiving replies</p>

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
        </form>
    </div>
</div>
