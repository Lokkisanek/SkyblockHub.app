<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Node binary resolution for scripts/networth.cjs (php-fpm often has no node on PATH).
 */
final class NetworthEnvironment
{
    public static function resolveNodeBinary(): string
    {
        $configured = trim((string) config('hypixel.networth_node_binary', ''));
        if ($configured !== '' && @is_executable($configured)) {
            return $configured;
        }

        foreach (['/usr/bin/node', '/usr/local/bin/node'] as $path) {
            if (@is_executable($path)) {
                return $path;
            }
        }

        return $configured !== '' ? $configured : 'node';
    }

    public static function skyhelperInstalled(string $basePath): bool
    {
        return is_file($basePath.'/node_modules/skyhelper-networth/package.json');
    }
}
