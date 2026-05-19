<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Centralized proxy for all Hypixel API calls.
 *
 * Every outgoing request goes through this service which provides:
 *  - Per-endpoint response caching with configurable TTL
 *  - Stale-while-revalidate: serves expired cache on API failure
 *  - Global rate limiting (sliding window) on every outbound Hypixel request
 *  - Retry with backoff for 5xx and transport errors only (429 → stale, no retry)
 *  - Structured logging for monitoring
 */
class HypixelApiProxy
{
    private const BASE_URL = 'https://api.hypixel.net';

    private const RATE_LIMIT_CACHE_KEY = 'hypixel:proxy:rate_window';

    // ─── Public API ──────────────────────────────────────────────────

    /**
     * GET /v2/skyblock/profiles
     *
     * @return array{success: bool, profiles: array}|null
     */
    public function getProfiles(string $uuid): ?array
    {
        $data = $this->cachedRequest('profiles', '/v2/skyblock/profiles', [
            'uuid' => $uuid,
        ], needsKey: true, cacheKeySuffix: $uuid);

        return $data;
    }

    /**
     * GET /v2/player
     *
     * @return array|null The "player" object or null.
     */
    public function getPlayer(string $uuid): ?array
    {
        $data = $this->cachedRequest('player', '/v2/player', [
            'uuid' => $uuid,
        ], needsKey: true, cacheKeySuffix: $uuid);

        return $data['player'] ?? null;
    }

    /**
     * GET /v2/skyblock/museum
     *
     * @return array|null The member's museum data or null.
     */
    public function getMuseum(string $profileId, string $uuid): ?array
    {
        $data = $this->cachedRequest('museum', '/v2/skyblock/museum', [
            'profile' => $profileId,
        ], needsKey: true, cacheKeySuffix: "{$profileId}:{$uuid}");

        return $data['members'][$uuid] ?? null;
    }

    /**
     * GET /v2/skyblock/bazaar  (no API key required)
     *
     * @return array{success: bool, products: array}|null
     */
    public function getBazaar(): ?array
    {
        return $this->cachedRequest('bazaar', '/v2/skyblock/bazaar', [],
            needsKey: false);
    }

    /**
     * GET /v2/skyblock/auctions  (no API key required)
     *
     * @return array|null Raw auction page response.
     */
    public function getAuctions(int $page = 0): ?array
    {
        return $this->cachedRequest('auctions', '/v2/skyblock/auctions', [
            'page' => $page,
        ], needsKey: false, cacheKeySuffix: "page:{$page}");
    }

    /**
     * GET /v2/resources/skyblock/election  (no API key required)
     */
    public function getElection(): ?array
    {
        return $this->cachedRequest('election', '/v2/resources/skyblock/election', [],
            needsKey: false);
    }

    /**
     * GET /v2/resources/skyblock/collections  (no API key required)
     */
    public function getCollections(): ?array
    {
        return $this->cachedRequest('collections', '/v2/resources/skyblock/collections', [],
            needsKey: false);
    }

    /**
     * GET /v2/resources/skyblock/items  (no API key required)
     */
    public function getItems(): ?array
    {
        return $this->cachedRequest('items', '/v2/resources/skyblock/items', [],
            needsKey: false);
    }

    /**
     * GET /v2/leaderboards — current Hypixel leaderboards (requires API key).
     *
     * @return array{success?: bool, leaderboards?: array<string, mixed>}|null
     */
    public function getLeaderboards(): ?array
    {
        return $this->cachedRequest('leaderboards', '/v2/leaderboards', [],
            needsKey: true, cacheKeySuffix: 'global');
    }

    /**
     * GET /v2/guild — guild by player UUID, guild id, or name (exactly one param).
     *
     * @return array{success?: bool, guild?: array<string, mixed>}|null
     */
    public function getGuild(?string $player = null, ?string $id = null, ?string $name = null): ?array
    {
        $params = array_filter([
            'player' => $player,
            'id' => $id,
            'name' => $name,
        ], static fn ($v): bool => $v !== null && $v !== '');

        if (count($params) !== 1) {
            return null;
        }

        $suffix = match (true) {
            $player !== null && $player !== '' => 'player:'.strtolower(preg_replace('/[^0-9a-fA-F]/', '', $player)),
            $id !== null && $id !== '' => 'id:'.$id,
            default => 'name:'.mb_strtolower((string) $name),
        };

        return $this->cachedRequest('guild', '/v2/guild', $params,
            needsKey: true, cacheKeySuffix: $suffix);
    }

    // ─── Internals ───────────────────────────────────────────────────

    /**
     * Execute a cached, rate-limited request.
     */
    private function cachedRequest(
        string $endpoint,
        string $path,
        array $params,
        bool $needsKey = false,
        string $cacheKeySuffix = '',
        ?int $cacheTtlOverride = null,
    ): ?array {
        $ttl = $cacheTtlOverride ?? (int) config("hypixel.cache_ttl.{$endpoint}", 300);
        $staleGrace = (int) config('hypixel.stale_grace', 1800);
        $cacheKey = $this->buildCacheKey($endpoint, $cacheKeySuffix);

        // ── 1. Check cache ───────────────────────────────────────────
        if ($ttl > 0) {
            $cached = $this->getFromCache($cacheKey);

            if ($cached !== null) {
                $age = time() - ($cached['_ts'] ?? 0);

                // Fresh → return immediately.
                if ($age <= $ttl) {
                    return $cached['_data'];
                }

                // Stale but within grace → try refresh, fall back to stale.
                // (we continue to the API call below)
            }
        }

        // ── 2. Rate limit check (all endpoints — keyless auction scans must count too)
        if (! $this->consumeRateToken()) {
            Log::warning('HypixelApiProxy: rate limit exhausted, serving stale', [
                'endpoint' => $endpoint,
                'suffix' => $cacheKeySuffix,
            ]);

            return $this->getStaleData($cacheKey, $staleGrace);
        }

        // ── 3. Make HTTP request with retries ────────────────────────
        $maxRetries = (int) config('hypixel.max_retries', 2);
        $timeout = (int) config('hypixel.timeout', 8);
        $connectTimeout = (int) config('hypixel.connect_timeout', 3);
        $apiKey = $needsKey ? trim((string) config('hypixel.api_key', '')) : '';

        if ($needsKey && empty($apiKey)) {
            Log::error('HypixelApiProxy: API key not configured');

            return $this->getStaleData($cacheKey, $staleGrace);
        }

        $queryParams = $params;
        if ($needsKey) {
            $queryParams['key'] = $apiKey;
        }

        $result = null;
        $lastError = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->acceptJson()
                    ->withHeaders(['User-Agent' => config('hypixel.user_agent', 'SkyblockHub/1.0')])
                    ->get(self::BASE_URL.$path, $queryParams);

                if ($response->status() === 429) {
                    Log::warning('HypixelApiProxy: 429 rate-limited — stopping retries, serving stale only', [
                        'endpoint' => $endpoint,
                    ]);
                    $this->depleteRateTokens();

                    return $this->getStaleData($cacheKey, $staleGrace);
                }

                if ($response->serverError()) {
                    $wait = (int) pow(2, $attempt);
                    Log::warning('HypixelApiProxy: server error', [
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'attempt' => $attempt + 1,
                    ]);

                    if ($attempt < $maxRetries) {
                        sleep($wait);

                        continue;
                    }

                    return $this->getStaleData($cacheKey, $staleGrace);
                }

                if (! $response->successful()) {
                    Log::error('HypixelApiProxy: unexpected status', [
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 300),
                    ]);

                    return $this->getStaleData($cacheKey, $staleGrace);
                }

                $json = $response->json() ?? [];

                if (($json['success'] ?? true) === false) {
                    $cause = (string) ($json['cause'] ?? 'unknown');
                    $isThrottle = ($json['throttle'] ?? false) === true
                        || stripos($cause, 'throttle') !== false;

                    if ($isThrottle) {
                        Log::warning('HypixelApiProxy: API throttle (success=false)', [
                            'endpoint' => $endpoint,
                            'cause' => $cause,
                        ]);
                        $this->depleteRateTokens();
                    } else {
                        Log::error('HypixelApiProxy: API returned success=false', [
                            'endpoint' => $endpoint,
                            'cause' => $cause,
                        ]);
                    }

                    // Guild name lookups: return the API body so callers can show "Guild not found" etc.
                    if ($endpoint === 'guild' && ! $isThrottle) {
                        if ($ttl > 0) {
                            $this->putToCache($cacheKey, $json, min($ttl, 120));
                        }

                        return $json;
                    }

                    return $this->getStaleData($cacheKey, $staleGrace);
                }

                $result = $json;
                break;

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('HypixelApiProxy: HTTP exception', [
                    'endpoint' => $endpoint,
                    'error' => $lastError,
                    'attempt' => $attempt + 1,
                ]);

                if ($attempt < $maxRetries) {
                    sleep((int) pow(2, $attempt));

                    continue;
                }
            }
        }

        if ($result === null) {
            Log::error('HypixelApiProxy: all retries exhausted', [
                'endpoint' => $endpoint,
                'lastError' => $lastError,
            ]);

            return $this->getStaleData($cacheKey, $staleGrace);
        }

        // ── 4. Store in cache ────────────────────────────────────────
        if ($ttl > 0) {
            $this->putToCache($cacheKey, $result, $ttl + $staleGrace);
        }

        return $result;
    }

    // ─── Rate Limiting (sliding window counter) ──────────────────────

    /**
     * Try to consume one request token. Returns false if limit exceeded.
     */
    private function consumeRateToken(): bool
    {
        $limit = (int) config('hypixel.rate_limit', 120);
        $windowKey = self::RATE_LIMIT_CACHE_KEY;

        $store = $this->cacheStore();

        $current = (int) $store->get($windowKey, 0);

        if ($current >= $limit) {
            return false;
        }

        // Increment atomically. TTL = 60s for the sliding window.
        if ($current === 0) {
            $store->put($windowKey, 1, 60);
        } else {
            $store->increment($windowKey);
        }

        return true;
    }

    /**
     * Mark rate limit as depleted (we received a 429).
     */
    private function depleteRateTokens(): void
    {
        $limit = (int) config('hypixel.rate_limit', 120);
        $this->cacheStore()->put(self::RATE_LIMIT_CACHE_KEY, $limit, 60);
    }

    // ─── Cache Helpers ───────────────────────────────────────────────

    private function buildCacheKey(string $endpoint, string $suffix): string
    {
        $key = "hypixel:proxy:{$endpoint}";
        if ($suffix !== '') {
            $key .= ':'.$suffix;
        }

        return $key;
    }

    private function getFromCache(string $key): ?array
    {
        try {
            $value = $this->cacheStore()->get($key);

            return is_array($value) ? $value : null;
        } catch (\Throwable $e) {
            Log::warning('HypixelApiProxy: cache read failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function putToCache(string $key, array $data, int $ttlSeconds): void
    {
        $wrapped = [
            '_ts' => time(),
            '_data' => $data,
        ];

        try {
            $this->cacheStore()->put($key, $wrapped, $ttlSeconds);
        } catch (\Throwable $e) {
            Log::warning('HypixelApiProxy: cache write failed', ['error' => $e->getMessage()]);
            // Try file store as fallback.
            try {
                Cache::store('file')->put($key, $wrapped, $ttlSeconds);
            } catch (\Throwable) {
                // silently fail
            }
        }
    }

    /**
     * Return stale data if it exists and is within the grace period.
     */
    private function getStaleData(string $cacheKey, int $staleGrace): ?array
    {
        $cached = $this->getFromCache($cacheKey);

        if ($cached === null) {
            return null;
        }

        $age = time() - ($cached['_ts'] ?? 0);

        if ($age <= $staleGrace + ($this->getTtlForKey($cacheKey))) {
            Log::info('HypixelApiProxy: serving stale data', [
                'key' => $cacheKey,
                'age_seconds' => $age,
            ]);

            return $cached['_data'];
        }

        return null;
    }

    /**
     * Infer the configured TTL from the cache key prefix.
     */
    private function getTtlForKey(string $cacheKey): int
    {
        // Key format: hypixel:proxy:{endpoint}:...
        $parts = explode(':', $cacheKey);
        $endpoint = $parts[2] ?? 'profiles';

        return (int) config("hypixel.cache_ttl.{$endpoint}", 300);
    }

    private function cacheStore(): Repository
    {
        $store = config('cache.default', 'file');

        try {
            return Cache::store($store);
        } catch (\Throwable) {
            return Cache::store('file');
        }
    }
}
