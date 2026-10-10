<div class="max-w-2xl space-y-6"><div><h1 class="text-display">AI settings</h1><p class="text-caption mt-2">{{ $usesCodex ? 'Codex' : 'Claude' }} researches opportunities on the live web and helps you write emails.</p></div>
@if($notice)<p role="status" class="panel bg-teal-50">{{ $notice }}</p>@endif
@if($usesCodex)
<section class="panel space-y-4">
    <p class="text-sm">Codex: <strong>{{ $configured ? 'Connected with ChatGPT' : 'Not connected' }}</strong></p>
    <p class="text-caption">Research and drafting use the Codex allowance of the ChatGPT account signed in on this machine. No API key is needed.</p>
    @unless($configured)<p class="text-sm">Run <code>codex login</code> in your terminal and sign in with ChatGPT. The app and research worker must run under the same computer account.</p>@endunless
    <button wire:click="save" wire:loading.attr="disabled" class="btn-secondary">Check connection</button>
</section>
@else
<form wire:submit="save" class="panel space-y-4">
<p class="text-sm">API key: <strong>{{ $configured ? 'Configured' : 'Not configured' }}</strong></p>
<label class="block text-sm">Anthropic API key<input type="password" wire:model="apiKey" autocomplete="new-password" class="field mt-1" placeholder="{{ $configured ? 'Leave blank to keep existing key' : 'Paste your API key' }}"></label>
<p class="text-caption">Stored encrypted. A Claude chat subscription does not provide API credits. Enable web search in your Anthropic account.</p>
<label class="block text-sm">Model<input wire:model="model" class="field mt-1" required></label>
@foreach($errors->all() as $error)<p class="text-rose-700 text-sm">{{ $error }}</p>@endforeach
<div class="flex gap-2"><button class="btn-primary">Save settings</button>@if($configured)<button type="button" wire:click="removeKey" wire:confirm="Remove the saved API key?" class="btn-secondary">Remove key</button>@endif</div>
</form>
@endif
<p class="text-caption">Research sends the target page content and project brief to {{ $usesCodex ? 'OpenAI through Codex' : 'Anthropic' }}. Drafting sends relevant conversation content. AI runs only when you request research or drafting.</p></div>
