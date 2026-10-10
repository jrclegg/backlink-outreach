<?php

use App\Jobs\AdvanceCampaignSequences;
use App\Jobs\CheckOpportunityLink;
use App\Jobs\PollInboxForReplies;
use App\Jobs\ScheduleBacklinkChecks;
use App\Models\EmailInbox;
use App\Models\Opportunity;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule periodic jobs
Schedule::job(new AdvanceCampaignSequences)
    ->hourly()
    ->withoutOverlapping();

Schedule::call(function () {
    EmailInbox::where('is_active', true)->each(function ($inbox) {
        PollInboxForReplies::dispatch($inbox);
    });
})->everyFiveMinutes()
    ->name('poll-inboxes')
    ->withoutOverlapping();

Schedule::job(new ScheduleBacklinkChecks)
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::call(function () {
    Opportunity::whereNotNull('placement_url')
        ->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
        ->each(fn ($op) => CheckOpportunityLink::dispatch($op));
})->dailyAt('03:15')->name('check-opportunity-links')->withoutOverlapping();

// London-time weekly employer batches use the campaign's sending mode.
Schedule::call(function () {
    $now = now('Europe/London');
    \App\Models\EmployerCampaign::where('enabled', true)
        ->where(fn ($q) => $q->where('cadence', 'daily')->orWhere(fn ($q) => $q->where('cadence', 'weekly')->where('weekday', $now->dayOfWeek)))
        ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $now->toDateString()))
        ->where('send_time', '<=', $now->format('H:i'))
        ->each(fn ($campaign) => app(\App\Services\Outreach\EmployerOutreach::class)->start($campaign));
})->everyMinute()->name('prepare-employer-outreach')->withoutOverlapping();

Schedule::call(function () {
    \App\Models\EmployerBatchItem::whereNull('auto_reviewed_at')
        ->whereHas('message', fn ($q) => $q->where('status', 'draft'))
        ->whereHas('contact', fn ($q) => $q->where('status', '!=', 'excluded')->whereNotNull('job_advert_url'))
        ->whereHas('batch.campaign', fn ($q) => $q->where('enabled', true)->where('auto_send', true))
        ->each(fn ($item) => \App\Jobs\AutoSendEmployerEmail::dispatch($item));
})->everyFiveMinutes()->name('check-automatic-employer-outreach')->withoutOverlapping();
