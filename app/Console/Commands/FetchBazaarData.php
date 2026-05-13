<?php

namespace App\Console\Commands;

use App\Events\BazaarDataUpdated;
use App\Models\BazaarItem;
use App\Models\PriceHistory;
use App\Services\HypixelApiProxy;
use Illuminate\Console\Command;

class FetchBazaarData extends Command
{
    protected $signature = 'bazaar:fetch';

    protected $description = 'Fetch latest Bazaar data from the Hypixel API and store it in the database';

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
        $this->info('Received '.count($products).' products. Processing…');

        $now = now();
        $processed = 0;
        $broadcastBatch = [];

        foreach ($products as $productId => $data) {
            $sellSummary = $data['sell_summary'] ?? [];
            $buySummary = $data['buy_summary'] ?? [];
            $quickStatus = $data['quick_status'] ?? [];

            $topSellPrice = isset($sellSummary[0]) ? (float) $sellSummary[0]['pricePerUnit'] : 0;
            $topBuyPrice = isset($buySummary[0]) ? (float) $buySummary[0]['pricePerUnit'] : 0;

            $sellVolume = (int) ($quickStatus['sellVolume'] ?? 0);
            $buyVolume = (int) ($quickStatus['buyVolume'] ?? 0);
            $sellOrders = (int) ($quickStatus['sellOrders'] ?? 0);
            $buyOrders = (int) ($quickStatus['buyOrders'] ?? 0);
            $sellMovingWeek = (float) ($quickStatus['sellMovingWeek'] ?? 0);
            $buyMovingWeek = (float) ($quickStatus['buyMovingWeek'] ?? 0);

            $margin = $topBuyPrice - $topSellPrice;
            $marginPercent = $topSellPrice > 0
                ? round(($margin / $topSellPrice) * 100, 2)
                : 0;

            $hoursInWeek = 7 * 24;
            $sellVelocity = $sellMovingWeek > 0 ? round($sellMovingWeek / $hoursInWeek, 2) : 0;
            $buyVelocity = $buyMovingWeek > 0 ? round($buyMovingWeek / $hoursInWeek, 2) : 0;

            $name = $this->humanise($productId);

            $item = BazaarItem::updateOrCreate(
                ['product_id' => $productId],
                [
                    'name' => $name,
                    'sell_price' => $topSellPrice,
                    'buy_price' => $topBuyPrice,
                    'sell_volume' => $sellVolume,
                    'buy_volume' => $buyVolume,
                    'sell_orders' => $sellOrders,
                    'buy_orders' => $buyOrders,
                    'sell_moving_week' => $sellMovingWeek,
                    'buy_moving_week' => $buyMovingWeek,
                    'last_updated' => $now,
                ]
            );

            PriceHistory::create([
                'bazaar_item_id' => $item->id,
                'sell_price' => $topSellPrice,
                'buy_price' => $topBuyPrice,
                'sell_volume' => $sellVolume,
                'buy_volume' => $buyVolume,
                'recorded_at' => $now,
            ]);

            $broadcastBatch[$productId] = [
                'id' => $item->id,
                'product_id' => $productId,
                'name' => $name,
                'sell_price' => $topSellPrice,
                'buy_price' => $topBuyPrice,
                'sell_volume' => $sellVolume,
                'buy_volume' => $buyVolume,
                'sell_orders' => $sellOrders,
                'buy_orders' => $buyOrders,
                'sell_moving_week' => $sellMovingWeek,
                'buy_moving_week' => $buyMovingWeek,
            ];

            $processed++;
        }

        if (! empty($broadcastBatch)) {
            BazaarDataUpdated::dispatch($broadcastBatch);
        }

        $this->info("Done. Processed {$processed} products.");

        return self::SUCCESS;
    }

    private function humanise(string $productId): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $productId)));
    }
}
