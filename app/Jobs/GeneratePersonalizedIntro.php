<?php

namespace App\Jobs;

use App\Models\CampaignRecipient;
use App\Services\AI\AnthropicClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class GeneratePersonalizedIntro implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public CampaignRecipient $recipient)
    {}

    public function handle(): void
    {
        $recipient = $this->recipient->fresh();

        // Skip if no LLM key configured
        $org = $recipient->campaign->project->organization;
        if (!$org->llm_api_key) {
            return;
        }

        try {
            // Fetch prospect page content
            $pageContent = Http::timeout(10)->get($recipient->prospect->site_url)->body();
            // Basic text extraction (in production, use proper HTML parsing)
            $pageContent = strip_tags($pageContent);
            $pageContent = substr($pageContent, 0, 2000); // Limit to first 2000 chars

            // Generate intro using AI
            $client = new AnthropicClient($org->llm_api_key);
            $result = $client->generatePersonalizedIntro(
                $pageContent,
                $recipient->campaign->project->website_url
            );

            if (empty($result['error'])) {
                $recipient->update([
                    'ai_intro_text' => $result['intro'],
                    'anchor_text' => $result['anchor_text'],
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail - will send with empty intro
            \Log::warning('Personalization failed for recipient ' . $recipient->id . ': ' . $e->getMessage());
        }
    }
}
