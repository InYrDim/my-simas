<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // OpenWA WhatsApp gateway (https://docs.open-wa.org). The admin key
    // creates sessions and per-school keys; the credentials key (64 hex
    // characters) encrypts those per-school keys at rest.
    'openwa' => [
        'base_url' => env('OPENWA_API_BASE_URL'),
        'admin_api_key' => env('OPENWA_ADMIN_API_KEY'),
        'credentials_key' => env('OPENWA_CREDENTIALS_KEY'),
        'timeout' => (int) env('OPENWA_TIMEOUT', 15),

        // Safe sending: one school's number must not look like a bot.
        // Seconds between two messages (random in the range), messages
        // per school per day, and the circuit breaker: consecutive gateway
        // failures that pause the school, and for how many seconds.
        'pace_min' => (int) env('OPENWA_PACE_MIN', 3),
        'pace_max' => (int) env('OPENWA_PACE_MAX', 10),
        'daily_limit' => (int) env('OPENWA_DAILY_LIMIT', 500),
        'breaker_threshold' => (int) env('OPENWA_BREAKER_THRESHOLD', 3),
        'breaker_pause' => (int) env('OPENWA_BREAKER_PAUSE', 300),
    ],

];
