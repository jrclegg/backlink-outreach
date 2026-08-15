<?php

use App\Jobs\AdvanceCampaignSequences;
use App\Jobs\PollInboxForReplies;
use App\Jobs\ScheduleBacklinkChecks;
use App\Models\EmailInbox;
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
    ->withoutOverlapping();

Schedule::job(new ScheduleBacklinkChecks)
    ->dailyAt('03:00')
    ->withoutOverlapping();
