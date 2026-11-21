<div class="space-y-6">
    <header>
        <a href="{{ $campaignId ? route('outreach.show', $campaignId) : route('outreach.index') }}" wire:navigate class="text-sm text-teal-700">← {{ $campaignId ? 'Back to campaign' : 'All campaigns' }}</a>
        <h1 class="text-display mt-2">{{ $campaignId ? 'Edit campaign' : 'New campaign' }}</h1>
        <p class="text-caption">Describe what you want in plain English. AI turns it into a brief you can check and edit. Nothing is sent until you switch the campaign on.</p>
    </header>
    @if($errors->any())<div role="alert" class="rounded-lg bg-rose-50 p-3 text-rose-800 text-sm">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section class="panel space-y-4">
        <h2 class="text-heading">1. Who is it for?</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <label class="block text-sm">Project or client
                <select wire:model.live="projectId" class="field mt-1" @disabled($campaignId)>
                    <option value="">Choose a project</option>
                    @foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}{{ $project->client_name ? ' (client: '.$project->client_name.')' : '' }}</option>@endforeach
                    <option value="0">+ New project or client…</option>
                </select>
            </label>
            <label class="block text-sm">Send from
                <select wire:model="inboxId" class="field mt-1">
                    <option value="">Choose an email account</option>
                    @foreach($inboxes as $inbox)<option value="{{ $inbox->id }}">{{ $inbox->sender_address }}{{ $inbox->from_name ? ' ('.$inbox->from_name.')' : '' }}</option>@endforeach
                </select>
                <span class="text-caption">Missing? <a href="{{ route('inboxes.create') }}" wire:navigate class="text-teal-700 underline">Connect an email account</a>.</span>
            </label>
        </div>
        @if($newProject)
            <div class="grid md:grid-cols-3 gap-4 border-t border-border pt-4">
                <label class="block text-sm">Name<input wire:model="newProjectName" class="field mt-1" placeholder="Beat Darwin"></label>
                <label class="block text-sm">Website<input type="url" wire:model="newProjectUrl" class="field mt-1" placeholder="https://example.com"></label>
                <label class="block text-sm">Client name (blank if it's yours)<input wire:model="newClientName" class="field mt-1" placeholder="e.g. Smith Recruitment"></label>
                <p class="text-caption md:col-span-3">A client's campaigns keep their own contact history: their prospects don't block yours, and yours don't block theirs. Opt-outs apply everywhere.</p>
            </div>
        @endif
    </section>

    <section class="panel space-y-4">
        <h2 class="text-heading">2. Describe the campaign</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <label class="block text-sm">Goal
                <select wire:model="goal" class="field mt-1">@foreach($goals as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
            </label>
            <label class="block text-sm">Link every email should include (optional)
                <input type="url" wire:model="link" class="field mt-1" placeholder="The page to link to, or the product page">
            </label>
        </div>
        <label class="block text-sm">In your own words
            <textarea wire:model="description" rows="7" class="field mt-1" placeholder="e.g. This is Beat Darwin, a free evolution quiz for students. Find biology teaching blogs, university outreach pages and science resource lists that would link to our quiz page. Send from my Beat Darwin email. Never offer payment for links."></textarea>
        </label>
        <p class="text-caption">Include who to contact, what you're offering or asking for, any prices or facts the emails may use, and anything to avoid. AI only uses facts you give it or that are on your website.</p>
        <button wire:click="writeBrief" wire:loading.attr="disabled" class="btn-primary">{{ $hasBrief ? 'Rewrite the brief from this' : 'Write the brief' }}</button>
        <span wire:loading wire:target="writeBrief" class="text-caption">Reading your website and writing the brief… this can take a minute.</span>
    </section>

    @if($hasBrief)
        <form wire:submit="save" class="panel space-y-4">
            <h2 class="text-heading">3. Check the brief</h2>
            @if($questions)
                <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm"><p class="font-semibold">Worth confirming before you switch it on</p><ul class="list-disc ml-5 mt-1">@foreach($questions as $q)<li>{{ $q }}</li>@endforeach</ul></div>
            @endif
            <div class="grid md:grid-cols-2 gap-4">
                <label class="block text-sm">Campaign name<input wire:model="name" class="field mt-1"></label>
                <label class="block text-sm">Goal<select wire:model="goal" class="field mt-1">@foreach($goals as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            </div>
            <label class="block text-sm">Who to target<textarea wire:model="audience" rows="3" class="field mt-1"></textarea></label>
            <label class="block text-sm">The offer or ask: the facts emails may use<textarea wire:model="offer" rows="5" class="field mt-1"></textarea></label>
            <label class="block text-sm">Link every email includes<input type="url" wire:model.live.debounce.500ms="productUrl" class="field mt-1">@if($productUrl && !$validLink)<span class="text-rose-700 text-xs">Enter a full https:// link.</span>@endif</label>
            <label class="block text-sm">Who the sender is<textarea wire:model="sender" rows="2" class="field mt-1"></textarea></label>
            <div class="grid md:grid-cols-2 gap-4">
                <label class="block text-sm">Search focuses (one per line, rotated daily)<textarea wire:model="focuses" rows="7" class="field mt-1"></textarea></label>
                <label class="block text-sm">How to judge fit, and what to skip<textarea wire:model="fitRules" rows="7" class="field mt-1"></textarea></label>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <label class="block text-sm">Email rules: must include / must never say<textarea wire:model="emailRules" rows="5" class="field mt-1"></textarea></label>
                <label class="block text-sm">Never contact these domains (one per line)<textarea wire:model="excludedDomains" rows="5" class="field mt-1"></textarea></label>
            </div>
            <label class="block text-sm">Example emails in your voice (optional)<textarea wire:model="examples" rows="4" class="field mt-1"></textarea></label>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <label class="block text-sm">Min words<input type="number" wire:model="minWords" class="field mt-1"></label>
                <label class="block text-sm">Max words<input type="number" wire:model="maxWords" class="field mt-1"></label>
                <label class="block text-sm">Emails per day<input type="number" min="1" max="50" wire:model="dailyLimit" class="field mt-1"></label>
                <label class="block text-sm">Start time (London)<input type="time" wire:model="sendTime" class="field mt-1"></label>
            </div>
            <div class="flex flex-wrap gap-3 items-center">
                <button class="btn-primary" wire:loading.attr="disabled">Save campaign</button>
                <span class="text-caption">{{ $campaignId ? 'Changes apply from the next draft.' : 'Saved switched off. Turn on approval mode on the next page to test it.' }}</span>
            </div>
        </form>
    @endif
</div>
