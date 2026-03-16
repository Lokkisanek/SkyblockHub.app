<?php

namespace App\Jobs;

use App\Events\MarketManipulationDetected;
use App\Models\BazaarHistory;
use App\Models\BazaarPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AnalyzeMarketManipulationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $since = Carbon::now()->subDays(7);
        $alerts = [];

        $prices = BazaarPrice::query()->get();

        foreach ($prices as $price) {
            $averageSellPrice = (float) BazaarHistory::query()
                ->where('product_id', $price->product_id)
                ->where('recorded_at', '>=', $since)
                ->avg('sell_price');

            if ($averageSellPrice <= 0) {
                continue;
            }

            $volumeSeries = Cache::get('bazaar:sell_volume_history:' . $price->product_id, []);
            if ($volumeSeries === []) {
                continue;
            }

            $averageSellVolume = max(1.0, array_sum($volumeSeries) / count($volumeSeries));
            $sellVolumeCondition = (float) $price->sell_volume < ($averageSellVolume * 0.15);
            $sellPriceCondition = (float) $price->sell_price > ($averageSellPrice * 3);

            if ($sellVolumeCondition && $sellPriceCondition) {
                $alerts[] = [
                    'product_id' => $price->product_id,
                    'sell_volume' => (int) $price->sell_volume,
                    'average_sell_volume_7d' => $averageSellVolume,
                    'sell_price' => (float) $price->sell_price,
                    'average_sell_price_7d' => $averageSellPrice,
                    'detected_at' => Carbon::now()->toISOString(),
                ];
            }
        }

        if ($alerts !== []) {
            event(new MarketManipulationDetected($alerts));
        }
    }
}
