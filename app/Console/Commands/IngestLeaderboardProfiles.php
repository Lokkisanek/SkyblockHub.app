<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\SkyCryptProxyController;
use App\Models\ProfileSearch;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class IngestLeaderboardProfiles extends Command
{
    protected $signature = 'leaderboard:ingest-searched {--limit=100 : Max number of usernames to sync} {--usernames= : Comma separated Minecraft usernames to ingest directly}';

    protected $description = 'Fetches SkyCrypt data for searched usernames and stores profile cache rows for leaderboard.';

    public function handle(SkyCryptProxyController $skyCryptProxyController): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $manualUsernames = collect(explode(',', (string) $this->option('usernames')))
            ->map(fn (string $name) => trim($name))
            ->filter(fn (string $name) => $name !== '')
            ->values();

        $usernames = $manualUsernames;

        if ($usernames->isEmpty()) {
            $usernames = ProfileSearch::query()
                ->select('username')
                ->whereNotNull('username')
                ->groupBy('username')
                ->orderByRaw('MAX(searched_at) DESC')
                ->limit($limit)
                ->pluck('username')
                ->filter()
                ->values();
        }

        if ($usernames->isEmpty()) {
            $this->warn('No usernames found. Use --usernames=Name1,Name2 or search profiles first.');
            return self::SUCCESS;
        }

        $this->info('Syncing '.$usernames->count().' usernames into leaderboard cache...');

        $ok = 0;
        $failed = 0;

        foreach ($usernames as $username) {
            $fakeRequest = Request::create('/api/skycrypt/'.rawurlencode((string) $username), 'GET');
            $response = $skyCryptProxyController->profile($fakeRequest, (string) $username);
            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                $ok++;
                $this->line("[ok] {$username}");
                continue;
            }

            $failed++;
            $this->line("[fail {$status}] {$username}");
        }

        $this->newLine();
        $this->info("Finished. Success: {$ok}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
