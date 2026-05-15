<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pre-materialized leaderboard (server-side)
    |--------------------------------------------------------------------------
    |
    | When `site_leaderboard_players` is populated (see `leaderboard:rebuild-snapshot`),
    | API reads this flat table instead of aggregating JSON from profiles_cache on every
    | request. Rebuild on a schedule (e.g. hourly) for predictable latency at scale.
    |
    */
    'use_snapshot_when_populated' => (bool) env('LEADERBOARD_USE_SNAPSHOT', true),

    'players_table' => 'site_leaderboard_players',

    'players_staging_table' => 'site_leaderboard_players_staging',

];
