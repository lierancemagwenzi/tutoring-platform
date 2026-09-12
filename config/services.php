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

    'h5p' => [
        'url' => env('H5P_SERVER_URL', 'http://127.0.0.1:8080'),
        'public_url' => env('H5P_SERVER_PUBLIC_URL', 'http://127.0.0.1:8080'),
        'key' => env('H5P_SERVER_API_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        // TEMPORARY — see GoogleCalendarMeetingProvider::createMeeting().
        // Skips the real Calendar API call and returns a placeholder
        // meeting link instead, for use until existing connected accounts
        // have been reconnected under the fixed OAuth scope.
        'fake_meetings' => env('GOOGLE_FAKE_MEETINGS', false),
    ],

    'payfast' => [
        'merchant_id' => env('PAYFAST_MERCHANT_ID'),
        'merchant_key' => env('PAYFAST_MERCHANT_KEY'),
        'passphrase' => env('PAYFAST_PASSPHRASE'),
        'sandbox' => env('PAYFAST_SANDBOX', true),
        'process_url' => env('PAYFAST_SANDBOX', true)
            ? 'https://sandbox.payfast.co.za/eng/process'
            : 'https://www.payfast.co.za/eng/process',
        'validate_url' => env('PAYFAST_SANDBOX', true)
            ? 'https://sandbox.payfast.co.za/eng/query/validate'
            : 'https://www.payfast.co.za/eng/query/validate',
    ],

];
