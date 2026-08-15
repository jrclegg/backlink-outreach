<?php

namespace App\Services\AI;

interface AiClient
{
    public function generatePersonalizedIntro(string $pageContent, string $website): array;

    public function classifyReplyIntent(string $replyText): array;
}
