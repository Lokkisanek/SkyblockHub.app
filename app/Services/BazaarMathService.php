<?php

namespace App\Services;

class BazaarMathService
{
    public function __construct(private readonly PerkService $perkService)
    {
    }

    public function calculateMargin(float $buyPrice, float $sellPrice, ?float $taxRate = null): float
    {
        $effectiveTaxRate = $taxRate ?? $this->perkService->getBazaarTaxRate(0.0125);

        return ($sellPrice - $buyPrice) - ($sellPrice * $effectiveTaxRate);
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
