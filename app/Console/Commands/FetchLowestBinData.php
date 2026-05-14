<?php

namespace App\Console\Commands;

use App\Models\BinSnapshot;
use App\Services\HypixelApiProxy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchLowestBinData extends Command
{
    protected $signature = 'bin:fetch';

    protected $description = 'Fetch lowest BIN auction data from the Hypixel API';

    public function handle(HypixelApiProxy $proxy): int
    {
        $this->info('Fetching lowest BIN data…');

        try {
            $data = $proxy->getAuctions(0);

            if ($data === null) {
                $this->error('API returned no data');

                return self::FAILURE;
            }

            $totalPages = (int) ($data['totalPages'] ?? 1);
            $maxPages = max(1, (int) config('hypixel.auction_fetch_max_pages', 120));
            $pagesToFetch = min($totalPages, $maxPages);
            $delayUs = max(0, (int) config('hypixel.auction_fetch_delay_ms', 650)) * 1000;

            $now = now();
            $inserted = 0;

            $inserted += $this->processAuctionsPage($data['auctions'] ?? [], $now);

            for ($page = 1; $page < $pagesToFetch; $page++) {
                $pageData = $proxy->getAuctions($page);
                if ($pageData === null) {
                    if ($delayUs > 0) {
                        usleep($delayUs);
                    }

                    continue;
                }

                $inserted += $this->processAuctionsPage($pageData['auctions'] ?? [], $now);
                if ($delayUs > 0) {
                    usleep($delayUs);
                }
            }

            if ($totalPages > $maxPages) {
                $this->warn("Auction pages capped at {$maxPages} of {$totalPages} (HYPIXEL_AUCTION_FETCH_MAX_PAGES).");
            }

            BinSnapshot::where('recorded_at', '<', now()->subHours(30))->delete();

            $this->info("Processed {$inserted} BIN auctions across {$pagesToFetch} of {$totalPages} pages.");

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
            $itemKey = $itemId.'|'.$internalName;

            BinSnapshot::updateOrCreate(
                ['auction_uuid' => $auction['uuid']],
                [
                    'item_name' => $itemName,
                    'item_id' => $itemId,
                    'internal_name' => $internalName,
                    'item_key' => $itemKey,
                    'price' => $auction['starting_bid'],
                    'tier' => $auction['tier'] ?? null,
                    'seller_username' => null,
                    'ends_at' => isset($auction['end']) ? Carbon::createFromTimestampMs($auction['end']) : null,
                    'recorded_at' => $recordedAt,
                ]
            );
            $inserted++;
        }

        return $inserted;
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
