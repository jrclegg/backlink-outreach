<?php

namespace App\Services\Outreach;

use App\Jobs\PrepareEmployerBatch;
use App\Jobs\SendReviewedMessage;
use App\Models\EmployerBatch;
use App\Models\EmployerBatchItem;
use App\Models\EmployerCampaign;
use App\Models\EmployerContact;
use App\Models\OutreachMessage;
use App\Models\Suppression;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmployerOutreach
{
    public static function advertKey(string $url): string
    {
        $host = preg_replace('/^www\./', '', strtolower(parse_url($url, PHP_URL_HOST) ?? ''));
        $path = rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        if ($host === 'jobs.ac.uk' && (preg_match('#^/job/([^/]+)#', $path, $match) || isset($query['job_ref']))) {
            $reference = $query['job_ref'] ?? $match[1];
            if ($reference !== 'share') {
                return $host.'/job/'.strtoupper($reference);
            }
        }
        foreach (array_keys($query) as $key) {
            if (str_starts_with(strtolower($key), 'utm_') || in_array(strtolower($key), ['gh_src', 'source', 'collaboration_id', 'fbclid', 'gclid'])) {
                unset($query[$key]);
            }
        }
        ksort($query);

        return $host.$path.($query ? '?'.http_build_query($query) : '');
    }

    public function start(EmployerCampaign $campaign, ?\Carbon\Carbon $date = null): EmployerBatch
    {
        $date = ($date ?? now('Europe/London'))->copy()->timezone('Europe/London');
        if ($campaign->starts_on && $date->toDateString() < $campaign->starts_on) {
            $date = \Carbon\Carbon::parse($campaign->starts_on, 'Europe/London');
        }
        $period = $campaign->cadence === 'daily' ? $date->toDateString() : $date->copy()->startOfWeek()->toDateString();
        $batch = EmployerBatch::firstOrCreate(['employer_campaign_id' => $campaign->id, 'week' => $period]);
        if ($batch->wasRecentlyCreated) {
            $job = PrepareEmployerBatch::dispatch($batch);
            if ($date->toDateString() > now('Europe/London')->toDateString()) {
                $job->delay($date->copy()->setTimeFromTimeString($campaign->send_time ?: '09:00'));
            }
        }

        return $batch->refresh();
    }

    public function eligible(EmployerContact $contact): bool
    {
        $campaign = $contact->campaign;
        if ($contact->status === 'excluded' || ! PublicPage::validUrl($contact->job_advert_url ?? '')) {
            return false;
        }
        if (Suppression::where('organization_id', $campaign->project->organization_id)->where('email', $contact->email)->exists()) {
            return false;
        }

        return ! OutreachMessage::whereHas('conversation', fn ($q) => $q->where('project_id', $campaign->project_id))
            ->where(fn ($q) => $q->where('to_email', $contact->email)->orWhere('from_email', $contact->email))
            ->whereIn('status', ['queued', 'sending', 'sent', 'received'])->exists();
    }

    public function approve(EmployerBatchItem $item): bool
    {
        return DB::transaction(function () use ($item) {
            $item = EmployerBatchItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $campaign = $item->batch->campaign;
            $campaign = EmployerCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            $campaignMessageIds = EmployerBatchItem::whereHas('batch', fn ($q) => $q->where('employer_campaign_id', $campaign->id))->whereNotNull('outreach_message_id')->pluck('outreach_message_id');
            $periodStart = $campaign->cadence === 'daily' ? now('Europe/London')->startOfDay() : now('Europe/London')->startOfWeek();
            if (OutreachMessage::whereIn('id', $campaignMessageIds)->where('approved_at', '>=', $periodStart)->count() >= $campaign->weekly_limit) {
                return false;
            }
            $contact = $item->contact;
            $advertKey = self::advertKey($contact->job_advert_url ?? '');
            $sameAdvertContactIds = $campaign->contacts()->whereNotNull('job_advert_url')->get(['id', 'job_advert_url'])
                ->filter(fn ($candidate) => self::advertKey($candidate->job_advert_url) === $advertKey)->pluck('id');
            $sameAdvertIds = EmployerBatchItem::whereIn('employer_contact_id', $sameAdvertContactIds)->whereNotNull('outreach_message_id')->pluck('outreach_message_id');
            if (OutreachMessage::whereIn('id', $sameAdvertIds)->whereNotNull('approved_at')->exists()) {
                return false;
            }
            if (! $item->outreach_message_id || ! $this->eligible($contact)) {
                return false;
            }
            $inbox = $campaign->project->emailInboxes()->whereKey($campaign->email_inbox_id)->where('is_active', true)->first();
            if (! $inbox || $inbox->organization_id !== $campaign->project->organization_id) {
                throw new RuntimeException('Choose an active sending account assigned to this project.');
            }
            $message = OutreachMessage::whereKey($item->outreach_message_id)->lockForUpdate()->firstOrFail();
            if ($message->status !== 'draft') {
                return false;
            }
            if ($message->email_inbox_id !== $inbox->id || $message->to_email !== $contact->email || $message->conversation->project_id !== $campaign->project_id) {
                throw new RuntimeException('Draft identity changed. Review it in the project inbox.');
            }
            if ($message->conversation->messages()->where('direction', 'inbound')->exists()) {
                return false;
            }
            $contact->update(['status' => 'approved']);
            $message->update(['status' => 'queued', 'approved_at' => now()]);
            SendReviewedMessage::dispatch($message)->afterCommit();

            return true;
        });
    }
}
