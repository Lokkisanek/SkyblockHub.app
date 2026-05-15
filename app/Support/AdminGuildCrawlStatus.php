<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed status for the admin guild profile crawl UI.
 */
final class AdminGuildCrawlStatus
{
    public const CACHE_KEY = 'admin.guild_crawl.status';

    public const LOCK_KEY = 'admin.guild_crawl.lock';

    private const RUNNING_STATUSES = ['queued', 'discovering', 'ingesting'];

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        $data = Cache::get(self::CACHE_KEY);

        if (! is_array($data)) {
            return self::idle();
        }

        return array_merge(self::idle(), $data);
    }

    public static function isRunning(): bool
    {
        return in_array((string) (self::snapshot()['status'] ?? ''), self::RUNNING_STATUSES, true);
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public static function merge(array $patch): array
    {
        $next = array_merge(self::snapshot(), $patch, [
            'updated_at' => now()->toIso8601String(),
        ]);
        Cache::put(self::CACHE_KEY, $next, now()->addDays(7));

        return $next;
    }

    /**
     * @param  array<int, string>  $guildNames
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function beginRun(array $guildNames, array $options): array
    {
        Cache::put(self::LOCK_KEY, true, now()->addHours(12));

        return self::merge([
            'status' => 'queued',
            'message' => 'Queued — starting soon…',
            'cancel_requested' => false,
            'guild_names' => $guildNames,
            'options' => $options,
            'guilds_found' => 0,
            'guild_names_requested' => count($guildNames),
            'seeds_scanned' => 0,
            'api_calls' => 0,
            'total_members' => 0,
            'processed' => 0,
            'ok' => 0,
            'failed' => 0,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'current_uuid' => null,
            'recent_log' => [],
            'guild_lookups' => [],
        ]);
    }

    public static function requestCancel(): array
    {
        if (! self::isRunning()) {
            return self::snapshot();
        }

        return self::merge([
            'cancel_requested' => true,
            'message' => 'Cancel requested — stopping after current player…',
        ]);
    }

    public static function finish(string $status, string $message): array
    {
        Cache::forget(self::LOCK_KEY);

        return self::merge([
            'status' => $status,
            'message' => $message,
            'finished_at' => now()->toIso8601String(),
            'current_uuid' => null,
        ]);
    }

    public static function appendLog(string $line): void
    {
        $snap = self::snapshot();
        $log = is_array($snap['recent_log'] ?? null) ? $snap['recent_log'] : [];
        array_unshift($log, [
            'at' => now()->toIso8601String(),
            'line' => $line,
        ]);
        $log = array_slice($log, 0, 40);

        self::merge(['recent_log' => $log]);
    }

    public static function shouldCancel(): bool
    {
        return (bool) (self::snapshot()['cancel_requested'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    private static function idle(): array
    {
        return [
            'status' => 'idle',
            'message' => 'Paste guild names and start a crawl.',
            'cancel_requested' => false,
            'guild_names' => [],
            'options' => [],
            'guilds_found' => 0,
            'guild_names_requested' => 0,
            'seeds_scanned' => 0,
            'api_calls' => 0,
            'total_members' => 0,
            'processed' => 0,
            'ok' => 0,
            'failed' => 0,
            'started_at' => null,
            'finished_at' => null,
            'updated_at' => null,
            'current_uuid' => null,
            'recent_log' => [],
            'guild_lookups' => [],
        ];
    }
}
