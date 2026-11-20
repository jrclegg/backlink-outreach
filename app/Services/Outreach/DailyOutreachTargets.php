<?php

namespace App\Services\Outreach;

use App\Jobs\DraftEmployerEmail;
use App\Jobs\ResearchConsultancyBatch;
use App\Jobs\ResearchEmployerContacts;
use App\Jobs\ResearchLeadBatch;
use App\Jobs\ResearchSponsorBatch;
use App\Models\ConsultancyCampaign;
use App\Models\EmployerCampaign;
use App\Models\LeadCampaign;
use App\Models\SponsorCampaign;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DailyOutreachTargets
{
    public function fill(): void
    {
        $now = now('Europe/London');
        EmployerCampaign::where('enabled', true)->where('auto_send', true)->where('cadence', 'daily')
            ->where('send_time', '<=', $now->format('H:i'))
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $now->toDateString()))
            ->each(function ($campaign) use ($now) {
                DB::transaction(function () use ($campaign, $now) {
                    $campaign = EmployerCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                    $batch = $campaign->batches()->where('week', $now->toDateString())->first();
                    if (! $batch || in_array($batch->status, ['queued', 'failed', 'paused'])) {
                        return;
                    }
                    // Failed checks do not use a slot. Count approvals across all batches today,
                    // plus drafts still being prepared/reviewed, before selecting replacements.
                    $items = $campaign->batches()->with('items.message')->get()->flatMap->items;
                    $used = $items->filter(fn ($item) => $item->message?->approved_at?->gte($now->copy()->startOfDay()))->count();
                    $pending = $items->filter(fn ($item) => ! $item->message?->approved_at
                        && (in_array($item->status, ['queued', 'drafting'])
                            || ($item->message?->status === 'draft' && $item->auto_reviewed_at === null)))->count();
                    $remaining = max(0, $campaign->weekly_limit - $used - $pending);
                    foreach ($campaign->contacts()->where('status', '!=', 'excluded')->whereDoesntHave('item')->orderBy('id')->cursor() as $contact) {
                        if (! $remaining) {
                            break;
                        }
                        if (! app(EmployerOutreach::class)->eligible($contact)) {
                            continue;
                        }
                        $item = $batch->items()->create(['employer_contact_id' => $contact->id]);
                        DraftEmployerEmail::dispatch($item)->afterCommit();
                        $remaining--;
                        if ($batch->status !== 'researching') {
                            $batch->update(['status' => 'drafting']);
                        }
                    }
                    // A replacement pool can run dry before the sending target is met.
                    // Search for fresh contacts in bounded rounds instead of waiting for tomorrow.
                    $key = 'employer-top-ups-'.$batch->id;
                    if ($remaining > 0 && $pending === 0 && $campaign->discover && $batch->status !== 'researching') {
                        Cache::add($key, 0, $now->copy()->endOfDay());
                        if (Cache::get($key, 0) >= 3) {
                            return;
                        }
                        Cache::increment($key);
                        $focuses = [
                            'Newly advertised UK ecology and evolution academic vacancies',
                            'US ecology, evolution and conservation faculty and postdoctoral vacancies',
                            'Canadian and Australian ecology and biodiversity academic vacancies',
                            'European ecology, global change and conservation research vacancies',
                            'Marine and freshwater ecology academic research vacancies',
                            'Ecology and evolution doctoral and research institute vacancies',
                        ];
                        $generation = (string) Str::uuid();
                        Cache::put('employer-research-remaining-'.$batch->id.'-'.$generation, count($focuses), 86400);
                        $batch->update(['status' => 'researching']);
                        foreach ($focuses as $part => $focus) {
                            ResearchEmployerContacts::dispatch($batch, $part, [], $focus, $generation)->afterCommit();
                        }
                    }
                });
            });

        SponsorCampaign::where('enabled', true)->where('auto_send', true)
            ->where('send_time', '<=', $now->format('H:i'))->each(function ($campaign) use ($now) {
                DB::transaction(function () use ($campaign, $now) {
                    $campaign = SponsorCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                    $batch = $campaign->batches()->where('day', $now->toDateString())->first();
                    if (! $batch || ! in_array($batch->status, ['ready', 'needs_attention', 'needs_contacts', 'drafting'])
                        || $batch->prospects()->whereIn('status', ['queued', 'drafting'])->exists()
                        || app(SponsorOutreach::class)->approvalCount($campaign) >= $campaign->daily_limit) {
                        return;
                    }
                    // Bound additional searches and never automatically replay a failed AI job.
                    $key = 'sponsor-top-ups-'.$batch->id;
                    Cache::add($key, 0, $now->copy()->endOfDay());
                    if (Cache::get($key, 0) >= 5) {
                        return;
                    }
                    Cache::increment($key);
                    $batch->update(['status' => 'queued', 'error' => null]);
                    ResearchSponsorBatch::dispatch($batch)->afterCommit();
                });
            });

        // In approval mode the target is drafts ready for you; in automatic mode it is emails approved today.
        ConsultancyCampaign::where('enabled', true)
            ->where('send_time', '<=', $now->format('H:i'))->each(function ($campaign) use ($now) {
                DB::transaction(function () use ($campaign, $now) {
                    $campaign = ConsultancyCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                    $batch = $campaign->batches()->where('day', $now->toDateString())->first();
                    $done = $campaign->auto_send ? app(ConsultancyOutreach::class)->approvalCount($campaign)
                        : $campaign->prospects()->whereIn('status', ['passed', 'approved'])->where('reviewed_at', '>=', $now->copy()->startOfDay())->count();
                    if (! $batch || ! in_array($batch->status, ['ready', 'needs_attention', 'needs_contacts', 'drafting'])
                        || $batch->prospects()->whereIn('status', ['queued', 'drafting'])->exists() || $done >= $campaign->daily_limit) {
                        return;
                    }
                    $key = 'consultancy-top-ups-'.$batch->id;
                    Cache::add($key, 0, $now->copy()->endOfDay());
                    if (Cache::get($key, 0) >= 3) {
                        return;
                    }
                    Cache::increment($key);
                    $batch->update(['status' => 'queued', 'error' => null]);
                    ResearchConsultancyBatch::dispatch($batch)->afterCommit();
                });
            });

        // Product lead campaigns: keep researching in bounded rounds until today's target is met.
        LeadCampaign::where('enabled', true)
            ->where('send_time', '<=', $now->format('H:i'))->each(function ($campaign) use ($now) {
                DB::transaction(function () use ($campaign, $now) {
                    $campaign = LeadCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                    $batch = $campaign->batches()->where('day', $now->toDateString())->first();
                    $done = $campaign->auto_send ? app(LeadOutreach::class)->approvalCount($campaign)
                        : $campaign->prospects()->whereIn('status', ['passed', 'approved'])->where('reviewed_at', '>=', $now->copy()->startOfDay())->count();
                    if (! $batch || ! in_array($batch->status, ['ready', 'needs_attention', 'needs_contacts', 'drafting'])
                        || $batch->prospects()->whereIn('status', ['queued', 'drafting'])->exists() || $done >= $campaign->daily_limit) {
                        return;
                    }
                    $key = 'lead-top-ups-'.$batch->id;
                    Cache::add($key, 0, $now->copy()->endOfDay());
                    if (Cache::get($key, 0) >= 4) {
                        return;
                    }
                    Cache::increment($key);
                    $batch->update(['status' => 'queued', 'error' => null]);
                    ResearchLeadBatch::dispatch($batch)->afterCommit();
                });
            });
    }
}
