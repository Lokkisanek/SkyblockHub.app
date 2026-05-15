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
    | Maximum outgoing HTTP requests per minute to api.hypixel.net (all
    | endpoints, keyed and keyless). Prevents e.g. auction page scans from
    | bypassing limits. Hypixel allows 300/min per key; stay well below.
    |
    */
    'rate_limit' => (int) env('HYPIXEL_RATE_LIMIT', 120),

    /*
    |--------------------------------------------------------------------------
    | Auction house scan (bin:fetch)
    |--------------------------------------------------------------------------
    |
    | Caps and pacing for paginated /v2/skyblock/auctions — each page is one
    | HTTP call. Without a cap, a single scheduler run can issue hundreds of
    | requests in a few minutes.
    |
    */
    'auction_fetch_max_pages' => (int) env('HYPIXEL_AUCTION_FETCH_MAX_PAGES', 120),
    'auction_fetch_delay_ms' => (int) env('HYPIXEL_AUCTION_FETCH_DELAY_MS', 650),

    /*
    |--------------------------------------------------------------------------
    | Profile networth (Node.js skyhelper-networth)
    |--------------------------------------------------------------------------
    |
    | Seconds PHP waits for scripts/networth.cjs. If the process times out or
    | Node is missing, item values fall back to bazaar/BIN base prices only
    | (no enchants, stars, gems, etc.).
    |
    */
    'networth_node_timeout_sec' => (float) env('HYPIXEL_NETWORTH_NODE_TIMEOUT_SEC', 60),

    /*
    | Absolute path to the Node binary for scripts/networth.cjs. Leave empty to
    | auto-detect (/usr/bin/node, /usr/local/bin/node). php-fpm often has a
    | minimal PATH, so bare "node" fails even when Node is installed.
    |
    */
    'networth_node_binary' => env('HYPIXEL_NETWORTH_NODE_BINARY', ''),

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
        'profiles' => (int) env('HYPIXEL_CACHE_PROFILES', 300),      // 5 min
        'player' => (int) env('HYPIXEL_CACHE_PLAYER', 600),        // 10 min
        'museum' => (int) env('HYPIXEL_CACHE_MUSEUM', 300),        // 5 min
        'bazaar' => (int) env('HYPIXEL_CACHE_BAZAAR', 60),         // 1 min (fetched every 5 anyway)
        'auctions' => (int) env('HYPIXEL_CACHE_AUCTIONS', 120),      // 2 min
        'election' => (int) env('HYPIXEL_CACHE_ELECTION', 120),      // 2 min
        'collections' => (int) env('HYPIXEL_CACHE_COLLECTIONS', 86400), // 24h
        'items' => (int) env('HYPIXEL_CACHE_ITEMS', 86400),       // 24h
        'leaderboards' => (int) env('HYPIXEL_CACHE_LEADERBOARDS', 900), // 15 min (ingest uses snapshot)
        'guild' => (int) env('HYPIXEL_CACHE_GUILD', 3600), // 1 h
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled profiles_cache ingest (leaderboard coverage)
    |--------------------------------------------------------------------------
    |
    | Periodically pulls SkyBlock profiles from Hypixel. Queue is built in priority order:
    | (1) top rows from our site leaderboard (profiles_cache), (2) Hypixel /v2/leaderboards SKYBLOCK
    | UUIDs, (3) linked app accounts, (4) stale cache rows, (5) PROFILE_INGEST_EXTRA_UUIDS.
    | Use `php artisan profiles:ingest-bulk` for large one-off backfills (see bulk_safe_cap).
    | Add `--new-only` to ingest only UUIDs not yet in profiles_cache (Hypixel SKYBLOCK leaderboards).
    | Guild crawl: `php artisan profiles:crawl-guilds` or PROFILE_INGEST_GUILD_CRAWL=true (uses seeds → /v2/guild).
    |
    */
    'profile_ingest' => [
        'enabled' => (bool) env('PROFILE_INGEST_ENABLED', false),
        // UUIDs refreshed per `profiles:ingest-scheduled` run (each does ~2 Hypixel calls: profiles + player).
        'max_per_run' => (int) env('PROFILE_INGEST_MAX_PER_RUN', 100),
        // Bulk/guild crawl: skip inventories + Node networth (leaderboard stats only; full data on profile view).
        'lightweight_bulk' => (bool) env('PROFILE_INGEST_LIGHTWEIGHT_BULK', true),
        'delay_ms' => (int) env('PROFILE_INGEST_DELAY_MS', 500),
        'stale_after_days' => (int) env('PROFILE_INGEST_STALE_DAYS', 7),
        'include_hypixel_leaderboards' => (bool) env('PROFILE_INGEST_HYPIXEL_LEADERBOARDS', true),
        'include_linked_users' => (bool) env('PROFILE_INGEST_LINKED_USERS', true),
        'include_stale_cache' => (bool) env('PROFILE_INGEST_STALE_CACHE', true),
        // Our DB leaderboard (profiles_cache, selected row) — prioritized first so top players refresh every run.
        'include_site_leaderboard_top' => (bool) env('PROFILE_INGEST_SITE_TOP', true),
        'site_leaderboard_top_sort' => (string) env('PROFILE_INGEST_SITE_TOP_SORT', 'level'),
        'site_leaderboard_top_direction' => (string) env('PROFILE_INGEST_SITE_TOP_DIRECTION', 'desc'),
        'site_leaderboard_top_limit' => (int) env('PROFILE_INGEST_SITE_TOP_LIMIT', 8000),
        // Hard cap for `profiles:ingest-bulk --limit=` (safety).
        'bulk_safe_cap' => (int) env('PROFILE_INGEST_BULK_SAFE_CAP', 25000),
        'extra_uuids' => array_values(array_filter(array_map(
            static fn (string $s): string => strtolower(preg_replace('/[^0-9a-fA-F]/', '', $s)),
            array_map('trim', explode(',', (string) env('PROFILE_INGEST_EXTRA_UUIDS', '')))
        ), static fn (string $s): bool => strlen($s) === 32 && ctype_xdigit($s))),
        'include_guild_crawl' => (bool) env('PROFILE_INGEST_GUILD_CRAWL', false),
        'guild_crawl' => [
            'max_guilds_per_run' => (int) env('PROFILE_INGEST_GUILD_MAX_GUILDS', 15),
            'seed_limit' => (int) env('PROFILE_INGEST_GUILD_SEED_LIMIT', 60),
            'max_members_per_run' => (int) env('PROFILE_INGEST_GUILD_MAX_MEMBERS', 500),
            'include_profiles_cache_seeds' => (bool) env('PROFILE_INGEST_GUILD_CACHE_SEEDS', true),
            'guild_names' => (string) env('PROFILE_INGEST_GUILD_NAMES', ''),
        ],
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
    'timeout' => (int) env('HYPIXEL_TIMEOUT', 8),
    'connect_timeout' => (int) env('HYPIXEL_CONNECT_TIMEOUT', 3),
    /* Retries: transport failures and 5xx only (429 returns stale immediately, no retry). */
    'max_retries' => (int) env('HYPIXEL_MAX_RETRIES', 2),
    'user_agent' => env('HYPIXEL_USER_AGENT', 'SkyblockHub/1.0'),

    /*
    |--------------------------------------------------------------------------
    | Developer dashboard — site ownership verification
    |--------------------------------------------------------------------------
    |
    | When Hypixel gives you a verification token, set HYPIXEL_SITE_VERIFICATION.
    | It is exposed as:
    |   - <meta name="..."> on every Inertia page (see resources/views/app.blade.php)
    |   - Plain text at GET /hypixel-verification.txt (if non-empty)
    |
    | If Hypixel uses a different meta name, set HYPIXEL_VERIFICATION_META_NAME.
    |
    */
    'site_verification' => [
        'meta_name' => env('HYPIXEL_VERIFICATION_META_NAME', 'hypixel-site-verification'),
        'meta_content' => trim((string) env('HYPIXEL_SITE_VERIFICATION', '')),
    ],

];
