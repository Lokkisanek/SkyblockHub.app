<?php

return [
    'funnel_enabled' => (bool) env('FUNNEL_ANALYTICS_ENABLED', true),

    'allowed_events' => [
        'onboarding_view',
        'onboarding_step_complete',
        'onboarding_dismiss',
        'landing_cta_click',
        'billing_view',
        'trial_start',
        'checkout_start',
        'checkout_success',
        'subscription_cancel',
        'upgrade_prompt_impression',
        'upgrade_prompt_cta',
        'upgrade_prompt_compare',
    ],
];
