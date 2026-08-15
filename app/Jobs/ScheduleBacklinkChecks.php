<?php

namespace App\Jobs;

use App\Models\BacklinkCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScheduleBacklinkChecks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Find all backlink checks due for verification
        $checks = BacklinkCheck::where('status', '!=', 'error')
            ->where(function ($query) {
                $query->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', now());
            })
            ->get();

        foreach ($checks as $check) {
            CheckBacklinkPlacement::dispatch($check);
        }
    }
}
