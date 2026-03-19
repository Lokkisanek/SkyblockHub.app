<?php

namespace App\Console\Commands;

use App\Models\BinSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchLowestBinData extends Command
{
    protected $signature = 'bin:fetch';

    protected $description = 'Fetch lowest BIN auction data from the Hypixel API';

    public function handle(): int
    {
        $this->info('Fetching lowest BIN data…');

        try {
            $response = $this->fetchWithRetry('https://api.hypixel.net/v2/skyblock/auctions?page=0');

            if (!$response->successful()) {
                $this->error('API returned status '.$response->status());
                return self::FAILURE;
            }

            $data = $response->json();
            $totalPages = (int) ($data['totalPages'] ?? 1);

            $now = now();
            $inserted = 0;

            $inserted += $this->processAuctionsPage($data['auctions'] ?? [], $now);

            // Fetch all pages so BIN sniper sees the full market.
            for ($page = 1; $page < $totalPages; $page++) {
                $pageResponse = $this->fetchWithRetry("https://api.hypixel.net/v2/skyblock/auctions?page={$page}");
                if (! $pageResponse->successful()) {
                    usleep(200_000);
                    continue;
                }

                $pageData = $pageResponse->json();
                $inserted += $this->processAuctionsPage($pageData['auctions'] ?? [], $now);
                usleep(200_000); // 200ms between pages
            }

            // Prune old snapshots (keep ~24h history for sniper avg price checks)
            BinSnapshot::where('recorded_at', '<', now()->subHours(30))->delete();

            $this->info("Processed {$inserted} BIN auctions across {$totalPages} pages.");
            return self::SUCCESS;

        } catch (\Exception $e) {
            Log::error('bin:fetch failed', ['error' => $e->getMessage()]);
            $this->error('Failed: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    private function processAuctionsPage(array $auctions, \Illuminate\Support\Carbon $recordedAt): int
    {
        $inserted = 0;

        foreach ($auctions as $auction) {
            if (($auction['bin'] ?? false) !== true || ($auction['claimed'] ?? false)) {
                continue;
            }

            if (! isset($auction['uuid'], $auction['starting_bid'])) {
                continue;
            }

                $itemName = $this->normalizeItemName((string) ($auction['item_name'] ?? 'Unknown Item'));
                $internalName = $this->deriveInternalName($auction, $itemName);
                $itemId = (string) ($auction['item_uuid'] ?? $internalName);
                $itemKey = $itemId . '|' . $internalName;

                BinSnapshot::updateOrCreate(
                    ['auction_uuid' => $auction['uuid']],
                    [
                        'item_name'       => $itemName,
                        'item_id'         => $itemId,
                        'internal_name'   => $internalName,
                        'item_key'        => $itemKey,
                        'price'           => $auction['starting_bid'],
                        'tier'            => $auction['tier'] ?? null,
                        'seller_username' => null, // UUID only in API
                        'ends_at'         => isset($auction['end']) ? \Carbon\Carbon::createFromTimestampMs($auction['end']) : null,
                        'recorded_at'     => $recordedAt,
                    ]
                );
                $inserted++;
            }

        return $inserted;
    }

    private function fetchWithRetry(string $url, int $maxRetries = 3): \Illuminate\Http\Client\Response
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                return $response;
            }

            if ($attempt >= $maxRetries || !in_array($response->status(), [429, 502, 503, 504])) {
                return $response;
            }

            $wait = (int) pow(2, $attempt);
            $this->warn("Retry {$attempt}/{$maxRetries} after {$wait}s…");
            sleep($wait);
        }
    }

    private function normalizeItemName(string $itemName): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($itemName)) ?? $itemName);

        return $clean !== '' ? $clean : 'Unknown Item';
    }

    private function deriveInternalName(array $auction, string $fallbackName): string
    {
        $source = (string) ($auction['item_name'] ?? $fallbackName);
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', $source) ?? '');
        $normalized = trim($normalized, '_');

        return $normalized !== '' ? $normalized : 'UNKNOWN_ITEM';
    }
}
