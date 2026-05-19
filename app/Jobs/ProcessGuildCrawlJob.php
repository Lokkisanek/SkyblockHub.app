<?php

namespace App\Jobs;

use App\Http\Controllers\Api\HypixelProfileController;
use App\Services\GuildCrawlService;
use App\Support\AdminGuildCrawlStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGuildCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Long guild crawls run in a queue worker, not inside the HTTP request lifecycle. */
    public int $timeout = 7200;

    public int $tries = 1;

    /**
     * @param  array<int, string>  $guildNames
     * @param  array{max_guilds: int, seed_limit: int, member_limit: int, delay_ms: int, new_only: bool}  $options
     */
    public function __construct(
        public array $guildNames,
        public array $options,
    ) {}

    public function handle(
        GuildCrawlService $guildCrawl,
        HypixelProfileController $hypixelProfiles,
    ): void {
        if (trim((string) config('hypixel.api_key', '')) === '') {
            AdminGuildCrawlStatus::finish('failed', 'HYPIXEL_API_KEY is not set.');

            return;
        }

        $maxGuilds = max(1, (int) ($this->options['max_guilds'] ?? 15));
        $seedLimit = max(0, (int) ($this->options['seed_limit'] ?? 60));
        $memberLimit = max(1, (int) ($this->options['member_limit'] ?? 500));
        $delayMs = max(0, (int) ($this->options['delay_ms'] ?? 2000));
        $guildLookupDelayMs = max(0, (int) ($this->options['guild_lookup_delay_ms'] ?? min(500, $delayMs)));
        $newOnly = (bool) ($this->options['new_only'] ?? true);
        $lightweight = (bool) config('hypixel.profile_ingest.lightweight_bulk', true);

        try {
            AdminGuildCrawlStatus::merge([
                'status' => 'discovering',
                'message' => sprintf('Fetching %d guild(s) from Hypixel…', count($this->guildNames)),
            ]);
            AdminGuildCrawlStatus::appendLog(sprintf('Discovering guilds (max %d)…', $maxGuilds));

            $result = $guildCrawl->collectMemberUuids(
                $maxGuilds,
                $seedLimit,
                $newOnly,
                $this->guildNames,
                $guildLookupDelayMs,
            );

            if (AdminGuildCrawlStatus::shouldCancel()) {
                AdminGuildCrawlStatus::finish('cancelled', 'Cancelled during guild discovery.');

                return;
            }

            $members = array_slice($result['member_uuids'], 0, $memberLimit);
            $guildLookups = is_array($result['guild_lookups'] ?? null) ? $result['guild_lookups'] : [];
            $lookupHint = GuildCrawlService::summarizeGuildLookupFailures($guildLookups);

            AdminGuildCrawlStatus::merge([
                'guilds_found' => $result['guilds_found'],
                'seeds_scanned' => $result['seeds_scanned'],
                'api_calls' => $result['api_calls'],
                'guild_lookups' => $guildLookups,
                'total_members' => count($members),
                'processed' => 0,
                'ok' => 0,
                'failed' => 0,
            ]);
            AdminGuildCrawlStatus::appendLog(sprintf(
                'Found %d guild(s), %d member UUID(s) to ingest.',
                $result['guilds_found'],
                count($members),
            ));

            if ($members === []) {
                $message = $result['guilds_found'] === 0
                    ? sprintf(
                        'Hypixel returned 0 / %d guild(s). %s',
                        count($this->guildNames),
                        $lookupHint !== '' ? $lookupHint : 'Check exact guild names (case & spaces).'
                    )
                    : ($newOnly
                        ? 'All members are already in cache (new-only enabled). Disable "Only players not in cache" or clear filter.'
                        : 'Guilds found but no member UUIDs parsed.');

                AdminGuildCrawlStatus::finish('completed', $message);

                return;
            }

            if (AdminGuildCrawlStatus::shouldCancel()) {
                AdminGuildCrawlStatus::finish('cancelled', 'Cancelled before ingest started.');

                return;
            }

            AdminGuildCrawlStatus::merge([
                'status' => 'ingesting',
                'message' => sprintf('Ingesting %d profile(s)…', count($members)),
            ]);

            $ok = 0;
            $fail = 0;

            foreach ($members as $i => $uuid) {
                if (AdminGuildCrawlStatus::shouldCancel()) {
                    AdminGuildCrawlStatus::finish(
                        'cancelled',
                        sprintf('Cancelled after %d / %d player(s). OK: %d, failed: %d.', $i, count($members), $ok, $fail),
                    );

                    return;
                }

                if ($i > 0 && $delayMs > 0) {
                    usleep($delayMs * 1000);
                }

                AdminGuildCrawlStatus::merge([
                    'current_uuid' => $uuid,
                    'processed' => $i,
                    'message' => sprintf('Ingesting %d / %d…', $i + 1, count($members)),
                ]);

                try {
                    if ($hypixelProfiles->ingestProfilesCacheForUuid($uuid, lightweight: $lightweight)) {
                        $ok++;
                    } else {
                        $fail++;
                    }
                } catch (\Throwable $e) {
                    $fail++;
                    Log::warning('Admin guild crawl ingest: exception', [
                        'uuid' => $uuid,
                        'message' => $e->getMessage(),
                    ]);
                }

                AdminGuildCrawlStatus::merge([
                    'processed' => $i + 1,
                    'ok' => $ok,
                    'failed' => $fail,
                ]);

                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            AdminGuildCrawlStatus::appendLog(sprintf('Done. OK: %d, failed: %d.', $ok, $fail));
            AdminGuildCrawlStatus::finish(
                'completed',
                sprintf('Finished. OK: %d, failed: %d. Run leaderboard:rebuild-snapshot if needed.', $ok, $fail),
            );
        } catch (\Throwable $e) {
            Log::error('Admin guild crawl failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            AdminGuildCrawlStatus::appendLog('Error: '.$e->getMessage());
            AdminGuildCrawlStatus::finish('failed', 'Crawl failed: '.$e->getMessage());
        }
    }
}
