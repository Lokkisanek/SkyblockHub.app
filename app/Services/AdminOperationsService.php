<?php

namespace App\Services;

use App\Models\BazaarPrice;
use App\Models\ProfileCache;
use App\Models\User;
use App\Support\AdminGuildCrawlStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class AdminOperationsService
{
    private const HYPIXEL_HEALTH_CACHE = 'admin:hypixel_api_health';

    /**
     * @return array<string, mixed>
     */
    public function buildSnapshot(): array
    {
        return [
            'hypixel' => $this->hypixelApiHealth(),
            'profiles' => $this->profilesCacheStats(),
            'leaderboard' => $this->leaderboardStats(),
            'users' => $this->userStats(),
            'bazaar' => $this->bazaarStats(),
            'queue' => $this->queueStats(),
            'ingest' => $this->ingestConfig(),
            'guild_crawl' => AdminGuildCrawlStatus::snapshot(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function hypixelApiHealth(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::HYPIXEL_HEALTH_CACHE);
        }

        return Cache::remember(self::HYPIXEL_HEALTH_CACHE, 90, function (): array {
            $key = trim((string) config('hypixel.api_key', ''));
            $checkedAt = now()->toIso8601String();

            if ($key === '') {
                return [
                    'status' => 'no_key',
                    'label' => 'No API key',
                    'message' => 'Set HYPIXEL_API_KEY in .env',
                    'http_status' => null,
                    'cause' => null,
                    'player_count' => null,
                    'checked_at' => $checkedAt,
                ];
            }

            try {
                $response = Http::timeout((int) config('hypixel.timeout', 8))
                    ->connectTimeout((int) config('hypixel.connect_timeout', 3))
                    ->acceptJson()
                    ->withHeaders([
                        'User-Agent' => (string) config('hypixel.user_agent', 'SkyblockHub/1.0'),
                    ])
                    ->get('https://api.hypixel.net/v2/counts', [
                        'key' => $key,
                    ]);

                $json = $response->json() ?? [];
                $success = ($json['success'] ?? false) === true;
                $cause = isset($json['cause']) ? (string) $json['cause'] : null;

                if ($cause && stripos($cause, 'invalid api key') !== false && stripos($cause, 'throttle') === false) {
                    return [
                        'status' => 'invalid_key',
                        'label' => 'Invalid API key',
                        'message' => 'Hypixel rejected this key. Regenerate at developer.hypixel.net, set HYPIXEL_API_KEY in .env (no quotes/spaces), then run php artisan config:clear.',
                        'http_status' => $response->status(),
                        'cause' => $cause,
                        'player_count' => null,
                        'checked_at' => $checkedAt,
                    ];
                }

                if ($response->status() === 429 || ($cause && stripos($cause, 'throttle') !== false)) {
                    return [
                        'status' => 'throttled',
                        'label' => 'Rate limited',
                        'message' => $cause ?: 'Hypixel returned HTTP 429 — daily or per-minute limit.',
                        'http_status' => $response->status(),
                        'cause' => $cause,
                        'player_count' => null,
                        'checked_at' => $checkedAt,
                    ];
                }

                if ($success) {
                    return [
                        'status' => 'ok',
                        'label' => 'Operational',
                        'message' => 'API key accepted. Guild crawl and ingest can run.',
                        'http_status' => $response->status(),
                        'cause' => null,
                        'player_count' => (int) ($json['playerCount'] ?? 0),
                        'checked_at' => $checkedAt,
                    ];
                }

                return [
                    'status' => 'error',
                    'label' => 'API error',
                    'message' => $cause ?: 'Hypixel returned success=false.',
                    'http_status' => $response->status(),
                    'cause' => $cause,
                    'player_count' => null,
                    'checked_at' => $checkedAt,
                ];
            } catch (\Throwable $e) {
                return [
                    'status' => 'error',
                    'label' => 'Unreachable',
                    'message' => $e->getMessage(),
                    'http_status' => null,
                    'cause' => null,
                    'player_count' => null,
                    'checked_at' => $checkedAt,
                ];
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function profilesCacheStats(): array
    {
        $staleDays = max(1, (int) config('hypixel.profile_ingest.stale_after_days', 7));
        $staleBefore = now()->subDays($staleDays);

        $total = ProfileCache::query()->count();
        $selected = ProfileCache::query()->where('selected', true)->count();
        $stale = ProfileCache::query()
            ->where(function ($q) use ($staleBefore): void {
                $q->whereNull('fetched_at')->orWhere('fetched_at', '<', $staleBefore);
            })
            ->count();

        $latest = ProfileCache::query()->max('fetched_at');

        return [
            'total' => $total,
            'selected_profiles' => $selected,
            'stale' => $stale,
            'stale_after_days' => $staleDays,
            'latest_fetched_at' => $latest ? (string) $latest : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leaderboardStats(): array
    {
        $table = (string) config('leaderboard.players_table', 'site_leaderboard_players');

        if (! Schema::hasTable($table)) {
            return [
                'table' => $table,
                'rows' => 0,
                'slice_max_fetched_at' => null,
                'available' => false,
            ];
        }

        return [
            'table' => $table,
            'rows' => (int) DB::table($table)->count(),
            'slice_max_fetched_at' => DB::table($table)->max('slice_max_fetched_at'),
            'available' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userStats(): array
    {
        return [
            'total' => User::query()->count(),
            'discord_linked' => User::query()->whereNotNull('discord_id')->count(),
            'minecraft_linked' => User::query()->where('is_mc_linked', true)->count(),
            'vip_ranked' => User::query()->whereNotNull('app_vip_rank')->where('app_vip_rank', '!=', '')->count(),
            'donators' => User::query()->where('is_donator', true)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bazaarStats(): array
    {
        $latest = BazaarPrice::query()->max('updated_at');
        $products = BazaarPrice::query()->count();

        return [
            'products' => $products,
            'latest_updated_at' => $latest ? (string) $latest : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queueStats(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['failed_jobs' => 0, 'available' => false];
        }

        return [
            'failed_jobs' => (int) DB::table('failed_jobs')->count(),
            'available' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ingestConfig(): array
    {
        $cfg = config('hypixel.profile_ingest', []);
        $guild = is_array($cfg['guild_crawl'] ?? null) ? $cfg['guild_crawl'] : [];

        return [
            'enabled' => (bool) ($cfg['enabled'] ?? false),
            'max_per_run' => (int) ($cfg['max_per_run'] ?? 0),
            'lightweight_bulk' => (bool) ($cfg['lightweight_bulk'] ?? true),
            'delay_ms' => (int) ($cfg['delay_ms'] ?? 0),
            'guild_crawl_enabled' => (bool) ($cfg['include_guild_crawl'] ?? false),
            'guild_max_per_run' => (int) ($guild['max_members_per_run'] ?? 0),
        ];
    }
}
