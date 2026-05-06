<?php

return [
    'owners' => [
        'frontend' => env('OPS_OWNER_FRONTEND', 'frontend'),
        'backend' => env('OPS_OWNER_BACKEND', 'backend'),
        'growth' => env('OPS_OWNER_GROWTH', 'growth'),
        'ops' => env('OPS_OWNER_OPS', 'ops'),
    ],

    'sentry' => [
        'routing' => [
            'frontend_runtime' => [
                'owner' => env('OPS_OWNER_FRONTEND', 'frontend'),
                'issue_types' => ['javascript_error', 'vue_render_error', 'unhandledrejection'],
                'source' => 'client',
                'severity' => 'error',
                'notify' => 'frontend',
            ],
            'backend_runtime' => [
                'owner' => env('OPS_OWNER_BACKEND', 'backend'),
                'issue_types' => ['php_exception', 'http_5xx', 'job_failure'],
                'source' => 'server',
                'severity' => 'error',
                'notify' => 'backend',
            ],
            'performance_regression' => [
                'owner' => env('OPS_OWNER_OPS', 'ops'),
                'issue_types' => ['slow_transaction', 'slow_endpoint', 'frontend_perf'],
                'source' => 'server_and_client',
                'severity' => 'warning',
                'notify' => 'ops',
            ],
            'release_regression' => [
                'owner' => env('OPS_OWNER_OPS', 'ops'),
                'issue_types' => ['new_release_error_spike', 'release_regression'],
                'source' => 'release',
                'severity' => 'error',
                'notify' => 'ops',
            ],
        ],

        'triage' => [
            'ack_minutes' => 15,
            'mitigate_hours' => 4,
            'escalate_hours' => 24,
            'rules' => [
                'Check environment and release first; ignore alerts that do not match production tags.',
                'Assign frontend issues to frontend, backend errors to backend, and cross-cutting or release regressions to ops.',
                'If an issue reproduces in the latest release, treat it as regression until proven otherwise.',
                'If the issue blocks checkout, login, or dashboard rendering, escalate immediately and start mitigation.',
            ],
        ],

        'runbook' => [
            'first_15_minutes' => [
                'Open the Sentry issue and confirm environment, release, and owner tags.',
                'Check whether the issue is already grouped with a known incident or deploy.',
                'Reproduce the issue, if possible, and attach the relevant stack trace or browser steps.',
            ],
            'first_4_hours' => [
                'Classify the incident as frontend, backend, performance, or release regression.',
                'Create a mitigation or rollback plan if the issue touches checkout, login, or dashboard access.',
                'Add a note with the owner, next action, and expected follow-up time.',
            ],
            'next_day' => [
                'Confirm the fix is deployed and the issue is silent in Sentry.',
                'Add a short postmortem note for recurring incidents.',
                'Adjust alert thresholds or routing rules if the incident was noisy or misclassified.',
            ],
        ],
    ],

    'alerts' => [
        'conversion_drop_threshold_pp' => (float) env('OPS_CONVERSION_DROP_THRESHOLD_PP', 5.0),
        'conversion_relative_drop_threshold_pct' => (float) env('OPS_CONVERSION_RELATIVE_DROP_THRESHOLD_PCT', 25.0),
        'minimum_previous_sample' => (int) env('OPS_MINIMUM_PREVIOUS_SAMPLE', 20),
        'slow_endpoint_threshold_ms' => (int) env('OPS_SLOW_ENDPOINT_THRESHOLD_MS', 2000),
        'error_spike_threshold' => (int) env('OPS_ERROR_SPIKE_THRESHOLD', 10),
    ],
];
