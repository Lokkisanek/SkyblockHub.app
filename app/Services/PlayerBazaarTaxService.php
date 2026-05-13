<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PlayerBazaarTaxService
{
    private const DEFAULT_TAX_RATE = 0.01;

    private const MIN_TAX_RATE = 0.005;

    public function __construct(
        private readonly PerkService $perkService,
        private readonly HypixelApiProxy $hypixelApi,
    ) {}

    /**
     * Resolve current tax rate for a user.
     * - Default is 1.0%
     * - If user has linked Minecraft UUID and API key, Trading level reduces tax.
     */
    public function getTaxMetaForUser(?User $user): array
    {
        $rate = self::DEFAULT_TAX_RATE;
        $source = 'default';
        $tradingLevel = null;

        if ($user?->minecraft_uuid && config('hypixel.api_key')) {
            $tradingLevel = $this->fetchTradingLevel($user->minecraft_uuid);

            if ($tradingLevel !== null) {
                // Approximation: each Trading level lowers tax by 0.01% (up to 25 levels = 0.25%).
                $rate -= min($tradingLevel, 25) * 0.0001;
                $source = 'player_trading_level';
            }
        }

        // Apply mayor perk reduction if active (e.g. Diaz) from PerkService.
        $rate = $this->perkService->getBazaarTaxRate($rate);

        $rate = max(self::MIN_TAX_RATE, $rate);

        return [
            'rate' => $rate,
            'source' => $source,
            'trading_level' => $tradingLevel,
        ];
    }

    /**
     * Combined rates for instant-buy → instant-sell bazaar flips (Hypixel quick_status).
     *
     * @return array{
     *     instant_buy_tax_rate: float,
     *     instant_sell_tax_rate: float,
     *     sell_keep_multiplier: float,
     *     buy_cost_multiplier: float,
     *     buy_tax_meta: array
     * }
     */
    public function getBazaarFlipTaxForUser(?User $user): array
    {
        $buyMeta = $this->getTaxMetaForUser($user);
        $buyRate = (float) ($buyMeta['rate'] ?? self::DEFAULT_TAX_RATE);
        $sellRate = $this->perkService->getInstantSellBazaarTaxRate();

        $buyRate = max(0.0, min(0.2, $buyRate));
        $sellRate = max(0.0, min(0.3, $sellRate));

        return [
            'instant_buy_tax_rate' => $buyRate,
            'instant_sell_tax_rate' => $sellRate,
            'sell_keep_multiplier' => max(0.0, 1.0 - $sellRate),
            'buy_cost_multiplier' => 1.0 + $buyRate,
            'buy_tax_meta' => $buyMeta,
        ];
    }

    private function fetchTradingLevel(string $uuid): ?int
    {
        $cacheKey = 'hypixel:trading_level:'.$uuid;

        return Cache::remember($cacheKey, 300, function () use ($uuid) {
            $data = $this->hypixelApi->getProfiles($uuid);

            if (! $data || ! ($data['success'] ?? false)) {
                return null;
            }

            $profiles = (array) ($data['profiles'] ?? []);
            $bestLevel = null;

            foreach ($profiles as $profile) {
                $members = (array) ($profile['members'] ?? []);
                $member = $members[$uuid] ?? null;
                if (! is_array($member)) {
                    continue;
                }

                $level = $this->extractTradingLevel($member);
                if ($level !== null) {
                    $bestLevel = max($bestLevel ?? 0, $level);
                }
            }

            return $bestLevel;
        });
    }

    /**
     * Best-effort parser for Trading level from Hypixel profile payload.
     */
    private function extractTradingLevel(array $member): ?int
    {
        $candidates = [
            $member['player_data']['levels']['TRADING'] ?? null,
            $member['player_data']['levels']['trading'] ?? null,
            $member['leveling']['completed_tasks']['trading'] ?? null,
            $member['leveling']['experience']['SKILL_BARTERING'] ?? null,
            $member['leveling']['experience']['SKILL_BARTER'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_int($candidate) || is_float($candidate) || ctype_digit((string) $candidate)) {
                $value = (int) floor((float) $candidate);
                if ($value > 0 && $value <= 60) {
                    return $value;
                }

                // If this is likely raw XP, convert roughly to a level bucket.
                if ($value > 60) {
                    return min(25, (int) floor($value / 50000));
                }
            }
        }

        return null;
    }
}
