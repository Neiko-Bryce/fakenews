<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Mozilla CA bundle — fixes cURL SSL error 60 on Windows (shared by Gemini + Groq HTTP calls).
    'ssl_ca_bundle' => env('SSL_CA_BUNDLE', env('GEMINI_CA_BUNDLE', storage_path('certs/cacert.pem'))),

    'news_analyzer' => [
        'driver' => env('NEWS_ANALYZER_DRIVER', 'gemini'),
        // When true, alternate providers on 429/quota (Gemini ↔ Groq) if both API keys are set.
        // Note: empty NEWS_ANALYZER_FALLBACK= in .env would otherwise be read as "" and disable fallback.
        'fallback' => filter_var(
            ($raw = env('NEWS_ANALYZER_FALLBACK')) === null || $raw === ''
                ? 'true'
                : $raw,
            FILTER_VALIDATE_BOOL
        ),
        // Max HTTP attempts when cycling (e.g. 6 = up to 3 tries per provider in a 2-provider chain).
        'fallback_max_attempts' => (int) env('NEWS_ANALYZER_FALLBACK_MAX_ATTEMPTS', 6),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        // Flash-Lite: higher free-tier throughput than full Flash; see https://ai.google.dev/gemini-api/docs/models
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
        // Lower cap = less TPM per request for structured JSON (raise if responses truncate).
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 2048),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        // Smaller model = lower TPM per request and typically more RPM headroom than 70B; see https://console.groq.com/docs/models
        'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
        'vision_model' => env('GROQ_VISION_MODEL', 'llama-3.2-11b-vision-preview'),
        'max_tokens' => (int) env('GROQ_MAX_TOKENS', 2048),
    ],

];
