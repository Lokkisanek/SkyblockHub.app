<?php

namespace App\Services\Leaderboard;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LeaderboardPlayerSource
{
    public function snapshotIsPopulated(): bool
    {
        if (! (bool) config('leaderboard.use_snapshot_when_populated', true)) {
            return false;
        }

        $table = (string) config('leaderboard.players_table', 'site_leaderboard_players');
        if (! Schema::hasTable($table)) {
            return false;
        }

        return DB::table($table)->exists();
    }

    public function snapshotQueryForFilter(string $filter): QueryBuilder
    {
        $table = (string) config('leaderboard.players_table', 'site_leaderboard_players');
        $q = DB::table($table);
        if ($filter === 'app_users') {
            $q->where('is_app_user', true);
        } elseif ($filter === 'non_app_users') {
            $q->where('is_app_user', false);
        }

        return $q;
    }

    /**
     * ORDER BY clause for ROW_NUMBER(), must stay in sync with {@see applyOrdering()}.
     */
    public function leaderboardWindowOrderSql(string $alias, string $sortType, string $sortDirection): string
    {
        $c = static fn (string $col): string => "{$alias}.{$col}";

        return match ($sortType) {
            'level' => $sortDirection === 'desc'
                ? "{$c('skyblock_level')} DESC, {$c('networth')} DESC"
                : "{$c('skyblock_level')} ASC, {$c('networth')} DESC",
            'networth' => $sortDirection === 'desc'
                ? "{$c('networth')} DESC, {$c('skyblock_level')} DESC"
                : "{$c('networth')} ASC, {$c('skyblock_level')} DESC",
            'non_cosmetic_networth' => $sortDirection === 'desc'
                ? "{$c('non_cosmetic_networth')} DESC, {$c('skyblock_level')} DESC"
                : "{$c('non_cosmetic_networth')} ASC, {$c('skyblock_level')} DESC",
            'skill_average' => $sortDirection === 'desc'
                ? "{$c('skill_average')} DESC, {$c('skyblock_level')} DESC"
                : "{$c('skill_average')} ASC, {$c('skyblock_level')} DESC",
            'weight' => $sortDirection === 'desc'
                ? "{$c('weight')} DESC, {$c('skill_average')} DESC"
                : "{$c('weight')} ASC, {$c('skill_average')} DESC",
            'slayer_total' => $sortDirection === 'desc'
                ? "{$c('slayer_total')} DESC, {$c('skyblock_level')} DESC"
                : "{$c('slayer_total')} ASC, {$c('skyblock_level')} DESC",
            default => $sortDirection === 'desc'
                ? "{$c('account_age_days')} DESC, {$c('skyblock_level')} DESC"
                : "{$c('account_age_days')} ASC, {$c('skyblock_level')} DESC",
        };
    }

    public function buildBaseQuery(string $filter, ?Carbon $from = null, ?Carbon $to = null): QueryBuilder
    {
        $skyblockLevelExpr = $this->jsonNumberExpr('profile_data.raw_data', '$.data.skyblock_level.level');
        $networthExpr = $this->jsonNumberExpr('profile_data.raw_data', '$.data.networth.networth');
        $nonCosmeticExpr = $this->jsonNumberCoalesceExpr('profile_data.raw_data', [
            '$.data.networth.unsoulboundNetworth',
            '$.data.networth.networth_no_cosmetics',
            '$.data.networth.purse',
        ]);
        $hypixelRankExpr = $this->jsonStringCoalesceExpr('profile_data.raw_data', [
            '$.player.rank.prefix',
            '$.rank.prefix',
            '$.data.player.rank.prefix',
        ]);
        $hypixelRankColorExpr = $this->jsonStringCoalesceExpr('profile_data.raw_data', [
            '$.player.rank.color',
            '$.rank.color',
            '$.data.player.rank.color',
        ]);
        $displayNameExpr = $this->jsonStringCoalesceExpr('profile_data.raw_data', [
            '$.username',
            '$.data.username',
        ]);
        $skillAverageExpr = $this->jsonDecimalExpr('profile_data.raw_data', [
            '$.data.average_skill_level',
        ]);
        $slayerTotalExpr = $this->jsonNumberExpr('profile_data.raw_data', '$.data.slayers.total_slayer_xp');
        $weightExpr = "ROUND(({$skillAverageExpr} * 10) + ({$slayerTotalExpr} / 1000), 0)";
        $onlineExpr = $this->jsonBoolExpr('profile_data.raw_data', [
            '$.player.online',
            '$.data.player.online',
        ]);
        $lastSeenExpr = $this->jsonNumberCoalesceExpr('profile_data.raw_data', [
            '$.player.lastLogout',
            '$.player.lastLogin',
            '$.data.player.lastLogout',
            '$.data.player.lastLogin',
        ]);
        $firstJoinExpr = $this->jsonNumberCoalesceExpr('profile_data.raw_data', [
            '$.data.first_join',
            '$.first_join',
        ]);

        $nowMs = (int) round(microtime(true) * 1000);
        $accountAgeExpr = "CASE WHEN MAX({$firstJoinExpr}) > 0 THEN CAST(({$nowMs} - MAX({$firstJoinExpr})) / 86400000 AS INTEGER) ELSE 0 END";

        $normalizedUuidExpr = "LOWER(REPLACE(profile_data.minecraft_uuid, '-', ''))";
        $linkedUserPredicate = $this->booleanPredicate('users.is_mc_linked');
        $appUserExistsSubquery = DB::table('users')
            ->selectRaw('1')
            ->whereRaw("LOWER(REPLACE(users.minecraft_uuid, '-', '')) = {$normalizedUuidExpr}")
            ->where('users.is_mc_linked', true)
            ->limit(1);

        $publicDashboardExistsSubquery = DB::table('user_dashboards')
            ->selectRaw('1')
            ->join('users', 'users.id', '=', 'user_dashboards.user_id')
            ->whereRaw("LOWER(REPLACE(users.minecraft_uuid, '-', '')) = {$normalizedUuidExpr}")
            ->where('user_dashboards.is_public', true)
            ->where('user_dashboards.slot_index', 1)
            ->limit(1);

        $linkedUserIdSubquery = DB::table('users')
            ->select('users.id')
            ->whereRaw("LOWER(REPLACE(users.minecraft_uuid, '-', '')) = {$normalizedUuidExpr}")
            ->where('users.is_mc_linked', true)
            ->limit(1);

        $linkedUuidSubquery = DB::table('users')
            ->select('users.minecraft_uuid')
            ->whereRaw("LOWER(REPLACE(users.minecraft_uuid, '-', '')) = {$normalizedUuidExpr}")
            ->where('users.is_mc_linked', true)
            ->limit(1);

        $query = DB::query()
            ->from('profiles_cache as profile_data')
            ->where('profile_data.selected', true)
            ->whereNotNull('profile_data.minecraft_uuid')
            ->select([
                DB::raw("({$linkedUserIdSubquery->toSql()}) as user_id"),
                'profile_data.minecraft_uuid',
                DB::raw("({$linkedUuidSubquery->toSql()}) as linked_minecraft_uuid"),
                DB::raw("CASE WHEN EXISTS({$appUserExistsSubquery->toSql()}) THEN 1 ELSE 0 END as is_app_user"),
                DB::raw("(SELECT app_vip_rank FROM users WHERE LOWER(REPLACE(users.minecraft_uuid, '-', '')) = {$normalizedUuidExpr} AND {$linkedUserPredicate} LIMIT 1) as app_vip_rank"),
                DB::raw("(SELECT is_donator FROM users WHERE LOWER(REPLACE(users.minecraft_uuid, '-', '')) = {$normalizedUuidExpr} AND {$linkedUserPredicate} LIMIT 1) as is_donator"),
            ])
            ->selectRaw("COALESCE((SELECT minecraft_username FROM users WHERE LOWER(REPLACE(users.minecraft_uuid, '-', '')) = {$normalizedUuidExpr} AND {$linkedUserPredicate} LIMIT 1), MAX({$displayNameExpr}), profile_data.minecraft_uuid) as display_name")
            ->selectRaw("COALESCE((SELECT minecraft_username FROM users WHERE LOWER(REPLACE(users.minecraft_uuid, '-', '')) = {$normalizedUuidExpr} AND {$linkedUserPredicate} LIMIT 1), MAX({$displayNameExpr})) as profile_username")
            ->selectRaw("MAX({$skyblockLevelExpr}) as skyblock_level")
            ->selectRaw("MAX({$networthExpr}) as networth")
            ->selectRaw("MAX({$nonCosmeticExpr}) as non_cosmetic_networth")
            ->selectRaw("{$accountAgeExpr} as account_age_days")
            ->selectRaw("MAX({$skillAverageExpr}) as skill_average")
            ->selectRaw("MAX({$slayerTotalExpr}) as slayer_total")
            ->selectRaw("MAX({$weightExpr}) as weight")
            ->selectRaw("MAX({$onlineExpr}) as online")
            ->selectRaw("MAX({$lastSeenExpr}) as last_seen_ts")
            ->selectRaw("MAX({$hypixelRankExpr}) as hypixel_rank")
            ->selectRaw("COALESCE(MAX({$hypixelRankColorExpr}), '#AAAAAA') as hypixel_rank_color")
            ->selectRaw("CASE WHEN EXISTS({$publicDashboardExistsSubquery->toSql()}) THEN 1 ELSE 0 END as has_public_dashboard")
            ->selectRaw('MAX(profile_data.fetched_at) as slice_max_fetched_at')
            ->groupBy([
                'profile_data.minecraft_uuid',
            ]);

        if ($from && $to) {
            $query->whereNotNull('profile_data.fetched_at')
                ->whereBetween('profile_data.fetched_at', [$from, $to]);
        }

        $query->addBinding($linkedUserIdSubquery->getBindings(), 'select');
        $query->addBinding($linkedUuidSubquery->getBindings(), 'select');
        $query->addBinding($appUserExistsSubquery->getBindings(), 'select');
        $query->addBinding($publicDashboardExistsSubquery->getBindings(), 'select');

        if ($filter === 'app_users') {
            $query->whereRaw("EXISTS({$appUserExistsSubquery->toSql()})");
            $query->addBinding($appUserExistsSubquery->getBindings(), 'where');
        }

        if ($filter === 'non_app_users') {
            $query->whereRaw("NOT EXISTS({$appUserExistsSubquery->toSql()})");
            $query->addBinding($appUserExistsSubquery->getBindings(), 'where');
        }

        return $query;
    }

    public function applyOrdering(QueryBuilder $query, string $sortType, string $direction): void
    {
        $primaryOrder = $direction === 'asc' ? 'orderBy' : 'orderByDesc';

        if ($sortType === 'level') {
            $query->{$primaryOrder}('skyblock_level')->orderByDesc('networth');

            return;
        }

        if ($sortType === 'networth') {
            $query->{$primaryOrder}('networth')->orderByDesc('skyblock_level');

            return;
        }

        if ($sortType === 'non_cosmetic_networth') {
            $query->{$primaryOrder}('non_cosmetic_networth')->orderByDesc('skyblock_level');

            return;
        }

        if ($sortType === 'skill_average') {
            $query->{$primaryOrder}('skill_average')->orderByDesc('skyblock_level');

            return;
        }

        if ($sortType === 'weight') {
            $query->{$primaryOrder}('weight')->orderByDesc('skill_average');

            return;
        }

        if ($sortType === 'slayer_total') {
            $query->{$primaryOrder}('slayer_total')->orderByDesc('skyblock_level');

            return;
        }

        $query->{$primaryOrder}('account_age_days')->orderByDesc('skyblock_level');
    }

    private function jsonNumberExpr(string $column, string $path): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "CAST(COALESCE(json_extract({$column}, '{$path}'), 0) AS INTEGER)";
        }

        if ($driver === 'pgsql') {
            $textExpr = $this->jsonTextExpr($column, $path);

            return "COALESCE(NULLIF({$textExpr}, '')::numeric, 0)";
        }

        return "CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')), '0') AS UNSIGNED)";
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function jsonDecimalExpr(string $column, array $paths): string
    {
        $driver = DB::connection()->getDriverName();
        $expressions = array_map(function (string $path) use ($column): string {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                return "CAST(COALESCE(json_extract({$column}, '{$path}'), 0) AS REAL)";
            }

            if ($driver === 'pgsql') {
                $textExpr = $this->jsonTextExpr($column, $path);

                return "COALESCE(NULLIF({$textExpr}, '')::numeric, 0)";
            }

            return "CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')), '0') AS DECIMAL(18, 2))";
        }, $paths);

        return $driver === 'pgsql'
            ? 'COALESCE('.implode(', ', $expressions).', 0::numeric)'
            : 'COALESCE('.implode(', ', $expressions).', 0)';
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function jsonNumberCoalesceExpr(string $column, array $paths): string
    {
        $driver = DB::connection()->getDriverName();

        $expressions = array_map(function (string $path) use ($column): string {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                return "json_extract({$column}, '{$path}')";
            }

            if ($driver === 'pgsql') {
                return $this->jsonTextExpr($column, $path);
            }

            return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}'))";
        }, $paths);

        $coalesced = implode(', ', $expressions);

        if ($driver === 'sqlite') {
            return "CAST(COALESCE({$coalesced}, 0) AS INTEGER)";
        }

        if ($driver === 'pgsql') {
            return "COALESCE(NULLIF(COALESCE({$coalesced}), '')::numeric, 0)";
        }

        return "CAST(COALESCE({$coalesced}, '0') AS UNSIGNED)";
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function jsonStringCoalesceExpr(string $column, array $paths): string
    {
        $expressions = array_map(function (string $path) use ($column): string {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                return "NULLIF(CAST(json_extract({$column}, '{$path}') AS TEXT), '')";
            }

            if ($driver === 'pgsql') {
                $textExpr = $this->jsonTextExpr($column, $path);

                return "NULLIF({$textExpr}, '')";
            }

            return "NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')), '')";
        }, $paths);

        return 'COALESCE('.implode(', ', $expressions).', NULL)';
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function jsonBoolExpr(string $column, array $paths): string
    {
        $driver = DB::connection()->getDriverName();

        $expressions = array_map(function (string $path) use ($column): string {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                return "CAST(COALESCE(json_extract({$column}, '{$path}'), 0) AS INTEGER)";
            }

            if ($driver === 'pgsql') {
                $textExpr = $this->jsonTextExpr($column, $path);

                return "CASE WHEN LOWER(COALESCE({$textExpr}, '')) IN ('1', 'true', 't', 'yes') THEN 1 ELSE 0 END";
            }

            return "CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')), '0') AS UNSIGNED)";
        }, $paths);

        return $driver === 'pgsql'
            ? 'COALESCE('.implode(', ', $expressions).', 0::integer)'
            : 'COALESCE('.implode(', ', $expressions).', 0)';
    }

    private function booleanPredicate(string $column): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? "{$column} = true"
            : "{$column} = 1";
    }

    private function jsonTextExpr(string $column, string $path): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "CAST(json_extract({$column}, '{$path}') AS TEXT)";
        }

        if ($driver === 'pgsql') {
            $segments = $this->jsonPathSegments($path);
            $quoted = implode(', ', array_map(
                static fn (string $segment): string => "'".str_replace("'", "''", $segment)."'",
                $segments
            ));

            return "jsonb_extract_path_text(({$column})::jsonb, {$quoted})";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}'))";
    }

    /**
     * @return array<int, string>
     */
    private function jsonPathSegments(string $path): array
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
