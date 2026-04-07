<?php

namespace App\Console\Commands;

use App\Events\BazaarDataUpdated;
use App\Models\BazaarItem;
use App\Models\PriceHistory;
use App\Services\HypixelApiProxy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchBazaarData extends Command
{
    protected $signature = 'bazaar:fetch';

    protected $description = 'Fetch latest Bazaar data from the Hypixel API and store it in the database';

    /**
     * Maximum retries on rate-limit (HTTP 429) or server errors.
     */
    private const MAX_RETRIES = 3;

    public function handle(HypixelApiProxy $proxy): int
    {
        $this->info('Fetching Bazaar data from Hypixel API…');

        $data = $proxy->getBazaar();

        if ($data === null || ! ($data['success'] ?? false)) {
            $this->error('Failed to fetch Bazaar data.');
            return self::FAILURE;
        }

        $products = $data['products'] ?? [];
        $this->info('Received ' . count($products) . ' products. Processing…');

        $now           = now();
        $processed     = 0;
        $broadcastBatch = [];

        foreach ($products as $productId => $data) {
            $sellSummary = $data['sell_summary'] ?? [];
            $buySummary  = $data['buy_summary']  ?? [];
            $quickStatus = $data['quick_status'] ?? [];

            // Top-of-book prices
            $topSellPrice = isset($sellSummary[0]) ? (float) $sellSummary[0]['pricePerUnit'] : 0;
            $topBuyPrice  = isset($buySummary[0])  ? (float) $buySummary[0]['pricePerUnit']  : 0;

            // Volumes & order counts from quick_status
            $sellVolume     = (int) ($quickStatus['sellVolume']     ?? 0);
            $buyVolume      = (int) ($quickStatus['buyVolume']      ?? 0);
            $sellOrders     = (int) ($quickStatus['sellOrders']     ?? 0);
            $buyOrders      = (int) ($quickStatus['buyOrders']      ?? 0);
            $sellMovingWeek = (float) ($quickStatus['sellMovingWeek'] ?? 0);
            $buyMovingWeek  = (float) ($quickStatus['buyMovingWeek']  ?? 0);

            // ---- Margin & Velocity calculations ----
            // Margin = spread between instant-buy (top buy order) and instant-sell (top sell order)
            // Positive margin means profit when buy-ordering then sell-ordering.
            $margin        = $topBuyPrice - $topSellPrice;
            $marginPercent = $topSellPrice > 0
                ? round(($margin / $topSellPrice) * 100, 2)
                : 0;

            // Velocity = average items traded per hour over the last 7 days
            $hoursInWeek  = 7 * 24;
            $sellVelocity = $sellMovingWeek > 0 ? round($sellMovingWeek / $hoursInWeek, 2) : 0;
            $buyVelocity  = $buyMovingWeek  > 0 ? round($buyMovingWeek  / $hoursInWeek, 2) : 0;

            // Readable name: ENCHANTED_DIAMOND_BLOCK → Enchanted Diamond Block
            $name = $this->humanise($productId);

            // Upsert the bazaar item
            $item = BazaarItem::updateOrCreate(
                ['product_id' => $productId],
                [
                    'name'             => $name,
                    'sell_price'       => $topSellPrice,
                    'buy_price'        => $topBuyPrice,
                    'sell_volume'      => $sellVolume,
                    'buy_volume'       => $buyVolume,
                    'sell_orders'      => $sellOrders,
                    'buy_orders'       => $buyOrders,
                    'sell_moving_week' => $sellMovingWeek,
                    'buy_moving_week'  => $buyMovingWeek,
                    'last_updated'     => $now,
                ]
            );

            // Record a price-history snapshot
            PriceHistory::create([
                'bazaar_item_id' => $item->id,
                'sell_price'     => $topSellPrice,
                'buy_price'      => $topBuyPrice,
                'sell_volume'    => $sellVolume,
                'buy_volume'     => $buyVolume,
                'recorded_at'    => $now,
            ]);

            // Collect data for broadcast
            $broadcastBatch[$productId] = [
                'id'              => $item->id,
                'product_id'      => $productId,
                'name'            => $name,
                'sell_price'      => $topSellPrice,
                'buy_price'       => $topBuyPrice,
                'sell_volume'     => $sellVolume,
                'buy_volume'      => $buyVolume,
                'sell_orders'     => $sellOrders,
                'buy_orders'      => $buyOrders,
                'sell_moving_week' => $sellMovingWeek,
                'buy_moving_week'  => $buyMovingWeek,
            ];

            $processed++;
        }

        // Broadcast live update to all connected clients
        if (! empty($broadcastBatch)) {
            BazaarDataUpdated::dispatch($broadcastBatch);
        }

        $this->info("Done. Processed {$processed} products.");

        return self::SUCCESS;
    }

    /**
     * Turn UPPER_SNAKE_CASE product IDs into Title Case names.
     */
    private function humanise(string $productId): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $productId)));
    }
}
