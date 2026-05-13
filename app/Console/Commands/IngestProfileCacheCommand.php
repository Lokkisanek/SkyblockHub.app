<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\HypixelProfileController;
use App\Services\ProfileCacheIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class IngestProfileCacheCommand extends Command
{
    protected $signature = 'profiles:ingest-scheduled
                            {--dry-run : List UUIDs that would be ingested without calling Hypixel}';

    protected $description = 'Refresh profiles_cache from Hypixel for leaderboard-linked, app-linked, and stale players (scheduled ingest).';

    public function handle(
        ProfileCacheIngestService $ingestService,
        HypixelProfileController $hypixelProfiles,
    ): int {
        if (! config('hypixel.profile_ingest.enabled')) {
            $this->warn('PROFILE_INGEST_ENABLED is false — nothing to do.');

            return self::SUCCESS;
        }

        if ((string) config('hypixel.api_key', '') === '') {
            $this->error('HYPIXEL_API_KEY is not set — cannot ingest profiles.');

            return self::FAILURE;
        }

        $queue = $ingestService->buildIngestQueue();

        if ($queue === []) {
            $this->info('No candidate UUIDs for this run.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Ingest queue: %d player(s).', count($queue)));

        if ($this->option('dry-run')) {
            foreach ($queue as $uuid) {
                $this->line($uuid);
            }

            return self::SUCCESS;
        }

        $delayMs = max(0, (int) config('hypixel.profile_ingest.delay_ms', 600));
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
                Log::warning('Profile ingest: exception', [
                    'uuid' => $uuid,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. OK: {$ok}, skipped/failed: {$fail}.");

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
