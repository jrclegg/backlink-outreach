<div>
    <div class="mb-8">
        <h1 class="text-display">Dashboard</h1>
        @if($organization)
            <p class="text-caption mt-2">{{ $organization->name }}</p>
        @endif
    </div>

    <!-- Key Metrics -->
    @if($stats)
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <!-- Total Sent -->
            <div class="border border-border rounded-lg p-4 bg-surface">
                <p class="text-caption text-text-muted mb-1">Total Sent</p>
                <p class="text-heading text-accent">{{ $stats['totalSent'] }}</p>
            </div>

            <!-- Open Rate -->
            <div class="border border-border rounded-lg p-4 bg-surface">
                <p class="text-caption text-text-muted mb-1">Open Rate</p>
                <p class="text-heading text-accent">{{ $stats['openRate'] }}%</p>
            </div>

            <!-- Reply Rate -->
            <div class="border border-border rounded-lg p-4 bg-surface">
                <p class="text-caption text-text-muted mb-1">Reply Rate</p>
                <p class="text-heading text-accent">{{ $stats['replyRate'] }}%</p>
            </div>

            <!-- Interested -->
            <div class="border border-border rounded-lg p-4 bg-surface">
                <p class="text-caption text-text-muted mb-1">Interested</p>
                <p class="text-heading text-success">{{ $stats['interestedCount'] }}</p>
            </div>

            <!-- Backlinks Placed -->
            <div class="border border-border rounded-lg p-4 bg-surface">
                <p class="text-caption text-text-muted mb-1">Backlinks</p>
                <p class="text-heading text-success">{{ $stats['backlinksPlaced'] }}</p>
            </div>
        </div>
    @endif

    <!-- Activity Feed & Project Summary -->
    @if($projects->count() > 0)
        <div class="grid md:grid-cols-3 gap-8 mb-8">
            <!-- Recent Activity -->
            <div class="md:col-span-2">
                <h2 class="text-heading mb-4">Recent Activity</h2>
                @if(count($activityFeed) > 0)
                    <div class="space-y-3">
                        @foreach($activityFeed as $activity)
                            <div class="border border-border rounded-lg p-4 bg-surface">
                                <div class="flex items-start gap-3">
                                    <span class="text-2xl">{{ $activity['icon'] }}</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-body font-medium">{{ $activity['title'] }}</p>
                                        <p class="text-caption text-text-muted mt-1 truncate">{{ $activity['description'] }}</p>
                                        <div class="flex items-center gap-2 mt-2">
                                            <p class="text-caption text-text-muted">{{ $activity['timestamp']?->format('M d, H:i') ?? '-' }}</p>
                                            @if($activity['intent'])
                                                <x-ui.status-pill :status="$activity['intent']" />
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="border border-border rounded-lg p-6 text-center bg-surface-muted">
                        <p class="text-caption text-text-muted">No recent activity</p>
                    </div>
                @endif
            </div>

            <!-- Project Summary -->
            <div>
                <h2 class="text-heading mb-4">Projects</h2>
                @if(count($projectSummaries) > 0)
                    <div class="space-y-3">
                        @foreach($projectSummaries as $summary)
                            <div class="border border-border rounded-lg p-4 bg-surface hover:bg-surface-muted transition-colors">
                                <h3 class="text-body font-medium mb-2">{{ $summary['name'] }}</h3>
                                <div class="space-y-1 text-caption">
                                    <p class="flex justify-between">
                                        <span class="text-text-muted">Sent:</span>
                                        <span class="font-medium">{{ $summary['sent'] }}</span>
                                    </p>
                                    <p class="flex justify-between">
                                        <span class="text-text-muted">Opened:</span>
                                        <span class="font-medium">{{ $summary['opened'] }}</span>
                                    </p>
                                    <p class="flex justify-between">
                                        <span class="text-text-muted">Replied:</span>
                                        <span class="font-medium text-success">{{ $summary['replied'] }}</span>
                                    </p>
                                </div>
                                <a href="{{ route('campaigns.show', ['campaign' => 1]) }}" class="text-accent text-caption mt-2 inline-block hover:underline">
                                    View details →
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- All Projects Grid -->
    @if($projects->count() > 0)
        <h2 class="text-heading mb-4">All Projects</h2>
        <div class="grid gap-6">
            @foreach($projects as $project)
                <div class="border border-border rounded-lg p-6 hover:bg-surface-muted transition-colors">
                    <h2 class="text-heading">{{ $project->name }}</h2>
                    <p class="text-caption mt-2">{{ $project->website_url }}</p>
                </div>
            @endforeach
        </div>
    @else
        <x-ui.empty-state
            title="No Projects Yet"
            description="Create your first project to get started with prospect discovery and campaigns."
            actionLabel="Create Project"
            actionUrl="{{ route('projects.index') }}"
        />
    @endif
</div>
