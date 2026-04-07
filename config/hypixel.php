<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hypixel API Key
    |--------------------------------------------------------------------------
    */
    'api_key' => env('HYPIXEL_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Global Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum outgoing requests per minute to the Hypixel API.
    | Hypixel allows 300/min per key. We stay well below that.
    |
    */
    'rate_limit' => (int) env('HYPIXEL_RATE_LIMIT', 120),

    /*
    |--------------------------------------------------------------------------
    | Per-endpoint Cache TTLs (seconds)
    |--------------------------------------------------------------------------
    |
    | How long each endpoint's response is cached before a fresh API call.
    | Setting to 0 disables caching for that endpoint.
    |
    */
    'cache_ttl' => [
        'profiles'    => (int) env('HYPIXEL_CACHE_PROFILES', 300),      // 5 min
        'player'      => (int) env('HYPIXEL_CACHE_PLAYER', 600),        // 10 min
        'museum'      => (int) env('HYPIXEL_CACHE_MUSEUM', 300),        // 5 min
        'bazaar'      => (int) env('HYPIXEL_CACHE_BAZAAR', 60),         // 1 min (fetched every 5 anyway)
        'auctions'    => (int) env('HYPIXEL_CACHE_AUCTIONS', 120),      // 2 min
        'election'    => (int) env('HYPIXEL_CACHE_ELECTION', 120),      // 2 min
        'collections' => (int) env('HYPIXEL_CACHE_COLLECTIONS', 86400), // 24h
        'items'       => (int) env('HYPIXEL_CACHE_ITEMS', 86400),       // 24h
    ],

    /*
    |--------------------------------------------------------------------------
    | Stale-While-Revalidate Grace (seconds)
    |--------------------------------------------------------------------------
    |
    | When a fresh API call fails (timeout, rate-limited, 5xx), serve stale
    | cached data up to this many seconds past its TTL. 0 = no stale grace.
    |
    */
    'stale_grace' => (int) env('HYPIXEL_STALE_GRACE', 1800), // 30 min

    /*
    |--------------------------------------------------------------------------
    | HTTP Settings
    |--------------------------------------------------------------------------
    */
    'timeout'         => (int) env('HYPIXEL_TIMEOUT', 8),
    'connect_timeout' => (int) env('HYPIXEL_CONNECT_TIMEOUT', 3),
    'max_retries'     => (int) env('HYPIXEL_MAX_RETRIES', 2),
    'user_agent'      => env('HYPIXEL_USER_AGENT', 'SkyblockHub/1.0'),

];
