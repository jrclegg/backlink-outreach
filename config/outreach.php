<?php

return [
    'academic_jobs_feed' => env('ACADEMIC_JOBS_FEED'),
    'academic_jobs_database' => env('ACADEMIC_JOBS_DATABASE'),
    // Codex mode is for a trusted local installation using the worker user's login.
    'ai_provider' => env('OUTREACH_AI_PROVIDER', 'anthropic'),
    'codex_binary' => env('CODEX_BINARY', 'codex'),
    'codex_model' => env('CODEX_MODEL', ''),
    // In Codex mode, requests Codex can't complete (e.g. usage limits) fall back to the local Claude login.
    'claude_fallback' => (bool) env('CLAUDE_FALLBACK', true),
    'claude_binary' => env('CLAUDE_BINARY', 'claude'),
    'claude_model' => env('CLAUDE_MODEL', 'opus'),
];
