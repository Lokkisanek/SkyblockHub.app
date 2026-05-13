<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * UUIDs from our DB-backed leaderboard (profiles_cache, selected profile), ordered like the public API.
 * Used to prioritize Hypixel refresh for top players and bulk ingest coverage.
 */
final class SiteLeaderboardTopUuidsQuery
{
    /**
     * @return array<int, string> 32-char lowercase hex, no hyphens
     */
    public static function run(int $limit, string $sortType = 'level', string $direction = 'desc'): array
    {
        if ($limit <= 0) {
            return [];
        }

        $sortType = in_array($sortType, [
            'level',
            'networth',
            'non_cosmetic_networth',
            'skill_average',
            'weight',
            'slayer_total',
            'account_age',
        ], true) ? $sortType : 'level';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        $driver = DB::connection()->getDriverName();
        $rawCol = 'profiles_cache.raw_data';

        $level = self::jsonNumber($rawCol, '$.data.skyblock_level.level', $driver);
        $networth = self::jsonNumber($rawCol, '$.data.networth.networth', $driver);
        $nonCosmetic = self::jsonNumberCoalesce($rawCol, [
            '$.data.networth.unsoulboundNetworth',
            '$.data.networth.networth_no_cosmetics',
            '$.data.networth.purse',
        ], $driver);
        $skillAvg = self::jsonDecimal($rawCol, ['$.data.average_skill_level'], $driver);
        $slayer = self::jsonNumber($rawCol, '$.data.slayers.total_slayer_xp', $driver);
        $firstJoin = self::jsonNumberCoalesce($rawCol, ['$.data.first_join', '$.first_join'], $driver);
        $nowMs = (int) round(microtime(true) * 1000);
        $accountAgeDays = "CASE WHEN MAX({$firstJoin}) > 0 THEN CAST(({$nowMs} - MAX({$firstJoin})) / 86400000 AS INTEGER) ELSE 0 END";
        $weight = "ROUND((MAX({$skillAvg}) * 10) + (MAX({$slayer}) / 1000), 0)";

        $q = DB::table('profiles_cache')
            ->where('profiles_cache.selected', true)
            ->whereNotNull('profiles_cache.minecraft_uuid')
            ->selectRaw("LOWER(REPLACE(profiles_cache.minecraft_uuid, '-', '')) as compact_uuid")
            ->selectRaw("MAX({$level}) as skyblock_level")
            ->selectRaw("MAX({$networth}) as networth")
            ->selectRaw("MAX({$nonCosmetic}) as non_cosmetic_networth")
            ->selectRaw("MAX({$skillAvg}) as skill_average")
            ->selectRaw("MAX({$slayer}) as slayer_total")
            ->selectRaw("{$weight} as weight")
            ->selectRaw("{$accountAgeDays} as account_age_days")
            ->groupBy('profiles_cache.minecraft_uuid');

        $desc = $direction === 'desc';

        match ($sortType) {
            'networth' => $desc
                ? $q->orderByDesc('networth')->orderByDesc('skyblock_level')
                : $q->orderBy('networth')->orderByDesc('skyblock_level'),
            'non_cosmetic_networth' => $desc
                ? $q->orderByDesc('non_cosmetic_networth')->orderByDesc('skyblock_level')
                : $q->orderBy('non_cosmetic_networth')->orderByDesc('skyblock_level'),
            'skill_average' => $desc
                ? $q->orderByDesc('skill_average')->orderByDesc('skyblock_level')
                : $q->orderBy('skill_average')->orderByDesc('skyblock_level'),
            'weight' => $desc
                ? $q->orderByDesc('weight')->orderByDesc('skill_average')
                : $q->orderBy('weight')->orderByDesc('skill_average'),
            'slayer_total' => $desc
                ? $q->orderByDesc('slayer_total')->orderByDesc('skyblock_level')
                : $q->orderBy('slayer_total')->orderByDesc('skyblock_level'),
            'account_age' => $desc
                ? $q->orderByDesc('account_age_days')->orderByDesc('skyblock_level')
                : $q->orderBy('account_age_days')->orderByDesc('skyblock_level'),
            default => $desc
                ? $q->orderByDesc('skyblock_level')->orderByDesc('networth')
                : $q->orderBy('skyblock_level')->orderByDesc('networth'),
        };

        $uuids = $q->limit($limit)->pluck('compact_uuid')->all();

        return array_values(array_filter($uuids, static function ($u): bool {
            $u = strtolower((string) $u);

            return strlen($u) === 32 && ctype_xdigit($u);
        }));
    }

    private static function jsonNumber(string $column, string $path, string $driver): string
    {
        return match ($driver) {
            'sqlite' => "CAST(COALESCE(json_extract({$column}, '{$path}'), 0) AS INTEGER)",
            'pgsql' => 'COALESCE(NULLIF('.self::pgsqlJsonText($column, $path).", '')::numeric, 0)",
            default => "CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')), '0') AS UNSIGNED)",
        };
    }

    /**
     * @param  array<int, string>  $paths
     */
    private static function jsonDecimal(string $column, array $paths, string $driver): string
    {
        $expressions = array_map(function (string $path) use ($column, $driver): string {
            return match ($driver) {
                'sqlite' => "CAST(COALESCE(json_extract({$column}, '{$path}'), 0) AS REAL)",
                'pgsql' => "COALESCE(NULLIF(".self::pgsqlJsonText($column, $path).", '')::numeric, 0)",
                default => "CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')), '0') AS DECIMAL(18, 2))",
            };
        }, $paths);

        return match ($driver) {
            'pgsql' => 'COALESCE('.implode(', ', $expressions).', 0::numeric)',
            default => 'COALESCE('.implode(', ', $expressions).', 0)',
        };
    }

    /**
     * @param  array<int, string>  $paths
     */
    private static function jsonNumberCoalesce(string $column, array $paths, string $driver): string
    {
        $expressions = array_map(function (string $path) use ($column, $driver): string {
            return match ($driver) {
                'sqlite' => "json_extract({$column}, '{$path}')",
                'pgsql' => self::pgsqlJsonText($column, $path),
                default => "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}'))",
            };
        }, $paths);

        $coalesced = implode(', ', $expressions);

        return match ($driver) {
            'sqlite' => "CAST(COALESCE({$coalesced}, 0) AS INTEGER)",
            'pgsql' => "COALESCE(NULLIF(COALESCE({$coalesced}), '')::numeric, 0)",
            default => "CAST(COALESCE({$coalesced}, '0') AS UNSIGNED)",
        };
    }

    private static function pgsqlJsonText(string $column, string $path): string
    {
        $segments = self::jsonPathSegments($path);
        $quoted = implode(', ', array_map(
            static fn (string $segment): string => "'".str_replace("'", "''", $segment)."'",
            $segments
        ));

        return "jsonb_extract_path_text(({$column})::jsonb, {$quoted})";
    }

    /**
     * @return array<int, string>
     */
    private static function jsonPathSegments(string $path): array
    {
        $trimmed = trim($path);
        if ($trimmed === '' || $trimmed === '$') {
            return [];
        }

        if (str_starts_with($trimmed, '$.')) {
            $trimmed = substr($trimmed, 2);
        }

        return array_values(array_filter(explode('.', $trimmed), static fn (string $segment): bool => $segment !== ''));
    }
}
