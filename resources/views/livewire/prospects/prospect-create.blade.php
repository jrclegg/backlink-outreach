<div>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold">Add Prospect</h2>
                <p class="text-text-muted text-sm mt-1">Manually add a new prospect to your outreach list</p>
            </div>
            <a href="{{ route('prospects.index') }}" wire:navigate class="px-4 py-2 text-text-muted hover:text-text-primary transition-colors">
                ← Back to Prospects
            </a>
        </div>

        <!-- Form -->
        <div class="bg-surface-inset rounded-lg p-6 border border-border max-w-2xl">
            <form wire:submit="save" class="space-y-6">
                <!-- Project Selection -->
                @if($projects->count() > 1)
                    <div>
                        <label class="block text-sm font-medium text-text-primary mb-2">Project</label>
                        <select
                            wire:model="projectId"
                            class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                            <option value="">Select a project</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                        @error('projectId')
                            <p class="text-danger text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <!-- Website URL -->
                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">Website URL *</label>
                    <input
                        type="url"
                        wire:model="siteUrl"
                        placeholder="https://example.com"
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                    <p class="text-text-muted text-xs mt-1">The domain of the site with the backlink</p>
                    @error('siteUrl')
                        <p class="text-danger text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Contact Name -->
                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">Contact Name</label>
                    <input
                        type="text"
                        wire:model="contactName"
                        placeholder="e.g., John Smith"
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                    @error('contactName')
                        <p class="text-danger text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Contact Email -->
                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">Contact Email</label>
                    <input
                        type="email"
                        wire:model="contactEmail"
                        placeholder="contact@example.com"
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                    <p class="text-text-muted text-xs mt-1">Optional — you can leave blank if you need to find it later or will submit a contact form</p>
                    @error('contactEmail')
                        <p class="text-danger text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Niche -->
                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">Niche / Topic</label>
                    <input
                        type="text"
                        wire:model="niche"
                        placeholder="e.g., SaaS, Technology, Marketing"
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                    @error('niche')
                        <p class="text-danger text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-text-primary mb-2">Notes</label>
                    <textarea
                        wire:model="notes"
                        placeholder="Add any notes about this prospect (e.g., referring page, context, etc.)"
                        rows="4"
                        class="w-full px-3 py-2 border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                    ></textarea>
                    @error('notes')
                        <p class="text-danger text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Form Actions -->
                <div class="flex gap-3 pt-4">
                    <button
                        type="submit"
                        class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors font-medium"
                    >
                        Add Prospect
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
        <div class="bg-blue-subtle border border-blue rounded-lg p-4 text-sm text-blue-dark space-y-2">
            <div><strong>Tips:</strong></div>
            <ul class="list-disc list-inside space-y-1">
                <li>Email is optional — you can add notes about where to find the contact info</li>
                <li>If you need to submit a contact form to get their email, add that detail in the notes field</li>
                <li>You can also import multiple prospects at once using CSV import, or discover them automatically with backlink search</li>
            </ul>
        </div>
    </div>
</div>
