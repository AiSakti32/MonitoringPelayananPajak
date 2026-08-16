<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Kajang Lako'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),
    'tagline' => 'Tepat Waktu, Tepat Pantau, Tepat Tindak',
    'session' => [
        'name' => env('SESSION_NAME', 'kajanglako_session'),
        'secure' => filter_var(env('SESSION_SECURE', false), FILTER_VALIDATE_BOOLEAN),
        'http_only' => filter_var(env('SESSION_HTTP_ONLY', true), FILTER_VALIDATE_BOOLEAN),
        'same_site' => env('SESSION_SAME_SITE', 'Lax'),
        'lifetime' => 7200,
    ],
    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 300),
    ],
];
