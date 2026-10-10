<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Http;

class AnthropicClient implements AiClient
{
    private string $apiKey;

    private string $model = 'claude-sonnet-4-6';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function generatePersonalizedIntro(string $pageContent, string $website): array
    {
        $prompt = <<<EOT
        Given this prospect's website content:
        $pageContent

        Write a personalized outreach email opening (1-2 sentences) that:
        1. References something specific from their page
        2. Proposes one small, valuable addition
        3. Suggests natural anchor text for a backlink to $website

        Return only JSON with keys: intro, anchor_text (no other text)
        EOT;

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 300,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->failed()) {
                throw new Exception('API error: '.$response->body());
            }

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '{}';

            // Extract JSON from response (may be wrapped in markdown code block)
            if (str_contains($content, '```json')) {
                $content = preg_replace('/```json\n?|\n?```/i', '', $content);
            }

            $parsed = json_decode($content, true);

            return [
                'intro' => $parsed['intro'] ?? '',
                'anchor_text' => $parsed['anchor_text'] ?? 'our resource',
            ];
        } catch (Exception $e) {
            return ['intro' => '', 'anchor_text' => '', 'error' => $e->getMessage()];
        }
    }

    public function classifyReplyIntent(string $replyText): array
    {
        $prompt = <<<EOT
        Classify the intent of this email reply:
        $replyText

        Return only JSON with:
        - intent: one of "interested", "declined", "question", "other"
        - suggested_response: a 2-3 sentence paste-ready response if intent is "interested"

        No other text.
        EOT;

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 300,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->failed()) {
                return ['intent' => 'unclassified', 'suggested_response' => null];
            }

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '{}';

            if (str_contains($content, '```json')) {
                $content = preg_replace('/```json\n?|\n?```/i', '', $content);
            }

            $parsed = json_decode($content, true);

            return [
                'intent' => $parsed['intent'] ?? 'unclassified',
                'suggested_response' => $parsed['suggested_response'] ?? null,
            ];
        } catch (Exception $e) {
            return ['intent' => 'unclassified', 'suggested_response' => null];
        }
    }
}
