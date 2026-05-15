<?php

namespace App\Console\Commands;

use App\Services\Leaderboard\LeaderboardPlayerSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RebuildSiteLeaderboardSnapshotCommand extends Command
{
    protected $signature = 'leaderboard:rebuild-snapshot';

    protected $description = 'Rebuild flat site_leaderboard_players from profiles_cache (schedule hourly in production).';

    /**
     * @var array<int, string>
     */
    private const SNAPSHOT_COLUMNS = [
        'minecraft_uuid',
        'user_id',
        'linked_minecraft_uuid',
        'is_app_user',
        'app_vip_rank',
        'is_donator',
        'display_name',
        'profile_username',
        'skyblock_level',
        'networth',
        'non_cosmetic_networth',
        'account_age_days',
        'skill_average',
        'slayer_total',
        'weight',
        'online',
        'last_seen_ts',
        'hypixel_rank',
        'hypixel_rank_color',
        'has_public_dashboard',
        'slice_max_fetched_at',
    ];

    public function handle(LeaderboardPlayerSource $playerSource): int
    {
        $main = (string) config('leaderboard.players_table', 'site_leaderboard_players');
        $staging = (string) config('leaderboard.players_staging_table', 'site_leaderboard_players_staging');

        if (! Schema::hasTable($main) || ! Schema::hasTable($staging)) {
            $this->error('Leaderboard snapshot tables are missing. Run migrations.');

            return self::FAILURE;
        }

        $this->info('Truncating staging table…');
        DB::table($staging)->truncate();

        $base = $playerSource->buildBaseQuery('all', null, null);
        $base->reorder()->orderBy('minecraft_uuid');

        $inserted = 0;
        $base->chunk(2000, function ($rows) use ($staging, &$inserted): void {
            $batch = [];
            foreach ($rows as $row) {
                $batch[] = $this->mapAggregateRowToSnapshot($row);
            }
            DB::table($staging)->insert($batch);
            $inserted += count($batch);
            $this->line("  staging rows: {$inserted}");
        }, 'minecraft_uuid');

        $this->info('Swapping staging → live…');
        DB::transaction(function () use ($main, $staging): void {
            DB::table($main)->delete();
            DB::table($main)->insertUsing(
                self::SNAPSHOT_COLUMNS,
                DB::table($staging)->select(self::SNAPSHOT_COLUMNS)
            );
        });

        DB::table($staging)->truncate();

        $this->info("Done. {$inserted} players materialized.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAggregateRowToSnapshot(object $row): array
    {
        $slice = $row->slice_max_fetched_at ?? null;
        $sliceSql = null;
        if ($slice instanceof \DateTimeInterface) {
            $sliceSql = $slice->format('Y-m-d H:i:s');
        } elseif (is_string($slice) && $slice !== '') {
            $sliceSql = $slice;
        }

        return [
            'minecraft_uuid' => (string) $row->minecraft_uuid,
            'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
            'linked_minecraft_uuid' => $row->linked_minecraft_uuid !== null ? (string) $row->linked_minecraft_uuid : null,
            'is_app_user' => (bool) ((int) ($row->is_app_user ?? 0) === 1),
            'app_vip_rank' => $row->app_vip_rank !== null ? (string) $row->app_vip_rank : null,
            'is_donator' => (bool) ((int) ($row->is_donator ?? 0) === 1),
            'display_name' => $row->display_name !== null ? (string) $row->display_name : null,
            'profile_username' => $row->profile_username !== null ? (string) $row->profile_username : null,
            'skyblock_level' => (int) ($row->skyblock_level ?? 0),
            'networth' => (int) ($row->networth ?? 0),
            'non_cosmetic_networth' => (int) ($row->non_cosmetic_networth ?? 0),
            'account_age_days' => (int) ($row->account_age_days ?? 0),
            'skill_average' => (float) ($row->skill_average ?? 0),
            'slayer_total' => (int) ($row->slayer_total ?? 0),
            'weight' => (int) ($row->weight ?? 0),
            'online' => (bool) ((int) ($row->online ?? 0) === 1),
            'last_seen_ts' => $row->last_seen_ts !== null ? (int) $row->last_seen_ts : null,
            'hypixel_rank' => $row->hypixel_rank !== null ? (string) $row->hypixel_rank : null,
            'hypixel_rank_color' => (string) ($row->hypixel_rank_color ?? '#AAAAAA'),
            'has_public_dashboard' => (bool) ((int) ($row->has_public_dashboard ?? 0) === 1),
            'slice_max_fetched_at' => $sliceSql,
        ];
    }
}
