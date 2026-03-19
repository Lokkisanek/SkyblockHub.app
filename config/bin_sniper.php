<?php

return [
    'version' => '1.0',
    'profit_thresholds' => [
        'min_profit_coins' => 500000,
        'min_profit_percentage' => 10.0,
    ],
    'statistics' => [
        'iqr_multiplier' => 1.5,
        'manipulation_ratio_max' => 3.0,
        'min_daily_volume' => 5,
    ],
    'tax_brackets' => [
        ['max_price' => 10000000, 'rate' => 0.01],
        ['max_price' => 100000000, 'rate' => 0.02],
        ['max_price' => null, 'rate' => 0.03],
    ],
    'component_weights' => [
        'recombobulator' => 0.60,
        'hot_potato_book' => 0.50,
        'fuming_potato_book' => 0.50,
        't6_t7_enchants' => 0.70,
        'ultimate_enchants' => 0.80,
        'master_stars' => 0.75,
        'gemstones_flawless' => 0.85,
        'gemstones_perfect' => 0.90,
        'dyes' => 0.95,
        'art_of_war' => 0.75,
    ],
    // Bazaar product IDs for fast component pricing where available.
    'component_market_ids' => [
        'recombobulator' => 'RECOMBOBULATOR_3000',
        'hot_potato_book' => 'HOT_POTATO_BOOK',
        'fuming_potato_book' => 'FUMING_POTATO_BOOK',
        'art_of_war' => 'THE_ART_OF_WAR',
    ],
    'ultimate_enchant_prefixes' => [
        'ultimate_',
    ],
];
