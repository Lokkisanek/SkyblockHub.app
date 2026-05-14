<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Leaderboard aggregates read `profiles_cache.raw_data` JSON (selected profile per player).
 */
class LeaderboardController extends Controller
{
    private const CACHE_TTL = 300;

    private const ITEMS_PER_PAGE = 50;

    /**
     * Sort keys + display metadata for GET /api/v1/leaderboards (single source for UI chips).
     *
     * @var array<int, array{key: string, label: string, format: string, align: string}>
     */
    private const SORT_DEFINITIONS = [
        ['key' => 'level', 'label' => 'Level', 'format' => 'integer', 'align' => 'right'],
        ['key' => 'networth', 'label' => 'Networth', 'format' => 'compact', 'align' => 'right'],
        ['key' => 'non_cosmetic_networth', 'label' => 'Pure Coins', 'format' => 'compact', 'align' => 'right'],
        ['key' => 'account_age', 'label' => 'Account Age', 'format' => 'age', 'align' => 'right'],
        ['key' => 'skill_average', 'label' => 'Skill Avg', 'format' => 'decimal', 'align' => 'right'],
        ['key' => 'weight', 'label' => 'Weight', 'format' => 'integer', 'align' => 'right'],
        ['key' => 'slayer_total', 'label' => 'Slayer XP', 'format' => 'compact', 'align' => 'right'],
    ];

    private const FILTERS = [
        'all',
        'app_users',
        'non_app_users',
    ];

    private const RANK_MAP_LIMIT = 1000;

    /**
     * @return array<int, array{key: string, label: string, format: string, align: string}>
     */
    public static function sortColumnsForClient(): array
    {
        return self::SORT_DEFINITIONS;
    }

    /**
     * @return array<int, string>
     */
    private static function allowedSortKeys(): array
    {
        return array_column(self::SORT_DEFINITIONS, 'key');
    }

    public function index(Request $request): JsonResponse
    {
        $sortType = (string) $request->query('sort', 'level');
        if (! in_array($sortType, self::allowedSortKeys(), true)) {
            $sortType = 'level';
        }

        $sortDirection = strtolower((string) $request->query('direction', 'desc'));
        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $filter = (string) $request->query('filter', 'all');
        if (! in_array($filter, self::FILTERS, true)) {
            $filter = 'all';
        }

        $page = max((int) $request->query('page', 1), 1);

        $cacheKey = "leaderboard:v8:{$sortType}:{$sortDirection}:{$filter}:{$page}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sortType, $sortDirection, $filter, $page): array {
            $query = $this->buildBaseQuery($filter, null, null);
            $this->applyOrdering($query, $sortType, $sortDirection);

            $previousRankMap = [];

            $paginator = $query->paginate(self::ITEMS_PER_PAGE, ['*'], 'page', $page);
            $firstRank = ($paginator->currentPage() - 1) * $paginator->perPage() + 1;

            $profileVisitCounts = $this->profileSearchCountsByUsernameLower(
                collect($paginator->items())
                    ->pluck('profile_username')
                    ->filter(static fn ($name): bool => $name !== null && $name !== '')
                    ->map(static fn ($name): string => mb_strtolower((string) $name))
                    ->unique()
                    ->values()
                    ->all()
            );

            $rows = collect($paginator->items())
                ->values()
                ->map(function ($row, int $index) use ($firstRank, $previousRankMap, $profileVisitCounts): array {
                    $rank = $firstRank + $index;

                    return $this->formatLeaderboardRow($row, $rank, $previousRankMap, $profileVisitCounts);
                });

            return [
                'rows' => $rows,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
                'cache_max_fetched_at' => $this->maxCacheFetchedAtForScope($filter, null, null),
                // Internal: used after cache to build per-user `personal` (not exposed in JSON).
                'previous_rank_map' => $previousRankMap,
            ];
        });

        $previousRankMap = $data['previous_rank_map'] ?? [];
        unset($data['previous_rank_map']);

        $data['personal'] = $this->buildPersonalCard(
            $request,
            $sortType,
            $sortDirection,
            $filter,
            $previousRankMap
        );

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Resolve global rank + pagination page for a player name / UUID (same sort and filter as the table; live data).
     */
    public function lookup(Request $request): JsonResponse
    {
        $sortType = (string) $request->query('sort', 'level');
        if (! in_array($sortType, self::allowedSortKeys(), true)) {
            $sortType = 'level';
        }

        $sortDirection = strtolower((string) $request->query('direction', 'desc'));
        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $filter = (string) $request->query('filter', 'all');
        if (! in_array($filter, self::FILTERS, true)) {
            $filter = 'all';
        }

        $rawQuery = trim((string) $request->query('q', ''));
        if ($rawQuery === '') {
            return response()->json([
                'data' => [
                    'found' => false,
                    'error' => 'empty_query',
                ],
            ], 422);
        }

        if (mb_strlen($rawQuery) > 48) {
            return response()->json([
                'data' => [
                    'found' => false,
                    'error' => 'query_too_long',
                ],
            ], 422);
        }

        $needle = mb_strtolower($rawQuery);
        if (mb_strlen($needle) < 2) {
            return response()->json([
                'data' => [
                    'found' => false,
                    'error' => 'query_too_short',
                ],
            ], 422);
        }

        $uuidNeedle = $this->normalizeUuid(str_replace('-', '', $rawQuery));

        $cacheKey = 'leaderboard:lookup:v2:'
            .hash('xxh128', implode("\0", [$sortType, $sortDirection, $filter, $needle, $uuidNeedle]));

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $sortType,
            $sortDirection,
            $filter,
            $needle,
            $uuidNeedle,
        ): array {
            $base = $this->buildBaseQuery($filter, null, null);
            $this->applyOrdering($base, $sortType, $sortDirection);

            $windowOrder = $this->leaderboardWindowOrderSql('lb', $sortType, $sortDirection);

            $ranked = DB::query()
                ->fromSub($base, 'lb')
                ->select([
                    'lb.minecraft_uuid',
                    'lb.display_name',
                    'lb.profile_username',
                ])
                ->selectRaw("ROW_NUMBER() OVER (ORDER BY {$windowOrder}) AS board_rank");

            $match = $this->firstLeaderboardLookupMatch($ranked, $needle, $uuidNeedle, requireExact: true);
            if (! $match) {
                $match = $this->firstLeaderboardLookupMatch($ranked, $needle, $uuidNeedle, requireExact: false);
            }

            if (! $match) {
                return [
                    'found' => false,
                ];
            }

            $rank = (int) $match->board_rank;
            $page = (int) max(1, (int) ceil($rank / self::ITEMS_PER_PAGE));

            return [
                'found' => true,
                'rank' => $rank,
                'page' => $page,
                'per_page' => self::ITEMS_PER_PAGE,
                'display_name' => $match->display_name,
                'profile_username' => $match->profile_username,
                'minecraft_uuid' => $match->minecraft_uuid,
            ];
        });

        return response()->json([
            'data' => $payload,
        ]);
    }

    private function firstLeaderboardLookupMatch(QueryBuilder $ranked, string $needle, string $uuidNeedle, bool $requireExact): ?object
    {
        $q = DB::query()->fromSub($ranked, 'r')->orderBy('r.board_rank');

        if ($requireExact) {
            $q->where(function ($inner) use ($needle, $uuidNeedle): void {
                $inner->whereRaw('LOWER(r.profile_username) = ?', [$needle])
                    ->orWhereRaw('LOWER(r.display_name) = ?', [$needle]);

                if ($uuidNeedle !== '' && strlen($uuidNeedle) === 32 && ctype_xdigit($uuidNeedle)) {
                    $inner->orWhereRaw('LOWER(REPLACE(r.minecraft_uuid, \'-\', \'\')) = ?', [$uuidNeedle]);
                }
            });
        } else {
            if (mb_strlen($needle) < 2) {
                return null;
            }

            $like = $needle.'%';
            $q->where(function ($inner) use ($like): void {
                $inner->whereRaw('LOWER(r.profile_username) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(r.display_name) LIKE ?', [$like]);
            });
        }

        return $q->first();
    }

    /**
     * ORDER BY clause for ROW_NUMBER(), must stay in sync with {@see applyOrdering()}.
     */
    private function leaderboardWindowOrderSql(string $alias, string $sortType, string $sortDirection): string
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

    private function buildBaseQuery(string $filter, ?Carbon $from = null, ?Carbon $to = null): QueryBuilder
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

    /**
     * Latest `profiles_cache.fetched_at` among rows that match the same scope as the leaderboard
     * (selected profile, filter; optional date window on fetched_at for callers that still pass it).
     */
    private function maxCacheFetchedAtForScope(string $filter, ?Carbon $from, ?Carbon $to): ?string
    {
        $sub = $this->buildBaseQuery($filter, $from, $to);
        $max = DB::query()
            ->fromSub($sub, 'leaderboard_scope')
            ->max('slice_max_fetched_at');

        if ($max === null) {
            return null;
        }

        return Carbon::parse($max)->toIso8601String();
    }

    /**
     * @return array<string, int>
     */
    private function buildRankMap(string $sortType, string $direction, string $filter, ?Carbon $from, ?Carbon $to): array
    {
        $query = $this->buildBaseQuery($filter, $from, $to);
        $this->applyOrdering($query, $sortType, $direction);

        $rows = $query->limit(self::RANK_MAP_LIMIT)->get();
        $rankMap = [];

        foreach ($rows as $index => $row) {
            $uuid = $this->normalizeUuid((string) ($row->minecraft_uuid ?? ''));
            if ($uuid !== '') {
                $rankMap[$uuid] = $index + 1;
            }
        }

        return $rankMap;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPersonalCard(
        Request $request,
        string $sortType,
        string $direction,
        string $filter,
        array $previousRankMap
    ): ?array {
        $user = $request->user();
        if (! $user || ! $user->minecraft_uuid) {
            return null;
        }

        $normalizedUuid = $this->normalizeUuid($user->minecraft_uuid);
        if ($normalizedUuid === '') {
            return null;
        }

        $query = $this->buildBaseQuery($filter, null, null);
        $query->whereRaw("LOWER(REPLACE(profile_data.minecraft_uuid, '-', '')) = ?", [$normalizedUuid]);

        $row = $query->first();
        if (! $row) {
            return null;
        }

        $rankMap = $this->buildRankMap($sortType, $direction, $filter, null, null);
        $rank = $rankMap[$normalizedUuid] ?? null;

        if (! $rank) {
            return null;
        }

        $profileVisitCounts = $this->profileSearchCountsByUsernameLower(
            ! empty($row->profile_username)
                ? [mb_strtolower((string) $row->profile_username)]
                : []
        );

        return $this->formatLeaderboardRow($row, $rank, $previousRankMap, $profileVisitCounts);
    }

    /**
     * One query for all distinct profile usernames on the current leaderboard page.
     *
     * @param  array<int, string>  $usernameLowers
     * @return array<string, int>
     */
    private function profileSearchCountsByUsernameLower(array $usernameLowers): array
    {
        $usernameLowers = array_values(array_unique(array_filter(
            $usernameLowers,
            static fn (string $u): bool => $u !== ''
        )));

        if ($usernameLowers === []) {
            return [];
        }

        $counts = DB::table('profile_searches')
            ->selectRaw('LOWER(username) AS username_key')
            ->selectRaw('COUNT(*) AS visit_count')
            ->whereIn(DB::raw('LOWER(username)'), $usernameLowers)
            ->groupBy(DB::raw('LOWER(username)'))
            ->pluck('visit_count', 'username_key');

        return $counts->map(static fn ($c): int => (int) $c)->all();
    }

    /**
     * @param  array<string, int>  $profileVisitCountsByUsernameLower
     * @return array<string, mixed>
     */
    private function formatLeaderboardRow(object $row, int $rank, array $previousRankMap, array $profileVisitCountsByUsernameLower): array
    {
        $hasPublicDashboard = (bool) ((int) ($row->has_public_dashboard ?? 0) === 1);

        $profileUsernameLower = mb_strtolower(trim((string) ($row->profile_username ?? '')));
        $profileVisits = $profileUsernameLower !== ''
            ? (int) ($profileVisitCountsByUsernameLower[$profileUsernameLower] ?? 0)
            : 0;

        $uuid = $this->normalizeUuid((string) ($row->minecraft_uuid ?? ''));
        $previousRank = $uuid !== '' ? ($previousRankMap[$uuid] ?? null) : null;
        $movement = $previousRank !== null ? $previousRank - $rank : null;

        return [
            'rank' => $rank,
            'previous_rank' => $previousRank,
            'movement' => $movement,
            'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
            'display_name' => $row->display_name,
            'profile_username' => $row->profile_username,
            'minecraft_uuid' => $row->minecraft_uuid,
            'linked_minecraft_uuid' => $row->linked_minecraft_uuid,
            'is_app_user' => (bool) ($row->is_app_user ?? false),
            'app_vip_rank' => $row->app_vip_rank,
            'is_donator' => (bool) $row->is_donator,
            'hypixel_rank' => $row->hypixel_rank,
            'hypixel_rank_color' => $row->hypixel_rank_color ?: '#AAAAAA',
            'skyblock_level' => (int) ($row->skyblock_level ?? 0),
            'networth' => (int) ($row->networth ?? 0),
            'non_cosmetic_networth' => (int) ($row->non_cosmetic_networth ?? 0),
            'account_age_days' => (int) ($row->account_age_days ?? 0),
            'skill_average' => (float) ($row->skill_average ?? 0),
            'weight' => (int) ($row->weight ?? 0),
            'slayer_total' => (int) ($row->slayer_total ?? 0),
            'online' => (bool) ($row->online ?? false),
            'last_seen_ts' => $row->last_seen_ts !== null ? (int) $row->last_seen_ts : null,
            'profile_visits' => $profileVisits,
            'has_public_dashboard' => $hasPublicDashboard,
        ];
    }

    private function normalizeUuid(string $uuid): string
    {
        return strtolower(str_replace('-', '', $uuid));
    }

    private function applyOrdering(QueryBuilder $query, string $sortType, string $direction): void
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
