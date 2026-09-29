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

    'supply_chain' => [
        'enabled' => (bool) env('SUPPLY_CHAIN_ENABLED', false),
        'base_url' => env('SUPPLY_CHAIN_BASE_URL'),
        'username' => env('SUPPLY_CHAIN_USERNAME'),
        'password' => env('SUPPLY_CHAIN_PASSWORD'),
        'approval_callback_enabled' => env(
            'SUPPLY_CHAIN_APPROVAL_CALLBACK_ENABLED',
            env('APP_ENV') === 'production',
        ),
        'callback_token' => env('SUPPLY_CHAIN_CALLBACK_TOKEN'),
        'member_prefix' => env('SUPPLY_CHAIN_MEMBER_PREFIX', 'LC01'),
        'testing_product_fallback' => env('SUPPLY_CHAIN_TESTING_PRODUCT_FALLBACK', 'DNY0003'),
        'testing_product_codes' => [
            'DNY0003', 'DNY0004', 'DNY0005', 'DNY0006', 'DNY0007', 'DNY0008', 'DNY0009',
            'DNY0010', 'DNY0011', 'DNY0012', 'DNY0013', 'DNY0014', 'DNY0015', 'DNY0020',
            'DNY0021', 'DNY0022', 'DNY0023', 'DNY0024', 'DNY0025', 'DNY0026', 'DNY0032',
            'DNY0034', 'DNY0035', 'DNY0036', 'DNY0037', 'DNY0038', 'DNY0041', 'DNY0043',
            'DNY0045', 'DNY0046', 'DNY0048', 'DNY0049', 'DNY0052', 'DNY0053', 'DNY0054',
            'DNY0055', 'DNY0057', 'DNY0058', 'DNY0059', 'DNY0060', 'DNY0061', 'DNY0062',
            'DNY0063', 'DNY0064', 'DNY0065', 'DNY0066',
        ],
        'connect_timeout' => (int) env('SUPPLY_CHAIN_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('SUPPLY_CHAIN_TIMEOUT', 30),
        'token_ttl_seconds' => (int) env('SUPPLY_CHAIN_TOKEN_TTL_SECONDS', 3300),
    ],

];
