<?php

namespace App\Services;

use App\Models\ProfileCache;
use App\Support\AdminGuildCrawlStatus;

/**
 * Discover Hypixel guilds from seed player UUIDs and collect member UUIDs for profile ingest.
 */
final class GuildCrawlService
{
    public function __construct(
        private readonly HypixelApiProxy $hypixelApi,
    ) {}

    /**
     * @param  array<int, string>  $guildNames  Hypixel guild names to fetch via /v2/guild?name=
     * @return array{
     *     guilds_found: int,
     *     member_uuids: array<int, string>,
     *     seeds_scanned: int,
     *     api_calls: int,
     *     seed_sources: array<string, int>,
     *     guild_lookups: array<int, array{name: string, ok: bool, cause: string|null, members: int, hypixel_name: string|null}>,
     * }
     */
    public function collectMemberUuids(
        int $maxGuilds,
        int $seedLimit,
        bool $newPlayersOnly = false,
        array $guildNames = [],
        int $guildLookupDelayMs = 0,
    ): array {
        $maxGuilds = max(1, $maxGuilds);
        $seedLimit = max(0, $seedLimit);
        $guildLookupDelayMs = max(0, $guildLookupDelayMs);

        $cached = $newPlayersOnly ? $this->cachedMinecraftUuidSet() : [];
        $guildsById = [];
        $apiCalls = 0;
        $guildLookups = [];

        $requestedNames = array_values(array_filter(array_map('trim', $guildNames), static fn (string $n): bool => $n !== ''));

        foreach ($requestedNames as $index => $guildName) {
            if (AdminGuildCrawlStatus::shouldCancel()) {
                break;
            }

            if (count($guildsById) >= $maxGuilds) {
                break;
            }

            if ($index > 0 && $guildLookupDelayMs > 0) {
                usleep($guildLookupDelayMs * 1000);
            }

            $payload = $this->hypixelApi->getGuild(name: $guildName);
            $apiCalls++;
            $lookup = self::describeGuildLookup($guildName, $payload);
            $guildLookups[] = $lookup;
            $this->storeGuildPayload($guildsById, $payload);
        }

        $seeds = [];
        $seedSources = [];

        if ($seedLimit > 0) {
            $seedBundle = $this->seedPlayerUuids($seedLimit);
            $seeds = $seedBundle['uuids'];
            $seedSources = $seedBundle['sources'];

            foreach ($seeds as $seedUuid) {
                if (count($guildsById) >= $maxGuilds) {
                    break;
                }

                $payload = $this->hypixelApi->getGuild(player: $seedUuid);
                $apiCalls++;
                $this->storeGuildPayload($guildsById, $payload);
            }
        }

        $memberUuids = [];
        $seen = [];

        foreach ($guildsById as $guild) {
            foreach (self::memberUuidsFromGuild($guild) as $uuid) {
                if (isset($seen[$uuid])) {
                    continue;
                }
                if ($newPlayersOnly && isset($cached[$uuid])) {
                    continue;
                }
                $seen[$uuid] = true;
                $memberUuids[] = $uuid;
            }
        }

        return [
            'guilds_found' => count($guildsById),
            'member_uuids' => $memberUuids,
            'seeds_scanned' => count($seeds),
            'api_calls' => $apiCalls,
            'seed_sources' => $seedSources,
            'guild_lookups' => $guildLookups,
        ];
    }

    /**
     * @return array{name: string, ok: bool, cause: string|null, members: int, hypixel_name: string|null}
     */
    public static function describeGuildLookup(string $requestedName, ?array $payload): array
    {
        if (! is_array($payload)) {
            return [
                'name' => $requestedName,
                'ok' => false,
                'cause' => 'no_response',
                'members' => 0,
                'hypixel_name' => null,
            ];
        }

        if (($payload['success'] ?? false) !== true) {
            $cause = trim((string) ($payload['cause'] ?? 'api_error'));

            return [
                'name' => $requestedName,
                'ok' => false,
                'cause' => $cause !== '' ? $cause : 'api_error',
                'members' => 0,
                'hypixel_name' => null,
            ];
        }

        $guild = $payload['guild'] ?? null;
        if (! is_array($guild)) {
            return [
                'name' => $requestedName,
                'ok' => false,
                'cause' => 'empty_guild',
                'members' => 0,
                'hypixel_name' => null,
            ];
        }

        $hypixelName = isset($guild['name']) ? (string) $guild['name'] : null;
        $members = count(self::memberUuidsFromGuild($guild));

        return [
            'name' => $requestedName,
            'ok' => true,
            'cause' => null,
            'members' => $members,
            'hypixel_name' => $hypixelName,
        ];
    }

    /**
     * @param  array<int, array{name: string, ok: bool, cause: string|null, members: int, hypixel_name: string|null}>  $lookups
     */
    public static function summarizeGuildLookupFailures(array $lookups): string
    {
        $failed = array_values(array_filter($lookups, static fn (array $row): bool => ! ($row['ok'] ?? false)));
        if ($failed === []) {
            return '';
        }

        $noResponse = count(array_filter($failed, static fn (array $r): bool => ($r['cause'] ?? '') === 'no_response'));

        if ($noResponse >= max(1, (int) floor(count($lookups) * 0.5))) {
            return 'Most lookups returned no data — likely Hypixel rate limit. Increase Delay (ms) and retry, or wait a minute.';
        }

        $samples = array_slice(array_map(
            static fn (array $r): string => sprintf('%s (%s)', $r['name'], $r['cause'] ?? '?'),
            $failed,
        ), 0, 4);

        return 'Failed guilds: '.implode('; ', $samples).(count($failed) > 4 ? '…' : '');
    }

    /**
     * @param  array<string, array<string, mixed>>  $guildsById
     * @param  array<string, mixed>|null  $payload
     */
    private function storeGuildPayload(array &$guildsById, ?array $payload): void
    {
        if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
            return;
        }

        $guild = $payload['guild'] ?? null;
        if (! is_array($guild)) {
            return;
        }

        $guildId = (string) ($guild['_id'] ?? '');
        if ($guildId === '' || isset($guildsById[$guildId])) {
            return;
        }

        $guildsById[$guildId] = $guild;
    }

    /**
     * @return array<string, true>
     */
    private function cachedMinecraftUuidSet(): array
    {
        $set = [];

        ProfileCache::query()
            ->whereNotNull('minecraft_uuid')
            ->select('minecraft_uuid')
            ->distinct()
            ->pluck('minecraft_uuid')
            ->each(function ($uuid) use (&$set): void {
                $compact = self::normalizeUuid((string) $uuid);
                if ($compact !== null) {
                    $set[$compact] = true;
                }
            });

        return $set;
    }

    /**
     * Seed UUIDs for guild discovery via ?player=.
     *
     * @return array{uuids: array<int, string>, sources: array<string, int>}
     */
    public function seedPlayerUuids(int $limit): array
    {
        $cfg = config('hypixel.profile_ingest', []);
        $crawlCfg = is_array($cfg['guild_crawl'] ?? null) ? $cfg['guild_crawl'] : [];
        $seen = [];
        $out = [];
        $sources = [
            'hypixel_leaderboards' => 0,
            'site_leaderboard' => 0,
            'profiles_cache' => 0,
            'extra_uuids' => 0,
        ];

        $push = function (string $uuid, string $source) use (&$seen, &$out, &$sources, $limit): void {
            $uuid = self::normalizeUuid($uuid);
            if ($uuid === null || isset($seen[$uuid]) || count($out) >= $limit) {
                return;
            }
            $seen[$uuid] = true;
            $out[] = $uuid;
            $sources[$source] = ($sources[$source] ?? 0) + 1;
        };

        if ($cfg['include_hypixel_leaderboards'] ?? true) {
            $payload = $this->hypixelApi->getLeaderboards();
            $boardUuids = ProfileCacheIngestService::skyblockUuidsFromLeaderboardsPayload($payload);
            foreach ($boardUuids as $uuid) {
                $push($uuid, 'hypixel_leaderboards');
            }
        }

        if ($cfg['include_site_leaderboard_top'] ?? true) {
            $topLimit = min($limit, max(0, (int) ($cfg['site_leaderboard_top_limit'] ?? 8000)));
            if ($topLimit > 0) {
                foreach (SiteLeaderboardTopUuidsQuery::run(
                    $topLimit,
                    (string) ($cfg['site_leaderboard_top_sort'] ?? 'level'),
                    (string) ($cfg['site_leaderboard_top_direction'] ?? 'desc'),
                ) as $uuid) {
                    $push($uuid, 'site_leaderboard');
                }
            }
        }

        if ($crawlCfg['include_profiles_cache_seeds'] ?? true) {
            foreach ($this->profileCacheSeedUuids($limit) as $uuid) {
                $push($uuid, 'profiles_cache');
            }
        }

        foreach ($cfg['extra_uuids'] ?? [] as $uuid) {
            $push((string) $uuid, 'extra_uuids');
        }

        return ['uuids' => $out, 'sources' => $sources];
    }

    /**
     * Players already on the site — used when Hypixel no longer exposes SKYBLOCK leaderboard UUIDs.
     *
     * @return array<int, string>
     */
    private function profileCacheSeedUuids(int $limit): array
    {
        return ProfileCache::query()
            ->whereNotNull('minecraft_uuid')
            ->where('selected', true)
            ->orderByDesc('fetched_at')
            ->limit(max(1, $limit))
            ->pluck('minecraft_uuid')
            ->map(fn ($u): ?string => self::normalizeUuid((string) $u))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function parseGuildNamesOption(?string $optionValue): array
    {
        if ($optionValue === null || trim($optionValue) === '') {
            return [];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", trim($optionValue));
        $parts = preg_split('/[\n,]+/', $normalized) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $n): bool => $n !== ''));
    }

    /**
     * @return array<int, string>
     */
    public static function configuredGuildNames(): array
    {
        $cfg = config('hypixel.profile_ingest', []);
        $crawlCfg = is_array($cfg['guild_crawl'] ?? null) ? $cfg['guild_crawl'] : [];
        $raw = (string) ($crawlCfg['guild_names'] ?? '');

        return self::parseGuildNamesOption($raw);
    }

    /**
     * @param  array<string, mixed>  $guild
     * @return array<int, string>
     */
    public static function memberUuidsFromGuild(array $guild): array
    {
        $members = $guild['members'] ?? null;
        if ($members === null) {
            return [];
        }

        $found = [];

        if (is_array($members)) {
            foreach ($members as $key => $row) {
                if (is_string($key) && self::normalizeUuid($key) !== null) {
                    $found[self::normalizeUuid($key)] = true;

                    continue;
                }

                if (! is_array($row)) {
                    continue;
                }

                $uuid = $row['uuid'] ?? $row['uuidFormatted'] ?? null;
                if (is_string($uuid)) {
                    $compact = self::normalizeUuid($uuid);
                    if ($compact !== null) {
                        $found[$compact] = true;
                    }
                }
            }
        }

        return array_keys($found);
    }

    public static function normalizeUuid(string $uuid): ?string
    {
        $compact = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $uuid));

        return strlen($compact) === 32 && ctype_xdigit($compact) ? $compact : null;
    }
}
