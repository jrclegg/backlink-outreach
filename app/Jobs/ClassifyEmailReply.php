<?php

namespace App\Jobs;

use App\Models\EmailReply;
use App\Services\AI\AnthropicClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClassifyEmailReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public EmailReply $reply)
    {}

    public function handle(): void
    {
        $reply = $this->reply->fresh();

        // Skip if no recipient (unmatched reply)
        if (!$reply->campaignRecipient) {
            return;
        }

        // Skip if no LLM key
        $org = $reply->campaignRecipient->campaign->project->organization;
        if (!$org->llm_api_key) {
            return;
        }

        try {
            $client = new AnthropicClient($org->llm_api_key);
            $result = $client->classifyReplyIntent($reply->body_text);

            $reply->update([
                'intent' => $result['intent'],
                'ai_suggested_response' => $result['suggested_response'],
            ]);

            // Auto-create backlink check if interested
            if ($result['intent'] === 'interested' && !$reply->campaignRecipient->backlinkCheck) {
                \App\Models\BacklinkCheck::create([
                    'campaign_recipient_id' => $reply->campaignRecipient->id,
                    'target_page_url' => $reply->campaignRecipient->prospect->site_url,
                    'expected_link_url' => $reply->campaignRecipient->campaign->project->website_url,
                    'status' => 'pending',
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Reply classification failed: ' . $e->getMessage());
        }
    }
}
