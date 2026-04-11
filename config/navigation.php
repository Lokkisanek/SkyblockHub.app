<?php

return [
    // Toggle visibility of admin-only experimental modules in header navigation.
    'experimental_modules' => [
        'crafting' => env('NAV_EXPERIMENTAL_CRAFTING_ENABLED', false),
        'dungeon_party' => env('NAV_EXPERIMENTAL_DUNGEON_PARTY_ENABLED', false),
        'portfolio' => env('NAV_EXPERIMENTAL_PORTFOLIO_ENABLED', false),
        'bin_sniper' => env('NAV_EXPERIMENTAL_BIN_SNIPER_ENABLED', false),
    ],
];