<?php

return [
    'title' => 'Welcome to SkyblockHub',
    'subtitle' => 'Let\'s get you up to speed',
    
    'steps' => [
        [
            'title' => 'Link Your Profile',
            'description' => 'Connect your Hypixel account to get started',
            'link_minecraft' => [
                'title' => 'Link Minecraft Account',
                'description' => 'Authenticate with your Hypixel profile',
            ],
        ],
        [
            'title' => 'Explore Bazaar',
            'description' => 'Learn how to identify profitable trades',
            'view_bazaar' => [
                'title' => 'View Bazaar',
                'description' => 'See real-time price movements',
            ],
        ],
        [
            'title' => 'Check Your Portfolio',
            'description' => 'Track your investments and networth',
            'view_portfolio' => [
                'title' => 'View Portfolio',
                'description' => 'Analyze your holdings',
            ],
        ],
        [
            'title' => 'Account (Optional)',
            'description' => 'Review account options and billing when available',
            'upgrade' => [
                'title' => 'View plans',
                'description' => 'See optional tiers and refresh limits',
            ],
        ],
    ],
    
    'completed' => 'Onboarding Complete!',
    'skip' => 'Skip',
    'next' => 'Next',
    'finish' => 'Finish',
];
