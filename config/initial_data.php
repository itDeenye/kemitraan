<?php

return [
    'administrator' => [
        'username' => env('DNY_INITIAL_ADMIN_USERNAME', 'admin'),
        'password' => env('DNY_INITIAL_ADMIN_PASSWORD'),
        'name' => env('DNY_INITIAL_ADMIN_NAME', 'Administrator'),
        'email' => env('DNY_INITIAL_ADMIN_EMAIL', 'admin@dny.co.id'),
    ],
    'member' => [
        'username' => env('DNY_INITIAL_MEMBER_USERNAME', 'member'),
        'password' => env('DNY_INITIAL_MEMBER_PASSWORD'),
        'pin' => env('DNY_INITIAL_MEMBER_PIN', '123456'),
        'name' => env('DNY_INITIAL_MEMBER_NAME', 'Initial Member'),
        'email' => env('DNY_INITIAL_MEMBER_EMAIL', 'member@dny.co.id'),
        'mobile_phone' => env('DNY_INITIAL_MEMBER_MOBILE_PHONE', '628999462641'),
    ],
    'development_approval_password' => env(
        'DNY_DEVELOPMENT_APPROVAL_PASSWORD',
        env('DNY_INITIAL_MEMBER_PASSWORD')
    ),
];
