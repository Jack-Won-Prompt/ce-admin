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

    // ── OpenAI (OCR 처방전 인식) ──────────────────────────
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    // ── Anthropic (Claude) ────────────────────────────────
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    // ── Todoworks 제품 API ────────────────────────────────
    'todoworks' => [
        'api_url' => env('TODOWORKS_API_URL', 'https://todoworks.co.kr'),
        'token'   => env('TODOWORKS_API_TOKEN'),
    ],

    // ── CE샵 Webhook & API ───────────────────────────────
    'ce_shop' => [
        'webhook_secret' => env('CE_SHOP_WEBHOOK_SECRET', 'ce-shop-secret-2026'),
        'base_url'       => env('CE_SHOP_BASE_URL', 'http://localhost/ce-shop/public'),
        'api_enabled'    => env('CE_SHOP_API_ENABLED', false),
    ],
];
