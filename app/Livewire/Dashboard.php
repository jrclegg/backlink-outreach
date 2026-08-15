<?php

namespace App\Livewire;

use App\Models\BacklinkCheck;
use App\Models\CampaignRecipient;
use App\Models\EmailReply;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $org = Auth::user()->currentOrganization;

        if (!$org) {
            // If user has no current org, try to set one
            $org = Auth::user()->organizations()->first();
            if ($org) {
                Auth::user()->update(['current_organization_id' => $org->id]);
            }
        }

        $projects = $org ? $org->projects : collect();

        // Calculate analytics
        $stats = $this->calculateStats($org);
        $activityFeed = $this->getActivityFeed($org);
        $projectSummaries = $this->getProjectSummaries($org);

        return view('livewire.dashboard', [
            'organization' => $org,
            'projects' => $projects,
            'stats' => $stats,
            'activityFeed' => $activityFeed,
            'projectSummaries' => $projectSummaries,
        ])->layout('components.layouts.app', ['title' => 'Dashboard']);
    }

    /**
     * Calculate key statistics
     */
    private function calculateStats(?Organization $org): array
    {
        if (!$org) {
            return [
                'totalSent' => 0,
                'openRate' => 0,
                'replyRate' => 0,
                'interestedCount' => 0,
                'backlinksPlaced' => 0,
            ];
        }

        $projectIds = $org->projects()->pluck('id')->toArray();

        $totalSent = CampaignRecipient::whereHas('campaign', function ($q) use ($projectIds) {
            $q->whereIn('project_id', $projectIds);
        })
            ->where('status', '!=', 'draft')
            ->count();

        $totalOpened = CampaignRecipient::whereHas('campaign', function ($q) use ($projectIds) {
            $q->whereIn('project_id', $projectIds);
        })
            ->whereNotNull('opened_at')
            ->count();

        $openRate = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 1) : 0;

        $totalReplied = CampaignRecipient::whereHas('campaign', function ($q) use ($projectIds) {
            $q->whereIn('project_id', $projectIds);
        })
            ->whereNotNull('replied_at')
            ->count();

        $replyRate = $totalSent > 0 ? round(($totalReplied / $totalSent) * 100, 1) : 0;

        $interestedCount = EmailReply::where('intent', 'interested')
            ->whereHas('campaignRecipient.campaign', function ($q) use ($projectIds) {
                $q->whereIn('project_id', $projectIds);
            })
            ->count();

        $backlinksPlaced = BacklinkCheck::where('status', 'active')
            ->whereHas('campaignRecipient.campaign', function ($q) use ($projectIds) {
                $q->whereIn('project_id', $projectIds);
            })
            ->count();

        return [
            'totalSent' => $totalSent,
            'openRate' => $openRate,
            'replyRate' => $replyRate,
            'interestedCount' => $interestedCount,
            'backlinksPlaced' => $backlinksPlaced,
        ];
    }

    /**
     * Get recent activity feed
     */
    private function getActivityFeed(?Organization $org): array
    {
        if (!$org) {
            return [];
        }

        $projectIds = $org->projects()->pluck('id')->toArray();

        // Get recent replies
        $replies = EmailReply::whereHas('campaignRecipient.campaign', function ($q) use ($projectIds) {
            $q->whereIn('project_id', $projectIds);
        })
            ->latest('received_at')
            ->take(5)
            ->get()
            ->map(function ($reply) {
                return [
                    'type' => 'reply',
                    'icon' => '💬',
                    'title' => 'Reply received',
                    'description' => "From {$reply->from_address}: {$reply->subject}",
                    'timestamp' => $reply->received_at,
                    'intent' => $reply->intent,
                ];
            })
            ->toArray();

        return $replies;
    }

    /**
     * Get project-level summaries
     */
    private function getProjectSummaries(?Organization $org): array
    {
        if (!$org || $org->projects->isEmpty()) {
            return [];
        }

        return $org->projects()
            ->take(5)
            ->get()
            ->map(function ($project) {
                $projectIds = [$project->id];

                $sent = CampaignRecipient::whereHas('campaign', function ($q) use ($projectIds) {
                    $q->whereIn('project_id', $projectIds);
                })
                    ->where('status', '!=', 'draft')
                    ->count();

                $opened = CampaignRecipient::whereHas('campaign', function ($q) use ($projectIds) {
                    $q->whereIn('project_id', $projectIds);
                })
                    ->whereNotNull('opened_at')
                    ->count();

                $replied = CampaignRecipient::whereHas('campaign', function ($q) use ($projectIds) {
                    $q->whereIn('project_id', $projectIds);
                })
                    ->whereNotNull('replied_at')
                    ->count();

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'sent' => $sent,
                    'opened' => $opened,
                    'replied' => $replied,
                ];
            })
            ->toArray();
    }
}
