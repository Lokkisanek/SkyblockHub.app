<?php

return [

    /*
    |--------------------------------------------------------------------------
    | “Online now” window (minutes)
    |--------------------------------------------------------------------------
    |
    | Sessions touching the site within this window count as one concurrent
    | visitor for landing-page social proof (see site_presence table).
    |
    */
    'active_presence_minutes' => (int) env('SOCIAL_PROOF_ACTIVE_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Stale presence row cleanup (hours)
    |--------------------------------------------------------------------------
    |
    | Rows older than this may be deleted when social metrics are recomputed.
    |
    */
    'presence_retention_hours' => (int) env('SOCIAL_PROOF_PRESENCE_RETENTION_HOURS', 48),

];
