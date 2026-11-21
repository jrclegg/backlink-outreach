<div class="page max-w-2xl">
    <header>
        <h1 class="page-title">AI settings</h1>
        <p class="page-subtitle">{{ $usesCodex ? 'Codex' : 'Claude' }} researches prospects on the live web and writes your emails.</p>
    </header>
    @if($notice)<div role="status" class="alert alert-success">{{ $notice }}</div>@endif

    @if($usesCodex)
        <section class="panel space-y-4">
            <div class="flex items-center justify-between gap-3"><p class="section-title">Codex</p><span class="badge {{ $configured ? 'badge-green' : 'badge-red' }}">{{ $configured ? 'Connected with ChatGPT' : 'Not connected' }}</span></div>
            <p class="text-sm text-zinc-600">Research and drafting use the Codex allowance of the ChatGPT account signed in on this machine. No API key is needed.</p>
            @unless($configured)<div class="inset">Run <code class="rounded bg-white px-1.5 py-0.5 text-xs ring-1 ring-border">codex login</code> in your terminal and sign in with ChatGPT. The app and research worker must run under the same computer account.</div>@endunless
            <button wire:click="save" wire:loading.attr="disabled" class="btn-secondary"><x-icon name="refresh" class="size-3.5" />Check connection</button>
        </section>
    @else
        <form wire:submit="save" class="panel space-y-4">
            <div class="flex items-center justify-between gap-3"><p class="section-title">Anthropic API</p><span class="badge {{ $configured ? 'badge-green' : 'badge-zinc' }}">{{ $configured ? 'Configured' : 'Not configured' }}</span></div>
            <label class="label">API key<input type="password" wire:model="apiKey" autocomplete="new-password" class="field" placeholder="{{ $configured ? 'Leave blank to keep the existing key' : 'Paste your API key' }}"><span class="hint block">Stored encrypted. A Claude chat subscription does not include API credits. Enable web search in your Anthropic account.</span></label>
            <label class="label">Model<input wire:model="model" class="field" required></label>
            @foreach($errors->all() as $error)<p class="text-sm text-rose-700">{{ $error }}</p>@endforeach
            <div class="flex justify-end gap-2">@if($configured)<button type="button" wire:click="removeKey" wire:confirm="Remove the saved API key?" class="btn-ghost">Remove key</button>@endif<button class="btn-primary">Save settings</button></div>
        </form>
    @endif
    <p class="text-xs text-zinc-500">Research sends the campaign brief and prospect pages to {{ $usesCodex ? 'OpenAI through Codex' : 'Anthropic' }}. Drafting sends relevant conversation content.</p>
</div>
