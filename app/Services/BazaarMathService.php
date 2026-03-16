<?php

namespace App\Services;

class BazaarMathService
{
    public function calculateMargin(float $buyPrice, float $sellPrice, float $taxRate = 0.0125): float
    {
        return ($sellPrice - $buyPrice) - ($sellPrice * $taxRate);
    }

    public function calculateVelocity(int $sellVolume, int $buyVolume): int
    {
        return (int) floor(($sellVolume + $buyVolume) / 24);
    }

    public function calculateProfitScore(float $margin, int $velocity): float
    {
        return $margin * log10($velocity + 1);
    }
}
