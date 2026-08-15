<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchCampaignSends implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Campaign $campaign)
    {}

    public function handle(): void
    {
        $campaign = $this->campaign->fresh();
        $campaign->update(['status' => 'sending']);

        // Dispatch send job for each pending recipient at step 1
        $campaign->recipients()
            ->where('status', 'pending')
            ->where('current_step', 1)
            ->each(function ($recipient) use ($campaign) {
                SendCampaignEmail::dispatch($recipient, 1)
                    ->onQueue('default');
            });

        $campaign->update(['status' => 'sent']);
    }
}
