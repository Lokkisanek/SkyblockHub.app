<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\HypixelProfileController;
use App\Services\GuildCrawlService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CrawlGuildMembersCommand extends Command
{
    protected $signature = 'profiles:crawl-guilds
                            {--max-guilds=15 : Max distinct guilds to discover}
                            {--seed-limit=60 : Max seed player UUIDs (?player= discovery)}
                            {--guild= : Comma-separated guild names (direct /v2/guild?name=), e.g. "My Guild,Other"}
                            {--member-limit=150 : Max guild member UUIDs to ingest this run}
                            {--new-only : Only ingest members not yet in profiles_cache}
                            {--delay-ms=2000 : Milliseconds between profile ingests}
                            {--dry-run : List discovered members without ingesting}';

    protected $description = 'Discover guilds from seed players (Hypixel /v2/guild) and ingest member SkyBlock profiles.';

    public function handle(
        GuildCrawlService $guildCrawl,
        HypixelProfileController $hypixelProfiles,
    ): int {
        if (trim((string) config('hypixel.api_key', '')) === '') {
            $this->error('HYPIXEL_API_KEY is not set — cannot crawl guilds.');

            return self::FAILURE;
        }

        $maxGuilds = max(1, (int) $this->option('max-guilds'));
        $seedLimit = max(1, (int) $this->option('seed-limit'));
        $memberLimit = max(1, (int) $this->option('member-limit'));
        $newOnly = (bool) $this->option('new-only');

        $guildNames = array_merge(
            GuildCrawlService::configuredGuildNames(),
            GuildCrawlService::parseGuildNamesOption($this->option('guild')),
        );
        $guildNames = array_values(array_unique($guildNames));

        $this->info(sprintf(
            'Discovering up to %d guild(s) (%d by name, then up to %d seed player(s))…',
            $maxGuilds,
            count($guildNames),
            $seedLimit
        ));

        $result = $guildCrawl->collectMemberUuids($maxGuilds, $seedLimit, $newOnly, $guildNames);
        $members = array_slice($result['member_uuids'], 0, $memberLimit);

        $rows = [
            ['Guilds found', (string) $result['guilds_found']],
            ['Guild names requested', (string) count($guildNames)],
            ['Seed players scanned', (string) $result['seeds_scanned']],
            ['Guild API calls', (string) $result['api_calls']],
            ['Member UUIDs (after filter)', (string) count($result['member_uuids'])],
            ['Will ingest', (string) count($members)],
        ];

        foreach ($result['seed_sources'] ?? [] as $source => $count) {
            if ($count > 0) {
                $rows[] = ["Seeds: {$source}", (string) $count];
            }
        }

        $this->table(['Metric', 'Value'], $rows);

        if ($members === []) {
            $this->newLine();
            $this->warn('No guild members to ingest.');
            $this->line(' • Pass SkyBlock guild names: --guild="Guild Name Here"');
            $this->line(' • Or set PROFILE_INGEST_GUILD_NAMES in .env');
            $this->line(' • Enable site seeds: PROFILE_INGEST_SITE_TOP=true');
            $this->line(' • Hypixel /v2/leaderboards no longer includes SKYBLOCK boards (0 UUIDs from API).');
            if ($result['seeds_scanned'] === 0) {
                $this->line(' • Add players via profile search first, or use --guild=');
            }

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach (array_slice($members, 0, 25) as $uuid) {
                $this->line($uuid);
            }
            if (count($members) > 25) {
                $this->comment('… '.(count($members) - 25).' more');
            }

            return self::SUCCESS;
        }

        $delayMs = max(0, (int) $this->option('delay-ms'));
        $ok = 0;
        $fail = 0;

        $this->info(sprintf('Ingesting %d member profile(s)…', count($members)));

        foreach ($members as $i => $uuid) {
            if ($i > 0 && $delayMs > 0) {
                usleep($delayMs * 1000);
            }

            try {
                if ($hypixelProfiles->ingestProfilesCacheForUuid($uuid, lightweight: true)) {
                    $ok++;
                } else {
                    $fail++;
                }
            } catch (\Throwable $e) {
                $fail++;
                Log::warning('Guild crawl ingest: exception', [
                    'uuid' => $uuid,
                    'message' => $e->getMessage(),
                ]);
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $this->info("Done. OK: {$ok}, skipped/failed: {$fail}.");
        $this->comment('Run: php artisan leaderboard:rebuild-snapshot');

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
