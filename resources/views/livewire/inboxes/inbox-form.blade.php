<div class="max-w-2xl space-y-6">
    <header><a href="{{ route('inboxes.index') }}" wire:navigate class="text-sm text-teal-700">← Email accounts</a><h1 class="text-display mt-3">{{ $inboxId ? 'Manage email account' : 'Connect your email' }}</h1><p class="text-caption mt-2">Your email and project, together. We handle the connection settings.</p></header>
    @if($notice)<p role="status" class="panel bg-teal-50">{{ $notice }}</p>@endif
    @if($errors->any())<div role="alert" class="panel text-rose-700 text-sm">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($step === 'email')
        <form wire:submit="identify" class="panel space-y-5">
            <label class="block text-sm font-medium" for="connect-email">What’s your email address?</label>
            <input id="connect-email" type="email" wire:model="email_address" autocomplete="email" class="field" placeholder="you@yourwebsite.com" required autofocus>
            <p class="text-caption">Works with IONOS, Gmail, Yahoo and Zoho, including supported custom-domain accounts.</p>
            <button class="btn-primary" wire:loading.attr="disabled" wire:target="identify"><span wire:loading.remove wire:target="identify">Continue</span><span wire:loading wire:target="identify">Finding your provider…</span></button>
        </form>
    @else
        <form wire:submit="save" class="panel space-y-5">
            <div class="flex items-start justify-between gap-3"><div><p class="font-medium break-all">{{ $email_address }}</p>@if($provider !== 'custom')<p class="text-caption mt-1">{{ $providers[$provider]['label'] ?? 'Email provider' }} · Connection settings ready</p>@endif</div>@if(!$inboxId)<button type="button" wire:click="changeEmail" class="text-sm text-teal-700">Change</button>@endif</div>
            @if($provider === 'custom' && !$manualMode)
                <div class="rounded-lg bg-zinc-50 p-4 space-y-3"><p class="text-sm">We couldn’t identify your email provider automatically. Who do you sign in with?</p>
                    <label class="block text-sm">Email provider<select wire:model.live="provider" class="field mt-1"><option value="custom">Choose your provider</option>@foreach($providers as $key=>$value)@if($key !== 'custom')<option value="{{ $key }}">{{ $value['label'] }}</option>@endif @endforeach</select></label>
                    <button type="button" wire:click="$set('manualMode', true)" class="text-sm text-teal-700">My provider isn’t listed</button>
                </div>
            @else
                <details wire:ignore.self><summary class="text-sm cursor-pointer text-teal-700">Different provider?</summary><label class="block text-sm mt-3">Email provider<select aria-label="Email provider" wire:model.live="provider" class="field mt-1">@foreach($providers as $key=>$value)<option value="{{ $key }}">{{ $value['label'] }}</option>@endforeach</select></label></details>
            @endif
            @if($provider !== 'custom' || $manualMode)
                <div class="space-y-2"><label for="connect-password" class="block text-sm font-medium">{{ $providerHelp['label'] }}</label>
                    <input id="connect-password" type="password" wire:model="password" autocomplete="new-password" class="field" placeholder="{{ $inboxId ? 'Leave blank to keep your saved password' : '' }}" @required(!$inboxId)>
                    <p class="text-caption">{{ $providerHelp['instruction'] }}</p>
                    @if($providerHelp['url'])<a href="{{ $providerHelp['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-block text-sm text-teal-700 underline">{{ in_array($provider,['gmail','yahoo']) ? 'Create an app password' : 'Help with Zoho access' }} ↗</a>@endif
                </div>
                <fieldset class="space-y-2"><legend class="text-sm font-medium mb-2">Which projects should use this email?</legend>@forelse($projects as $project)<label class="flex gap-2 text-sm"><input type="checkbox" wire:model.live="projectIds" value="{{ $project->id }}">{{ $project->name }}</label>@empty<p class="text-caption">You can assign a project later. New messages will appear in Unassigned mail.</p>@endforelse</fieldset>
                @if(count($projectIds) > 1)<label class="block text-sm">Put new conversations in<select wire:model="defaultProjectId" class="field mt-1"><option value="">Unassigned mail — I’ll choose later</option>@foreach($projects->whereIn('id',$projectIds) as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></label>@endif
                <label class="block text-sm">Your name on outgoing emails<input wire:model="from_name" autocomplete="name" class="field mt-1" required></label>
                <label class="block text-sm">Send as address (optional)<input type="email" wire:model="from_address" class="field mt-1" placeholder="{{ $email_address }}"><span class="text-caption block mt-1">Use an alias your provider allows you to send from. Leave blank to send from {{ $email_address }}. Your mailbox login stays the same.</span></label>
                @if($provider === 'yahoo')<p class="text-caption">Set up and verify your send-only address in Yahoo first. Checking the connection verifies your login; it does not verify that Yahoo will deliver mail from the alias.</p>@endif
                @if($manualMode)<p class="text-caption">This provider needs manual settings. Only enter the settings supplied by your email provider.</p>@endif
                <details wire:ignore.self class="border-t border-border pt-4" @if($manualMode) open @endif wire:key="advanced-{{ (int) $manualMode }}"><summary class="text-sm cursor-pointer text-text-muted">Advanced options (optional)</summary>
                    <div class="space-y-4 mt-4"><label class="block text-sm">Account label<input wire:model="name" class="field mt-1"></label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="manualMode">Use custom server settings</label>
                        @if($manualMode)<div class="grid md:grid-cols-2 gap-4">
                            @foreach(['smtp_host'=>'SMTP host','imap_host'=>'IMAP host','smtp_port'=>'SMTP port','imap_port'=>'IMAP port'] as $field=>$label)<label class="text-sm">{{ $label }}<input wire:model="{{ $field }}" class="field mt-1"></label>@endforeach
                            <label class="text-sm">SMTP encryption<select wire:model="smtp_encryption" class="field mt-1"><option value="tls">STARTTLS (587)</option><option value="ssl">TLS (465)</option></select></label><label class="text-sm">IMAP encryption<select wire:model="imap_encryption" class="field mt-1"><option value="ssl">TLS (993)</option><option value="tls">STARTTLS (143)</option></select></label>
                        </div>@endif
                        <div class="grid md:grid-cols-2 gap-4">@foreach(['smtp_username'=>'Sending username (defaults to email)','imap_username'=>'Receiving username (defaults to email)','imap_folder'=>'Incoming folder'] as $field=>$label)<label class="text-sm">{{ $label }}<input wire:model="{{ $field }}" class="field mt-1"></label>@endforeach
                        <label class="text-sm">Separate receiving password (optional)<input type="password" wire:model="imap_password" autocomplete="new-password" class="field mt-1"></label><label class="text-sm">Maximum sends per hour<input type="number" wire:model="max_sends_per_hour" min="1" max="200" class="field mt-1"></label><label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active">Account active</label></div>
                        @if($inboxId)<button type="button" wire:click="testConnection" wire:loading.attr="disabled" class="btn-secondary">Check connection</button>@endif
                    </div>
                </details>
                <button type="submit" wire:loading.attr="disabled" class="btn-primary"><span wire:loading.remove wire:target="save">{{ $inboxId ? 'Save changes' : 'Connect account' }}</span><span wire:loading wire:target="save">Checking sending and receiving…</span></button>
                <p class="text-caption">{{ $inboxId ? 'Changes to your connection are tested before saving.' : 'We’ll check sending and receiving, then start syncing your inbox.' }} No test email is sent.</p>
            @endif
        </form>
    @endif
</div>
