<div>
    <h1 class="text-display mb-8">Create Campaign</h1>

    <form wire:submit="sendCampaign" class="space-y-6 max-w-4xl">
        <!-- Basic Info -->
        <div class="border border-border rounded-lg p-6">
            <h3 class="text-heading mb-4">Campaign Details</h3>

            <div class="space-y-4">
                <div>
                    <label class="text-label block mb-2">Campaign Name</label>
                    <input type="text" wire:model="campaignName" placeholder="e.g., Q4 Backlink Outreach" class="w-full px-4 py-2 border border-border rounded-lg" />
                </div>

                <div>
                    <label class="text-label block mb-2">Project</label>
                    <select wire:model="projectId" class="w-full px-4 py-2 border border-border rounded-lg">
                        <option value="">Select a project...</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-label block mb-2">Email Inbox</label>
                    <select wire:model="inboxId" class="w-full px-4 py-2 border border-border rounded-lg">
                        <option value="">Select an inbox...</option>
                        @foreach($inboxes as $inbox)
                            <option value="{{ $inbox->id }}">{{ $inbox->name }} ({{ $inbox->email_address }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-label block mb-2">Personalization</label>
                    <div class="space-y-2">
                        <label>
                            <input type="radio" wire:model="personalizationMode" value="static" />
                            <span class="text-body ml-2">Static - Same email to all</span>
                        </label>
                        <label>
                            <input type="radio" wire:model="personalizationMode" value="ai_generated" />
                            <span class="text-body ml-2">AI Generated - Personalized per prospect</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sequence Steps -->
        <div class="border border-border rounded-lg p-6">
            <h3 class="text-heading mb-4">Email Sequence</h3>

            @foreach($steps as $index => $step)
                <div class="border border-border rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-body font-semibold">Step {{ $step['number'] }}</h4>
                        @if($index > 0)
                            <button type="button" wire:click="removeStep({{ $index }})" class="text-danger text-caption hover:underline">Remove</button>
                        @endif
                    </div>

                    @if($step['number'] > 1)
                        <div class="mb-4">
                            <label class="text-label block mb-2">Send after {{ $step['delay_days'] }} days</label>
                            <input type="number" wire:model="steps.{{ $index }}.delay_days" min="0" class="w-full px-4 py-2 border border-border rounded-lg" />
                        </div>

                        <div class="mb-4">
                            <label class="text-label block mb-2">Send if</label>
                            <select wire:model="steps.{{ $index }}.send_if" class="w-full px-4 py-2 border border-border rounded-lg">
                                <option value="always">Always</option>
                                <option value="no_reply">No reply received</option>
                            </select>
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="text-label block mb-2">Subject</label>
                        <input type="text" wire:model="steps.{{ $index }}.subject" placeholder="Email subject line" class="w-full px-4 py-2 border border-border rounded-lg" />
                    </div>

                    <div class="mb-4">
                        <label class="text-label block mb-2">Body</label>
                        <textarea wire:model="steps.{{ $index }}.body" placeholder="Email body - use {{first_name}}, {{email}}, {{company}}, {{ai_intro}} as tokens" rows="6" class="w-full px-4 py-2 border border-border rounded-lg font-mono text-13px"></textarea>
                    </div>
                </div>
            @endforeach

            <button type="button" wire:click="addStep" class="px-4 py-2 border border-border rounded-lg text-body hover:bg-surface-inset">
                + Add Follow-up Step
            </button>
        </div>

        <!-- Prospect Selection -->
        @if($projectId && $prospects->count() > 0)
            <div class="border border-border rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-heading">Select Prospects ({{ $selectedCount }} selected)</h3>
                    <button type="button" wire:click="selectAllProspects" class="text-accent text-caption hover:underline">Select all</button>
                </div>

                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @foreach($prospects as $prospect)
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="selectedProspectIds" value="{{ $prospect->id }}" />
                            <span class="ml-3 text-body">{{ $prospect->contact_email }}</span>
                            @if($prospect->contact_name)
                                <span class="ml-2 text-caption">({{ $prospect->contact_name }})</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Submit -->
        <div class="flex gap-4">
            <button type="submit" class="flex-1 px-4 py-2 bg-accent text-white rounded-lg text-body hover:opacity-90">
                Send Campaign
            </button>
            <a href="{{ route('dashboard') }}" wire:navigate class="flex-1 px-4 py-2 border border-border rounded-lg text-body hover:bg-surface-inset text-center">
                Cancel
            </a>
        </div>
    </form>
</div>
