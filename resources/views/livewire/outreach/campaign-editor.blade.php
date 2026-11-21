@php
    $section = 'grid gap-x-10 gap-y-5 py-8 first:pt-0 border-t border-border first:border-t-0 lg:grid-cols-3';
@endphp
<div class="page max-w-5xl">
    <header class="space-y-3">
        <a href="{{ $campaignId ? route('outreach.show', $campaignId) : route('outreach.index') }}" wire:navigate class="crumb"><x-icon name="arrow-left" class="size-3.5" />{{ $campaignId ? 'Back to campaign' : 'AI campaigns' }}</a>
        <div>
            <h1 class="page-title">{{ $campaignId ? 'Edit campaign' : 'New campaign' }}</h1>
            <p class="page-subtitle">Describe what you want in plain English. AI turns it into a brief you can check. Nothing sends until you switch it on.</p>
        </div>
    </header>
    @if($errors->any())<div role="alert" class="alert alert-error"><div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif

    <div class="panel p-6 sm:p-8">
        <section class="{{ $section }}">
            <div>
                <p class="section-title">Who it's for</p>
                <p class="mt-1 text-sm text-zinc-500">The project or client, and the email account it sends from.</p>
            </div>
            <div class="space-y-4 lg:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="label">Project or client
                        <select wire:model.live="projectId" class="field" @disabled($campaignId)>
                            <option value="">Choose…</option>
                            @foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}{{ $project->client_name ? ' — '.$project->client_name : '' }}</option>@endforeach
                            <option value="0">+ New project or client</option>
                        </select>
                    </label>
                    <label class="label">Send from
                        <select wire:model="inboxId" class="field">
                            <option value="">Choose an account…</option>
                            @foreach($inboxes as $inbox)<option value="{{ $inbox->id }}">{{ $inbox->sender_address }}</option>@endforeach
                        </select>
                        <span class="hint block">Not listed? <a href="{{ route('inboxes.create') }}" wire:navigate class="link">Connect an account</a></span>
                    </label>
                </div>
                @if($newProject)
                    <div class="inset space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="label">Name<input wire:model="newProjectName" class="field" placeholder="Beat Darwin"></label>
                            <label class="label">Website<input type="url" wire:model="newProjectUrl" class="field" placeholder="https://example.com"></label>
                        </div>
                        <label class="label">Client name <span class="font-normal text-zinc-500">(leave blank if it's yours)</span><input wire:model="newClientName" class="field" placeholder="e.g. Smith Recruitment"></label>
                        <p class="text-xs text-zinc-500">A client's campaigns keep their own contact history, separate from yours. Opt-outs apply everywhere.</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="{{ $section }}">
            <div>
                <p class="section-title">Describe it</p>
                <p class="mt-1 text-sm text-zinc-500">Who to contact, what you're offering or asking for, any prices or facts the emails may use, and anything to avoid.</p>
            </div>
            <div class="space-y-4 lg:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="label">Goal<select wire:model="goal" class="field">@foreach($goals as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                    <label class="label">Link to include <span class="font-normal text-zinc-500">(optional)</span><input type="url" wire:model="link" class="field" placeholder="https://"></label>
                </div>
                <label class="label">In your own words
                    <textarea wire:model="description" rows="6" class="field" placeholder="e.g. This is Beat Darwin, a free evolution quiz for students. Find biology teaching blogs, university outreach pages and science resource lists that would link to our quiz page. Never offer payment for links."></textarea>
                </label>
                <div class="flex flex-wrap items-center gap-3">
                    <button wire:click="writeBrief" wire:loading.attr="disabled" class="btn-primary"><x-icon name="sparkles" />{{ $hasBrief ? 'Rewrite the brief' : 'Write the brief' }}</button>
                    <span wire:loading wire:target="writeBrief" class="text-sm text-zinc-500">Reading your website and writing the brief. This can take a minute…</span>
                </div>
            </div>
        </section>

        @if($hasBrief)
            <form wire:submit="save" id="brief" class="border-t border-border pt-8">
                <section class="{{ $section }}">
                    <div>
                        <p class="section-title">The brief</p>
                        <p class="mt-1 text-sm text-zinc-500">What the AI researches and writes from. Edit anything that's off.</p>
                    </div>
                    <div class="space-y-4 lg:col-span-2">
                        @if($questions)
                            <div class="alert alert-warning"><div><p class="font-medium">Worth confirming before you switch it on</p><ul class="mt-1 list-disc space-y-0.5 pl-4">@foreach($questions as $q)<li>{{ $q }}</li>@endforeach</ul></div></div>
                        @endif
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="label">Campaign name<input wire:model="name" class="field"></label>
                            <label class="label">Goal<select wire:model="goal" class="field">@foreach($goals as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                        </div>
                        <label class="label">Who to target<textarea wire:model="audience" rows="3" class="field"></textarea></label>
                        <label class="label">Offer or ask <span class="font-normal text-zinc-500">· the only facts emails may use</span><textarea wire:model="offer" rows="5" class="field"></textarea></label>
                        <label class="label">Link every email includes<input type="url" wire:model.live.debounce.500ms="productUrl" class="field">@if($productUrl && !$validLink)<span class="hint block text-rose-700">Enter a full https:// link.</span>@endif</label>
                        <label class="label">Who the sender is<textarea wire:model="sender" rows="2" class="field"></textarea></label>
                    </div>
                </section>

                <section class="{{ $section }}">
                    <div>
                        <p class="section-title">Targeting</p>
                        <p class="mt-1 text-sm text-zinc-500">Search focuses rotate daily so it keeps finding new prospects.</p>
                    </div>
                    <div class="space-y-4 lg:col-span-2">
                        <label class="label">Search focuses <span class="font-normal text-zinc-500">· one per line</span><textarea wire:model="focuses" rows="6" class="field"></textarea></label>
                        <label class="label">How to judge fit, and what to skip<textarea wire:model="fitRules" rows="4" class="field"></textarea></label>
                        <label class="label">Never contact <span class="font-normal text-zinc-500">· one domain per line</span><textarea wire:model="excludedDomains" rows="3" class="field"></textarea></label>
                    </div>
                </section>

                <section class="{{ $section }}">
                    <div>
                        <p class="section-title">Writing</p>
                        <p class="mt-1 text-sm text-zinc-500">Every draft is checked against these rules before it can send.</p>
                    </div>
                    <div class="space-y-4 lg:col-span-2">
                        <label class="label">Must include, or must never say<textarea wire:model="emailRules" rows="4" class="field"></textarea></label>
                        <label class="label">Example emails in your voice <span class="font-normal text-zinc-500">(optional)</span><textarea wire:model="examples" rows="4" class="field"></textarea></label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="label">Min words<input type="number" wire:model="minWords" class="field"></label>
                            <label class="label">Max words<input type="number" wire:model="maxWords" class="field"></label>
                        </div>
                    </div>
                </section>

                <section class="{{ $section }}">
                    <div>
                        <p class="section-title">Schedule</p>
                        <p class="mt-1 text-sm text-zinc-500">The daily limit counts emails sent, per campaign.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4 lg:col-span-2">
                        <label class="label">Emails per day<input type="number" min="1" max="50" wire:model="dailyLimit" class="field"></label>
                        <label class="label">Start time (London)<input type="time" wire:model="sendTime" class="field"></label>
                    </div>
                </section>
            </form>
        @endif
    </div>

    @if($hasBrief)
        <div class="sticky bottom-0 -mx-4 flex flex-wrap items-center justify-end gap-3 border-t border-border bg-canvas/90 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-xl sm:border sm:bg-white/90">
            <p class="mr-auto text-xs text-zinc-500">{{ $campaignId ? 'Changes apply from the next draft.' : 'Saved switched off. Test it in approval mode next.' }}</p>
            <a href="{{ $campaignId ? route('outreach.show', $campaignId) : route('outreach.index') }}" wire:navigate class="btn-ghost">Cancel</a>
            <button form="brief" class="btn-primary" wire:loading.attr="disabled" wire:target="save">Save campaign</button>
        </div>
    @endif
</div>
