<?php

return [
    'max_login_attempts' => (int) env('DNY_AUTH_MAX_LOGIN_ATTEMPTS', 5),
    'lockout_minutes' => (int) env('DNY_AUTH_LOCKOUT_MINUTES', 15),
    'token_expiration_minutes' => (int) env('DNY_AUTH_TOKEN_EXPIRATION_MINUTES', 1440),
    'refresh_token_expiration_days' => (int) env('DNY_AUTH_REFRESH_TOKEN_EXPIRATION_DAYS', 30),
    'login_rate_limit_per_minute' => (int) env('DNY_AUTH_LOGIN_RATE_LIMIT_PER_MINUTE', 5),
    'password_reset_rate_limit_per_minute' => (int) env('DNY_AUTH_PASSWORD_RESET_RATE_LIMIT_PER_MINUTE', 5),
    'password_reset_urls' => [
        'admin' => env('DNY_ADMIN_PASSWORD_RESET_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/admin/reset-password'),
        'member' => env('DNY_MEMBER_PASSWORD_RESET_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/member/reset-password'),
    ],
    'api_rate_limit_per_minute' => (int) env('DNY_API_RATE_LIMIT_PER_MINUTE', 30),
];
