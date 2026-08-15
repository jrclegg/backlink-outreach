<?php

namespace App\Jobs;

use App\Models\CampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AdvanceCampaignSequences implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Find all recipients due for advancement
        $recipients = CampaignRecipient::where('status', 'sent')
            ->whereNotNull('next_step_send_at')
            ->where('next_step_send_at', '<=', now())
            ->get();

        foreach ($recipients as $recipient) {
            $campaign = $recipient->campaign;
            $nextStepNum = $recipient->current_step + 1;
            $nextStep = $campaign->sequenceSteps()->where('step_number', $nextStepNum)->first();

            if (!$nextStep) {
                continue; // No more steps
            }

            // Check send_if condition
            if ($nextStep->send_if === 'no_reply' && $recipient->replied_at) {
                continue; // Don't send if reply received and send_if is no_reply
            }

            // Skip if recipient has bounced or failed
            if (in_array($recipient->status, ['bounced', 'failed', 'suppressed'])) {
                continue;
            }

            // Dispatch the follow-up send
            SendCampaignEmail::dispatch($recipient, $nextStepNum);
        }
    }
}
