<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\HypixelProfileController;
use App\Services\ProfileCacheIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IngestProfileCacheBulkCommand extends Command
{
    protected $signature = 'profiles:ingest-bulk
                            {--limit=5000 : Max profiles to refresh this run (capped by profile_ingest.bulk_safe_cap)}
                            {--delay-ms= : Milliseconds between players (default: profile_ingest.delay_ms)}
                            {--dry-run : Show queue size and sample UUIDs without calling Hypixel}';

    protected $description = 'Bulk refresh profiles_cache from Hypixel (site top + Hypixel boards + linked + stale), for large backfills.';

    public function handle(
        ProfileCacheIngestService $ingestService,
        HypixelProfileController $hypixelProfiles,
    ): int {
        if (trim((string) config('hypixel.api_key', '')) === '') {
            $this->error('HYPIXEL_API_KEY is not set (or is only whitespace) — cannot ingest profiles.');

            return self::FAILURE;
        }

        $cap = max(1, (int) config('hypixel.profile_ingest.bulk_safe_cap', 25000));
        $requested = max(1, (int) $this->option('limit'));
        $limit = min($requested, $cap);

        if ($requested > $cap) {
            $this->warn("Requested --limit={$requested} exceeds bulk_safe_cap ({$cap}); using {$cap}.");
        }

        $queue = $ingestService->buildIngestQueue($limit);

        if ($queue === []) {
            $this->info('No candidate UUIDs in queue.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Bulk ingest queue: %d player(s).', count($queue)));

        if ($this->option('dry-run')) {
            foreach (array_slice($queue, 0, 25) as $uuid) {
                $this->line($uuid);
            }
            if (count($queue) > 25) {
                $this->comment('… '.(count($queue) - 25).' more');
            }

            return self::SUCCESS;
        }

        $delayOpt = $this->option('delay-ms');
        $delayMs = $delayOpt !== null && $delayOpt !== ''
            ? max(0, (int) $delayOpt)
            : max(0, (int) config('hypixel.profile_ingest.delay_ms', 500));

        $ok = 0;
        $fail = 0;

        foreach ($queue as $i => $uuid) {
            if ($i > 0 && $delayMs > 0) {
                usleep($delayMs * 1000);
            }

            try {
                if ($hypixelProfiles->ingestProfilesCacheForUuid($uuid)) {
                    $ok++;
                } else {
                    $fail++;
                }
            } catch (\Throwable $e) {
                $fail++;
                Log::warning('Profile bulk ingest: exception', [
                    'uuid' => $uuid,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. OK: {$ok}, skipped/failed: {$fail}.");

        if ($ok === 0 && $fail > 0) {
            $this->newLine();
            $this->warn('Every UUID failed: ingestProfilesCacheForUuid() returned false for each one. Typical causes:');
            $this->line(' • Hypixel did not return profiles (null) — invalid/expired API key, rate limit with no stale cache, network, or Hypixel success=false.');
            $this->line(' • Hypixel returned an empty <fg=yellow>profiles</> array — player has never played SkyBlock, or API is disabled for that account.');
            $this->line(' • Check <fg=cyan>storage/logs/laravel.log</> for lines containing <fg=yellow>HypixelApiProxy</>.');
        }

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
