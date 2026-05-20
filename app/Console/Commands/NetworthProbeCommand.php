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

        $skyhelperDir = $base.'/node_modules/skyhelper-networth';
        if ($skyhelper && is_dir($skyhelperDir)) {
            if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
                $this->warn('Running as root: writable checks may not match php-fpm (e.g. www-data). Prefer: sudo -u www-data php artisan networth:probe');
            }

            $backupPath = $skyhelperDir.'/.itemsBackup.json';
            $isLink = is_link($backupPath);
            $linkTarget = $isLink ? (string) readlink($backupPath) : '';
            $this->line('items backup .itemsBackup.json: '.($isLink ? 'symlink → '.$linkTarget : 'not a symlink (run npm install to link into storage/app/skyhelper-networth/)'));

            // PHP is_writable() on a symlink follows the target file.
            $backupWritable = (file_exists($backupPath) || $isLink) ? @is_writable($backupPath) : @is_writable($skyhelperDir);
            if (! $backupWritable && ! file_exists($backupPath)) {
                $backupWritable = @is_writable($skyhelperDir);
            }

            $this->line('items backup writable (effective): '.($backupWritable ? 'yes' : 'NO'));
            if (! $backupWritable) {
                $this->error('SkyHelper must write .itemsBackup.json; EACCES breaks full networth. Fixes:');
                $this->line('  • Re-run npm install (postinstall symlinks backup into storage/app/skyhelper-networth/)');
                $this->line('  • Or: sudo chown -R www-data:www-data '.escapeshellarg($skyhelperDir));

                return self::FAILURE;
            }
        }

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
            $this->newLine();
            $this->comment('CLI passed, but the website uses php-fpm (often www-data). If Profile Stats still shows simplified pricing:');
            $this->line('  1) Re-run this probe as the web user, e.g. sudo -u www-data bash -lc \'cd '.escapeshellarg($base).' && php artisan networth:probe\'');
            $this->line('  2) Check the php-fpm pool for php_admin_value[disable_functions] blocking proc_open (CLI php.ini can differ).');
            $this->line('  3) After fixing, clear the profile HTTP cache key: hypixel:profile:<username> (or wait 5 minutes).');
            $this->line('  4) Open storage/logs and search for "Networth: skyhelper Node path failed" — the JSON response now includes pricing_failure_reason when fallback runs.');
            $this->line('  5) Ensure postinstall ran (npm ci) so .itemsBackup.json is symlinked under storage/; as root, postinstall chowns that dir to www-data.');
            $this->line('  6) tinker as www-data: use HOME inside the project, e.g. sudo -u www-data env HOME='.escapeshellarg($base).' php artisan tinker (avoids /var/www/.config/psysh errors).');
            $this->line('  7) git pull blocked by local package.json? On server: git restore package.json package-lock.json && git pull (never commit local edits there).');

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
