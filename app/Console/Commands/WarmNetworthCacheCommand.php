<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\NetworthEnvironment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Pre-fetch SkyHelper prices + verify Node can run scripts/networth.cjs (run after deploy).
 */
class WarmNetworthCacheCommand extends Command
{
    protected $signature = 'networth:warm-cache';

    protected $description = 'Warm SkyHelper prices cache and verify networth Node subprocess';

    public function handle(): int
    {
        if (! NetworthEnvironment::skyhelperInstalled(base_path())) {
            $this->error('skyhelper-networth missing — run npm ci in project root.');

            return self::FAILURE;
        }

        $node = NetworthEnvironment::resolveNodeBinary();
        $script = base_path('scripts/networth.cjs');
        $storageApp = storage_path('app');

        if (! File::isDirectory($storageApp)) {
            File::makeDirectory($storageApp, 0755, true);
        }

        $this->line('Node: '.$node);
        $this->line('Warming prices + items (may take up to ~30s on first run)…');

        $inFile = tempnam(sys_get_temp_dir(), 'sbh_nw_warm_in_');
        $outFile = tempnam(sys_get_temp_dir(), 'sbh_nw_warm_out_');

        $payload = json_encode([
            'profileData' => [
                'currencies' => ['coin_purse' => 0],
                'profile' => ['bank_account' => 0],
            ],
            'museumData' => new \stdClass,
            'bankBalance' => 0,
        ], JSON_THROW_ON_ERROR);

        file_put_contents($inFile, $payload);

        $cmd = escapeshellarg($node).' '.escapeshellarg($script).' '
            .escapeshellarg($outFile).' '.escapeshellarg($inFile);

        $descriptor = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptor, $pipes, base_path());
        if (! is_resource($process)) {
            @unlink($inFile);
            @unlink($outFile);
            $this->error('proc_open failed — check disable_functions and HYPIXEL_NETWORTH_NODE_BINARY.');

            return self::FAILURE;
        }

        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($process);

        @unlink($inFile);

        if ($code !== 0 || ! is_file($outFile)) {
            @unlink($outFile);
            $this->error('networth.cjs failed (exit '.$code.')');
            if ($stderr !== '') {
                $this->line(trim($stderr));
            }

            return self::FAILURE;
        }

        $json = json_decode((string) file_get_contents($outFile), true);
        @unlink($outFile);

        if (! is_array($json) || ! array_key_exists('networth', $json)) {
            $this->error('Invalid networth.cjs output.');

            return self::FAILURE;
        }

        $pricesCache = storage_path('app/.skyhelper-prices-cache.json');
        $itemsBackup = base_path('node_modules/skyhelper-networth/.itemsBackup.json');

        $this->info('SkyHelper networth subprocess OK.');
        $this->line('Prices cache: '.(is_file($pricesCache) ? 'yes' : 'no'));
        $this->line('Items backup: '.(is_file($itemsBackup) ? 'yes ('.number_format((int) filesize($itemsBackup)).' bytes)' : 'no'));

        return self::SUCCESS;
    }
}
