<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGuildCrawlJob;
use App\Services\GuildCrawlService;
use App\Support\AdminGuildCrawlStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGuildCrawlController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'guild_crawl' => AdminGuildCrawlStatus::snapshot(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        if (trim((string) config('hypixel.api_key', '')) === '') {
            return response()->json([
                'message' => 'HYPIXEL_API_KEY is not set.',
            ], 422);
        }

        if (AdminGuildCrawlStatus::isRunning()) {
            return response()->json([
                'message' => 'A guild crawl is already running.',
                'guild_crawl' => AdminGuildCrawlStatus::snapshot(),
            ], 409);
        }

        $queueError = self::queueConfigurationError();
        if ($queueError !== null) {
            return response()->json([
                'message' => $queueError,
            ], 422);
        }

        $validated = $request->validate([
            'guild_list' => ['required', 'string', 'max:50000'],
            'max_guilds' => ['nullable', 'integer', 'min:1', 'max:200'],
            'seed_limit' => ['nullable', 'integer', 'min:0', 'max:500'],
            'member_limit' => ['nullable', 'integer', 'min:1', 'max:25000'],
            'delay_ms' => ['nullable', 'integer', 'min:0', 'max:15000'],
            'new_only' => ['nullable', 'boolean'],
        ]);

        $guildNames = GuildCrawlService::parseGuildNamesOption($validated['guild_list']);
        if ($guildNames === []) {
            return response()->json([
                'message' => 'Add at least one guild name (one per line or comma-separated).',
            ], 422);
        }

        $delayMs = (int) ($validated['delay_ms'] ?? config('hypixel.profile_ingest.delay_ms', 2000));

        $options = [
            'max_guilds' => (int) ($validated['max_guilds'] ?? config('hypixel.profile_ingest.guild_crawl.max_guilds_per_run', 15)),
            'seed_limit' => (int) ($validated['seed_limit'] ?? config('hypixel.profile_ingest.guild_crawl.seed_limit', 60)),
            'member_limit' => (int) ($validated['member_limit'] ?? config('hypixel.profile_ingest.guild_crawl.max_members_per_run', 500)),
            'delay_ms' => $delayMs,
            'guild_lookup_delay_ms' => min(800, max(350, (int) floor($delayMs / 4))),
            'new_only' => (bool) ($validated['new_only'] ?? true),
        ];

        $snapshot = AdminGuildCrawlStatus::beginRun($guildNames, $options);

        ProcessGuildCrawlJob::dispatch($guildNames, $options);

        return response()->json([
            'message' => 'Guild crawl started.',
            'guild_crawl' => $snapshot,
        ]);
    }

    public function cancel(): JsonResponse
    {
        $snapshot = AdminGuildCrawlStatus::requestCancel();

        return response()->json([
            'message' => AdminGuildCrawlStatus::isRunning()
                ? 'Cancel requested.'
                : 'No crawl is running.',
            'guild_crawl' => $snapshot,
        ]);
    }

    private static function queueConfigurationError(): ?string
    {
        $connection = (string) config('queue.default', 'sync');

        if ($connection === 'sync') {
            return 'QUEUE_CONNECTION is "sync". Guild crawls cannot run in the browser — set QUEUE_CONNECTION=database in .env, run php artisan migrate && php artisan config:clear, then php artisan queue:work --timeout=7200. Or use SSH: php artisan profiles:crawl-guilds --guild="Your Guild"';
        }

        $jobsTable = (string) config('queue.connections.database.table', 'jobs');
        if (! \Illuminate\Support\Facades\Schema::hasTable($jobsTable)) {
            return 'Queue jobs table is missing. Run: php artisan migrate';
        }

        $retryAfter = (int) config('queue.connections.database.retry_after', 90);
        if ($retryAfter < 7200) {
            return 'DB_QUEUE_RETRY_AFTER is too low ('.$retryAfter.'s). Set DB_QUEUE_RETRY_AFTER=7500 in .env and php artisan config:clear.';
        }

        return null;
    }
}
