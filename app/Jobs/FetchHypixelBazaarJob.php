<?php

namespace App\Jobs;

use App\Events\BazaarPricesUpdated;
use App\Models\BazaarHistory;
use App\Models\BazaarPrice;
use App\Models\BazaarProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FetchHypixelBazaarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const API_ENDPOINT = 'https://api.hypixel.net/v2/skyblock/bazaar';

    public function handle(): void
    {
        $response = Http::timeout(20)->get(self::API_ENDPOINT);

        if (! $response->ok() || ! $response->json('success')) {
            return;
        }

        $products = $response->json('products', []);
        $now = Carbon::now();
        $updatedRows = [];
        $historyRows = [];
        $productRows = [];
        $priceRows = [];

        $productIds = array_keys($products);
        $previousPrices = BazaarPrice::query()
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        foreach ($products as $productId => $payload) {
            $quickStatus = $payload['quick_status'] ?? [];

            $sellPrice = (float) ($quickStatus['sellPrice'] ?? 0);
            $buyPrice = (float) ($quickStatus['buyPrice'] ?? 0);
            $sellVolume = (int) ($quickStatus['sellVolume'] ?? 0);
            $buyVolume = (int) ($quickStatus['buyVolume'] ?? 0);
            $sellMovingWeek = (float) ($quickStatus['sellMovingWeek'] ?? 0);
            $buyMovingWeek = (float) ($quickStatus['buyMovingWeek'] ?? 0);
            $sellOrders = (int) ($quickStatus['sellOrders'] ?? 0);
            $buyOrders = (int) ($quickStatus['buyOrders'] ?? 0);

            $productRows[] = [
                'product_id' => $productId,
                'name' => $this->humanizeProductId($productId),
                'category' => $this->inferCategory($productId),
                'npc_sell_price' => 0,
                'updated_at' => $now,
                'created_at' => $now,
            ];

            $priceRows[] = [
                'product_id' => $productId,
                'buy_price' => $buyPrice,
                'sell_price' => $sellPrice,
                'buy_volume' => $buyVolume,
                'sell_volume' => $sellVolume,
                'buy_moving_week' => $buyMovingWeek,
                'sell_moving_week' => $sellMovingWeek,
                'buy_orders' => $buyOrders,
                'sell_orders' => $sellOrders,
                'updated_at' => $now,
                'created_at' => $now,
            ];

            $cacheKey = 'bazaar:sell_volume_history:' . $productId;
            $volumeSeries = Cache::get($cacheKey, []);
            $volumeSeries[] = $sellVolume;
            if (count($volumeSeries) > 10080) {
                $volumeSeries = array_slice($volumeSeries, -10080);
            }
            Cache::forever($cacheKey, $volumeSeries);

            $previous = $previousPrices->get($productId);
            if ($this->priceChangedMoreThanOnePercent($previous, $buyPrice, $sellPrice)) {
                $historyRows[] = [
                    'product_id' => $productId,
                    'buy_price' => $buyPrice,
                    'sell_price' => $sellPrice,
                    'recorded_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $updatedRows[] = [
                'product_id' => $productId,
                'buy_price' => $buyPrice,
                'sell_price' => $sellPrice,
                'buy_volume' => $buyVolume,
                'sell_volume' => $sellVolume,
                'buy_moving_week' => $buyMovingWeek,
                'sell_moving_week' => $sellMovingWeek,
                'buy_orders' => $buyOrders,
                'sell_orders' => $sellOrders,
                'updated_at' => $now->toISOString(),
            ];
        }

        if ($productRows !== []) {
            BazaarProduct::query()->upsert(
                $productRows,
                ['product_id'],
                ['name', 'category', 'npc_sell_price', 'updated_at']
            );
        }

        if ($priceRows !== []) {
            BazaarPrice::query()->upsert(
                $priceRows,
                ['product_id'],
                [
                    'buy_price',
                    'sell_price',
                    'buy_volume',
                    'sell_volume',
                    'buy_moving_week',
                    'sell_moving_week',
                    'buy_orders',
                    'sell_orders',
                    'updated_at',
                ]
            );
        }

        if ($historyRows !== []) {
            BazaarHistory::query()->insert($historyRows);
        }

        if ($updatedRows !== [] && config('broadcasting.default') !== 'log') {
            event(new BazaarPricesUpdated($updatedRows));
        }
    }

    private function priceChangedMoreThanOnePercent(?BazaarPrice $previous, float $newBuyPrice, float $newSellPrice): bool
    {
        if (! $previous) {
            return true;
        }

        $buyChange = $this->percentChange((float) $previous->buy_price, $newBuyPrice);
        $sellChange = $this->percentChange((float) $previous->sell_price, $newSellPrice);

        return $buyChange > 1 || $sellChange > 1;
    }

    private function percentChange(float $old, float $new): float
    {
        if ($old <= 0) {
            return $new > 0 ? 100 : 0;
        }

        return abs(($new - $old) / $old) * 100;
    }

    private function humanizeProductId(string $productId): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $productId)));
    }

    private function inferCategory(string $productId): string
    {
        return str_contains($productId, '_')
            ? explode('_', $productId)[0]
            : 'MISC';
    }
}
