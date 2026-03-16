<?php

namespace App\Services;

use App\Models\BazaarPrice;

class FarmingCalculatorService
{
    public function calculateHourlyYield(string $cropType, float $farmingFortune, int $blocksBrokenPerSecond = 20): array
    {
        $baseDrops = $this->baseDropsForCrop($cropType);
        $multiplier = 1 + ($farmingFortune / 100);
        $itemsPerHour = $blocksBrokenPerSecond * 3600 * $baseDrops * $multiplier;

        $currentSellPrice = (float) (BazaarPrice::query()
            ->where('product_id', $cropType)
            ->value('sell_price') ?? 0);

        $grossProfitPerHour = $itemsPerHour * $currentSellPrice;

        return [
            'crop_type' => $cropType,
            'farming_fortune' => $farmingFortune,
            'blocks_broken_per_second' => $blocksBrokenPerSecond,
            'base_drops' => $baseDrops,
            'multiplier' => $multiplier,
            'items_per_hour' => $itemsPerHour,
            'gross_profit_per_hour' => $grossProfitPerHour,
            'current_bazaar_sell_price' => $currentSellPrice,
        ];
    }

    private function baseDropsForCrop(string $cropType): float
    {
        return match ($cropType) {
            'ENCHANTED_NETHER_STALK' => 2.5,
            'ENCHANTED_CARROT' => 3.0,
            'ENCHANTED_POTATO' => 3.0,
            'ENCHANTED_WHEAT' => 1.0,
            default => 1.0,
        };
    }
}
