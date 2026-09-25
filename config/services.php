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

    'stc' => [
        'base_url' => env('STC_BASE_URL', 'https://stcdev-client.esoftdream.co.id'),
        'token' => env('STC_TOKEN'),
        'callback_token' => env('STC_CALLBACK_TOKEN'),
        'client_id' => env('STC_CLIENT_ID'),
        'client_code' => env('STC_CLIENT_CODE'),
        'origin' => [
            'district_id' => env('STC_ORIGIN_DISTRICT_ID'),
            'subdistrict_id' => env('STC_ORIGIN_SUBDISTRICT_ID'),
            'address' => env('STC_ORIGIN_ADDRESS'),
            'phone' => env('STC_ORIGIN_PHONE'),
            'latitude' => env('STC_ORIGIN_LATITUDE'),
            'longitude' => env('STC_ORIGIN_LONGITUDE'),
        ],
        'connect_timeout' => (int) env('STC_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('STC_TIMEOUT', 10),
    ],

];
