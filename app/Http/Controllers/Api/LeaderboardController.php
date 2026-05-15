<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Leaderboard\LeaderboardPlayerSource;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Leaderboard reads pre-materialized `site_leaderboard_players` when populated (see
 * `leaderboard:rebuild-snapshot`); otherwise aggregates `profiles_cache.raw_data` live.
 */
class LeaderboardController extends Controller
{
    public function __construct(private LeaderboardPlayerSource $playerSource) {}

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

        $cacheKey = "leaderboard:v10:{$sortType}:{$sortDirection}:{$filter}:{$page}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sortType, $sortDirection, $filter, $page): array {
            $query = $this->queryForLeaderboard($filter);
            $this->playerSource->applyOrdering($query, $sortType, $sortDirection);

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

        $cacheKey = 'leaderboard:lookup:v3:'
            .hash('xxh128', implode("\0", [$sortType, $sortDirection, $filter, $needle, $uuidNeedle]));

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $sortType,
            $sortDirection,
            $filter,
            $needle,
            $uuidNeedle,
        ): array {
            $base = $this->queryForLeaderboard($filter);
            $this->playerSource->applyOrdering($base, $sortType, $sortDirection);

            $windowOrder = $this->playerSource->leaderboardWindowOrderSql('lb', $sortType, $sortDirection);

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

    private function queryForLeaderboard(string $filter): QueryBuilder
    {
        if ($this->playerSource->snapshotIsPopulated()) {
            return $this->playerSource->snapshotQueryForFilter($filter);
        }

        return $this->playerSource->buildBaseQuery($filter, null, null);
    }

    private function restrictToNormalizedUuid(QueryBuilder $query, string $normalizedUuid): void
    {
        if ($this->playerSource->snapshotIsPopulated()) {
            $query->whereRaw('LOWER(REPLACE(minecraft_uuid, \'-\', \'\')) = ?', [$normalizedUuid]);

            return;
        }

        $query->whereRaw("LOWER(REPLACE(profile_data.minecraft_uuid, '-', '')) = ?", [$normalizedUuid]);
    }

    private function maxCacheFetchedAtForScope(string $filter, ?Carbon $from, ?Carbon $to): ?string
    {
        if ($this->playerSource->snapshotIsPopulated() && $from === null && $to === null) {
            $max = $this->playerSource->snapshotQueryForFilter($filter)->max('slice_max_fetched_at');
        } else {
            $sub = $this->playerSource->buildBaseQuery($filter, $from, $to);
            $max = DB::query()
                ->fromSub($sub, 'leaderboard_scope')
                ->max('slice_max_fetched_at');
        }

        if ($max === null) {
            return null;
        }

        return Carbon::parse($max)->toIso8601String();
    }

    /**
     * Global board rank (1-based) for one UUID; one small result row instead of scanning the top-N map client-side.
     */
    private function resolveBoardRankForUuid(
        string $normalizedUuid,
        string $sortType,
        string $direction,
        string $filter
    ): ?int {
        $base = $this->queryForLeaderboard($filter);
        $this->playerSource->applyOrdering($base, $sortType, $direction);

        $windowOrder = $this->playerSource->leaderboardWindowOrderSql('lb', $sortType, $direction);

        $ranked = DB::query()
            ->fromSub($base, 'lb')
            ->select(['lb.minecraft_uuid'])
            ->selectRaw("ROW_NUMBER() OVER (ORDER BY {$windowOrder}) AS board_rank");

        $rank = DB::query()
            ->fromSub($ranked, 'r')
            ->whereRaw('LOWER(REPLACE(r.minecraft_uuid, \'-\', \'\')) = ?', [$normalizedUuid])
            ->value('board_rank');

        return $rank !== null ? (int) $rank : null;
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

        $cacheKey = 'leaderboard:personal:v1:'
            .((string) $user->getAuthIdentifier())
            .':'
            .hash('xxh128', implode("\0", [$sortType, $direction, $filter, $normalizedUuid]));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $normalizedUuid,
            $sortType,
            $direction,
            $filter,
            $previousRankMap
        ): ?array {
            $query = $this->queryForLeaderboard($filter);
            $this->restrictToNormalizedUuid($query, $normalizedUuid);

            $row = $query->first();
            if (! $row) {
                return null;
            }

            $rank = $this->resolveBoardRankForUuid($normalizedUuid, $sortType, $direction, $filter);
            if ($rank === null || $rank < 1) {
                return null;
            }

            $profileVisitCounts = $this->profileSearchCountsByUsernameLower(
                ! empty($row->profile_username)
                    ? [mb_strtolower((string) $row->profile_username)]
                    : []
            );

            return $this->formatLeaderboardRow($row, $rank, $previousRankMap, $profileVisitCounts);
        });
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
}
