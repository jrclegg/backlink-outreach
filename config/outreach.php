<?php

return [
    'academic_jobs_feed' => env('ACADEMIC_JOBS_FEED'),
    'academic_jobs_database' => env('ACADEMIC_JOBS_DATABASE'),
    // Codex mode is for a trusted local installation using the worker user's login.
    'ai_provider' => env('OUTREACH_AI_PROVIDER', 'anthropic'),
    'codex_binary' => env('CODEX_BINARY', 'codex'),
    'codex_model' => env('CODEX_MODEL', ''),
];
