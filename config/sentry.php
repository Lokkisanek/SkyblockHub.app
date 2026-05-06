<?php

return [
    'dsn' => env('SENTRY_DSN'),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    'release' => env('APP_RELEASE', env('SENTRY_RELEASE')),
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),
    'send_default_pii' => false,
    'in_app_exclude' => [
        base_path('vendor'),
    ],
];
