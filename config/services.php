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

    // Tawk.to live-chat widget. Disabled by default — set TAWK_ENABLED=true and
    // TAWK_ID to your own property to enable. (Never inherit a third party's id.)
    'tawk' => [
        'enabled' => env('TAWK_ENABLED', false),
        'id' => env('TAWK_ID'),
    ],

    // §7.5 — optional: auto-fetches a pasted YouTube lesson video's duration via the Data API
    // v3 (oEmbed alone doesn't expose duration). Entirely optional — with no key configured,
    // the curriculum builder's "Auto-fetch duration" button just no-ops and duration stays a
    // manual field, exactly as it already was before this feature existed.
    'youtube' => [
        'key' => env('YOUTUBE_API_KEY'),
    ],

    // Flutterwave payment gateway (HMS_PLAN.md §16). Secrets live ONLY in env
    // (C11) — never commit real keys. secret_hash verifies inbound webhooks.
    'flutterwave' => [
        'secret_key' => env('FLW_SECRET_KEY'),
        'public_key' => env('FLW_PUBLIC_KEY'),
        'encryption_key' => env('FLW_ENCRYPTION_KEY'),
        'secret_hash' => env('FLW_SECRET_HASH'),
        'base_url' => env('FLW_BASE_URL', 'https://api.flutterwave.com'),
        'currency' => env('FLW_CURRENCY', 'UGX'),
        'payment_options' => env('FLW_PAYMENT_OPTIONS', 'card,mobilemoneyuganda,banktransfer,ussd'),
        'timeout' => (int) env('FLW_TIMEOUT', 20),
    ],

];
