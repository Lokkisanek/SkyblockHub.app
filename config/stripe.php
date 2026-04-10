<?php

return [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'trial_days' => (int) env('STRIPE_TRIAL_DAYS', 7),

    'plans' => [
        'vip' => [
            'name' => 'VIP',
            'price' => '$4.99',
            'price_id' => env('STRIPE_PRICE_VIP_MONTHLY'),
        ],
        'mvp' => [
            'name' => 'MVP',
            'price' => '$8.99',
            'price_id' => env('STRIPE_PRICE_MVP_MONTHLY'),
        ],
    ],
];
