<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\NetworthEnvironment;
use Illuminate\Console\Command;

/**
 * Run on the server (same user as php-fpm if possible) to see why profile networth
 * falls back to simple bazaar/BIN pricing instead of skyhelper-networth.
 */
class NetworthProbeCommand extends Command
{
    protected $signature = 'networth:probe';

    protected $description = 'Check Node, skyhelper-networth, and proc_open for profile networth';

    public function handle(): int
    {
        $base = base_path();
        $this->info('Project: '.$base);

        $disabled = ini_get('disable_functions') ?: '';
        $this->line('disable_functions contains proc_open: '.(str_contains($disabled, 'proc_open') ? 'YES (broken)' : 'no'));

        $skyhelper = NetworthEnvironment::skyhelperInstalled($base);
        $this->line('skyhelper-networth in node_modules: '.($skyhelper ? 'yes' : 'NO — run npm ci in project root'));

        $node = NetworthEnvironment::resolveNodeBinary();
        $this->line('Resolved Node binary: '.$node);
        $this->line('is_executable: '.(@is_executable($node) ? 'yes' : 'no'));

        if (! function_exists('proc_open')) {
            $this->error('proc_open is disabled — PHP cannot spawn Node.');

            return self::FAILURE;
        }

        if (! $skyhelper) {
            return self::FAILURE;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Do not require('skyhelper-networth') here — it can block on first-run HTTP.
        $cmd = [$node, '-e', 'if (process.version) { console.log("NODE_OK", process.version); process.exit(0); } process.exit(1);'];
        $process = @proc_open($cmd, $descriptors, $pipes, $base);
        if (! is_resource($process)) {
            $this->error('proc_open failed to start Node (permissions / open_basedir / SELinux?).');

            return self::FAILURE;
        }

        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 5);
        stream_set_timeout($pipes[2], 5);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        $stdout = trim((string) $stdout);
        $stderr = trim((string) $stderr);

        if ($code === 0 && str_starts_with($stdout, 'NODE_OK')) {
            $this->info('proc_open + Node from project cwd: OK ('.$stdout.')');
            $this->comment('If networth is still wrong on the site, open a profile and check storage/logs for "Networth: skyhelper Node path failed" with a `reason` field.');

            return self::SUCCESS;
        }

        $this->error('Node smoke test FAILED (exit '.$code.')');
        if ($stdout !== '') {
            $this->line('stdout: '.$stdout);
        }
        if ($stderr !== '') {
            $this->line('stderr: '.$stderr);
        }

        return self::FAILURE;
    }
}
