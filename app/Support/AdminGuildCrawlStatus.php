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

    /** No status update for this long ⇒ treat the crawl as dead (PHP timeout, crashed worker). */
    private const STALE_MINUTES = 8;

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        $snapshot = self::readRaw();

        if (self::isStaleRun($snapshot)) {
            return self::writeFinished(
                $snapshot,
                'cancelled',
                'Crawl stopped (no progress for '.self::STALE_MINUTES.'+ minutes). Start again or check queue worker.',
            );
        }

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    private static function readRaw(): array
    {
        $data = Cache::get(self::CACHE_KEY);

        if (! is_array($data)) {
            return self::idle();
        }

        return array_merge(self::idle(), $data);
    }

    public static function isRunning(): bool
    {
        return self::isRunningStatus((string) (self::snapshot()['status'] ?? ''));
    }

    private static function isRunningStatus(string $status): bool
    {
        return in_array($status, self::RUNNING_STATUSES, true);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function isStaleRun(array $snapshot): bool
    {
        if (! self::isRunningStatus((string) ($snapshot['status'] ?? ''))) {
            return false;
        }

        $updatedAt = $snapshot['updated_at'] ?? null;
        if (! is_string($updatedAt) || $updatedAt === '') {
            return true;
        }

        try {
            return \Illuminate\Support\Carbon::parse($updatedAt)->lte(now()->subMinutes(self::STALE_MINUTES));
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    public static function merge(array $patch): array
    {
        $next = array_merge(self::readRaw(), $patch, [
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
        $snapshot = self::snapshot();

        if (! self::isRunningStatus((string) ($snapshot['status'] ?? ''))) {
            return $snapshot;
        }

        if (self::isStaleRun($snapshot)) {
            return self::writeFinished(
                $snapshot,
                'cancelled',
                'Crawl was stuck — status cleared. Ensure a queue worker is running for long crawls.',
            );
        }

        return self::merge([
            'cancel_requested' => true,
            'message' => 'Cancel requested — stopping after current player…',
        ]);
    }

    public static function forceReset(string $message = 'Crawl cleared manually.'): array
    {
        Cache::forget(self::LOCK_KEY);

        return self::merge([
            'status' => 'cancelled',
            'message' => $message,
            'cancel_requested' => false,
            'finished_at' => now()->toIso8601String(),
            'current_uuid' => null,
        ]);
    }

    public static function finish(string $status, string $message): array
    {
        Cache::forget(self::LOCK_KEY);

        return self::writeFinished(self::readRaw(), $status, $message);
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    private static function writeFinished(array $base, string $status, string $message): array
    {
        Cache::forget(self::LOCK_KEY);

        $next = array_merge($base, [
            'status' => $status,
            'message' => $message,
            'finished_at' => now()->toIso8601String(),
            'current_uuid' => null,
            'cancel_requested' => false,
            'updated_at' => now()->toIso8601String(),
        ]);
        Cache::put(self::CACHE_KEY, $next, now()->addDays(7));

        return $next;
    }

    public static function appendLog(string $line): void
    {
        $snap = self::readRaw();
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
        return (bool) (self::readRaw()['cancel_requested'] ?? false);
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
