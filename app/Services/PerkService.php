<?php

namespace App\Services;

class PerkService
{
    public function __construct(private readonly MayorService $mayorService) {}

    public function isPerkActive(string $perkName, ?array $mayorPayload = null): bool
    {
        $state = $this->buildState($mayorPayload);

        return (bool) ($state['active_perks'][$perkName] ?? false);
    }

    public function buildState(?array $mayorPayload = null): array
    {
        $payload = $mayorPayload ?? $this->mayorService->getCurrentMayorData();
        $mayorName = strtolower((string) ($payload['name'] ?? ''));
        $perks = (array) ($payload['perks'] ?? []);

        $textBlob = $mayorName;
        foreach ($perks as $perk) {
            $name = strtolower((string) ($perk['name'] ?? ''));
            $description = strtolower((string) ($perk['description'] ?? ''));
            $textBlob .= ' '.$name.' '.$description;
        }

        $activePerks = [
            'mythological_ritual' => str_contains($mayorName, 'diana') || str_contains($textBlob, 'mythological'),
            'jerry_workshop' => str_contains($mayorName, 'jerry') || str_contains($textBlob, 'workshop') || str_contains($textBlob, 'season of jerry'),
            'dungeon_benefit' => str_contains($mayorName, 'paul') || str_contains($textBlob, 'catacombs') || str_contains($textBlob, 'dungeon'),
            'bazaar_fee_reduction' => str_contains($mayorName, 'diaz') || str_contains($textBlob, 'bazaar') || str_contains($textBlob, 'tax') || str_contains($textBlob, 'fee'),
            'forge_speed' => str_contains($mayorName, 'cole') || str_contains($textBlob, 'forge') || str_contains($textBlob, 'mining fiesta'),
        ];

        $boostedEventKeys = [];
        if ($activePerks['mythological_ritual']) {
            $boostedEventKeys[] = 'mythological-ritual';
        }
        if ($activePerks['jerry_workshop']) {
            $boostedEventKeys[] = 'season-of-jerry';
        }
        if ($activePerks['dungeon_benefit']) {
            $boostedEventKeys[] = 'dungeon-rush';
        }

        return [
            'mayor_name' => $payload['name'] ?? 'Unknown',
            'active_perks' => $activePerks,
            'boosted_event_keys' => $boostedEventKeys,
        ];
    }

    public function getBazaarTaxRate(float $baseTaxRate = 0.0125): float
    {
        if ($this->isPerkActive('bazaar_fee_reduction')) {
            return max(0.0, $baseTaxRate - 0.0025);
        }

        return $baseTaxRate;
    }

    /**
     * Tax fraction withheld when you instant-sell into buy orders (Hypixel quick_status.buyPrice leg).
     * Base 1.25%; Diaz-style mayor lowers it; Aura (minister) raises sell fee to 2.25% (best-effort text match).
     */
    public function getInstantSellBazaarTaxRate(?array $mayorPayload = null): float
    {
        $payload = $mayorPayload ?? $this->mayorService->getCurrentMayorData();
        $mayor = strtolower((string) ($payload['name'] ?? ''));
        $perks = (array) ($payload['perks'] ?? []);
        $blob = $mayor;
        foreach ($perks as $perk) {
            $blob .= ' '.strtolower((string) ($perk['name'] ?? '')).' '.strtolower((string) ($perk['description'] ?? ''));
        }

        if (str_contains($blob, 'aura')) {
            return 0.0225;
        }

        return $this->getBazaarTaxRate(0.0125);
    }

    public function getForgeTimeMultiplier(float $defaultMultiplier = 1.0): float
    {
        if ($this->isPerkActive('forge_speed')) {
            return 0.9;
        }

        return $defaultMultiplier;
    }
}
