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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI MVP Server
    |--------------------------------------------------------------------------
    |
    | Configuration for the separate AI MVP server that handles OpenAI
    | queries with business context.
    |
    */

    'ai_mvp_server' => [
        'url' => env('AI_MVP_SERVER_URL', 'http://localhost:3001'),
        'api_key' => env('AI_MVP_SERVER_API_KEY'),
        'timeout' => env('AI_MVP_SERVER_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    |
    | Configuration for WhatsApp Business Cloud API integration.
    |
    */

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Geolocation Services
    |--------------------------------------------------------------------------
    |
    | Configuration for IP geolocation services used in login notifications.
    | Optional API keys enhance fallback reliability.
    |
    */

    'geolocation' => [
        'timeout' => env('GEOLOCATION_TIMEOUT', 2),
        'cache_ttl' => env('GEOLOCATION_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Umami Analytics
    |--------------------------------------------------------------------------
    |
    | Privacy-first analytics. Set UMAMI_WEBSITE_ID and UMAMI_SCRIPT_URL to
    | enable. Script URL: https://cloud.umami.is/script.js (cloud) or your
    | self-hosted instance URL + /umami.js.
    |
    */

    'umami' => [
        'website_id' => env('UMAMI_WEBSITE_ID'),
        'script_url' => env('UMAMI_SCRIPT_URL', 'https://cloud.umami.is/script.js'),
        'do_not_track' => env('UMAMI_DO_NOT_TRACK', true),
        'host_url' => env('UMAMI_HOST_URL'),
    ],

];
