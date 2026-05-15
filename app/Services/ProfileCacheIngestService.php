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
     * @param  bool  $newPlayersOnly  Skip UUIDs already present in profiles_cache (for growing coverage).
     * @return array<int, string> Normalized 32-char hex UUIDs (no hyphens)
     */
    public function buildIngestQueue(?int $maxTotal = null, bool $newPlayersOnly = false): array
    {
        $cfg = config('hypixel.profile_ingest', []);
        $max = max(1, (int) ($maxTotal ?? $cfg['max_per_run'] ?? 100));

        $seen = [];
        $out = [];
        $cachedUuids = $newPlayersOnly ? $this->cachedMinecraftUuidSet() : [];

        $push = function (string $uuid) use (&$seen, &$out, $max, $newPlayersOnly, $cachedUuids): void {
            $uuid = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $uuid));
            if (strlen($uuid) !== 32 || ! ctype_xdigit($uuid) || isset($seen[$uuid]) || count($out) >= $max) {
                return;
            }
            if ($newPlayersOnly && isset($cachedUuids[$uuid])) {
                return;
            }
            $seen[$uuid] = true;
            $out[] = $uuid;
        };

        if ($newPlayersOnly) {
            if ($cfg['include_hypixel_leaderboards'] ?? true) {
                $payload = $this->hypixelApi->getLeaderboards();
                foreach (self::skyblockUuidsFromLeaderboardsPayload($payload) as $uuid) {
                    $push($uuid);
                }
            }

            if ($cfg['include_guild_crawl'] ?? false) {
                foreach ($this->guildCrawlMemberUuids($cfg, $newPlayersOnly) as $uuid) {
                    $push($uuid);
                }
            }

            foreach ($cfg['extra_uuids'] ?? [] as $uuid) {
                $push((string) $uuid);
            }

            return $out;
        }

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

        if ($cfg['include_guild_crawl'] ?? false) {
            foreach ($this->guildCrawlMemberUuids($cfg, false) as $uuid) {
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
     * @return array<string, true> compact lowercase UUID => true
     */
    public function cachedMinecraftUuidSet(): array
    {
        $set = [];

        ProfileCache::query()
            ->whereNotNull('minecraft_uuid')
            ->select('minecraft_uuid')
            ->distinct()
            ->pluck('minecraft_uuid')
            ->each(function ($uuid) use (&$set): void {
                $compact = strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string) $uuid));
                if (strlen($compact) === 32 && ctype_xdigit($compact)) {
                    $set[$compact] = true;
                }
            });

        return $set;
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

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<int, string>
     */
    private function guildCrawlMemberUuids(array $cfg, bool $newPlayersOnly): array
    {
        $crawlCfg = is_array($cfg['guild_crawl'] ?? null) ? $cfg['guild_crawl'] : [];
        $maxGuilds = max(1, (int) ($crawlCfg['max_guilds_per_run'] ?? 15));
        $seedLimit = max(1, (int) ($crawlCfg['seed_limit'] ?? 60));
        $memberCap = max(1, (int) ($crawlCfg['max_members_per_run'] ?? 500));

        $result = app(GuildCrawlService::class)->collectMemberUuids(
            $maxGuilds,
            $seedLimit,
            $newPlayersOnly,
        );

        return array_slice($result['member_uuids'], 0, $memberCap);
    }
}
