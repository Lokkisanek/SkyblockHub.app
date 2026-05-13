<?php

namespace App\Services;

use App\Models\ProfileCache;
use App\Models\User;

/**
 * Builds the UUID queue for scheduled profile cache ingest.
 */
class ProfileCacheIngestService
{
    public function __construct(
        private readonly HypixelApiProxy $hypixelApi,
    ) {}

    /**
     * @param  int|null  $maxTotal  Max UUIDs in the queue (null = profile_ingest.max_per_run from config).
     * @return array<int, string> Normalized 32-char hex UUIDs (no hyphens)
     */
    public function buildIngestQueue(?int $maxTotal = null): array
    {
        $cfg = config('hypixel.profile_ingest', []);
        $max = max(1, (int) ($maxTotal ?? $cfg['max_per_run'] ?? 100));

        $seen = [];
        $out = [];

        $push = function (string $uuid) use (&$seen, &$out, $max): void {
            $uuid = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $uuid));
            if (strlen($uuid) !== 32 || ! ctype_xdigit($uuid) || isset($seen[$uuid]) || count($out) >= $max) {
                return;
            }
            $seen[$uuid] = true;
            $out[] = $uuid;
        };

        if ($cfg['include_site_leaderboard_top'] ?? true) {
            $topSort = (string) ($cfg['site_leaderboard_top_sort'] ?? 'level');
            $topDir = (string) ($cfg['site_leaderboard_top_direction'] ?? 'desc');
            $topLimit = max(0, (int) ($cfg['site_leaderboard_top_limit'] ?? 8000));
            if ($topLimit > 0) {
                foreach (SiteLeaderboardTopUuidsQuery::run($topLimit, $topSort, $topDir) as $uuid) {
                    $push($uuid);
                }
            }
        }

        if ($cfg['include_hypixel_leaderboards'] ?? true) {
            $payload = $this->hypixelApi->getLeaderboards();
            foreach (self::skyblockUuidsFromLeaderboardsPayload($payload) as $uuid) {
                $push($uuid);
            }
        }

        if ($cfg['include_linked_users'] ?? true) {
            foreach ($this->linkedMinecraftUuids() as $uuid) {
                $push($uuid);
            }
        }

        if ($cfg['include_stale_cache'] ?? true) {
            foreach ($this->staleSelectedProfileUuids((int) ($cfg['stale_after_days'] ?? 7)) as $uuid) {
                $push($uuid);
            }
        }

        foreach ($cfg['extra_uuids'] ?? [] as $uuid) {
            $push((string) $uuid);
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function staleSelectedProfileUuids(int $olderThanDays): array
    {
        $threshold = now()->subDays(max(1, $olderThanDays));

        return ProfileCache::query()
            ->where('selected', true)
            ->where(function ($q) use ($threshold): void {
                $q->whereNull('fetched_at')
                    ->orWhere('fetched_at', '<', $threshold);
            })
            ->orderBy('fetched_at')
            ->limit(400)
            ->pluck('minecraft_uuid')
            ->map(fn ($u): string => strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string) $u)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function linkedMinecraftUuids(): array
    {
        return User::query()
            ->where('is_mc_linked', true)
            ->whereNotNull('minecraft_uuid')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->pluck('minecraft_uuid')
            ->map(fn ($u): string => strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string) $u)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Collect UUIDs from Hypixel /v2/leaderboards for boards whose name contains "SKYBLOCK".
     *
     * @param  array<string, mixed>|null  $response
     * @return array<int, string>
     */
    public static function skyblockUuidsFromLeaderboardsPayload(?array $response): array
    {
        if (! is_array($response) || empty($response['leaderboards']) || ! is_array($response['leaderboards'])) {
            return [];
        }

        $found = [];

        foreach ($response['leaderboards'] as $boardName => $rows) {
            if (! is_string($boardName) || stripos($boardName, 'SKYBLOCK') === false) {
                continue;
            }

            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $uuid = $row['uuid'] ?? null;
                if (! is_string($uuid)) {
                    continue;
                }

                $compact = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $uuid));
                if (strlen($compact) === 32 && ctype_xdigit($compact)) {
                    $found[$compact] = true;
                }
            }
        }

        return array_keys($found);
    }
}
