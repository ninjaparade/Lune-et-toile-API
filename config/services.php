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

    // APNs token auth. The key is the .p8 from developer.apple.com (Keys → Apple Push
    // Notifications service, "Sandbox & Production"); give a file path or one-line base64.
    'apn' => [
        'key_path' => env('APN_KEY_PATH'),
        'key_base64' => env('APN_KEY_BASE64'),
        'key_id' => env('APN_KEY_ID'),
        'team_id' => env('APN_TEAM_ID', 'U4L693DAJG'),
        'bundle_id' => env('APN_BUNDLE_ID', 'com.ninjaparade.etoile'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
