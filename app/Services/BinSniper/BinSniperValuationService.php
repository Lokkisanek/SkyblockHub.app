<?php

namespace App\Services\BinSniper;

use App\Utils\NbtParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BinSniperValuationService
{
    /**
     * Analyze one auction with advanced valuation model and return sniper decision.
     *
     * @param  array<string, mixed>  $auction
     * @param  array<int, float|int|string>  $basePriceSamples
     * @param  array<string, float|int|string>  $marketOverrides
     * @return array<string, mixed>
     */
    public function analyzeAuction(array $auction, array $basePriceSamples, array $marketOverrides = []): array
    {
        $cfg = config('bin_sniper');

        $prices = $this->sanitizePrices($basePriceSamples);
        $lbin = (float) ($auction['lbin_price'] ?? $auction['price'] ?? 0);
        $slbin = (float) ($auction['slbin_price'] ?? 0);
        $liquidity24h = max(0, (int) ($auction['liquidity_24h'] ?? 0));

        $baseValue = $this->computeIqrMedianBaseValue($prices, (float) ($cfg['statistics']['iqr_multiplier'] ?? 1.5));

        $components = $this->extractComponentsFromAuction($auction);
        $marketPrices = $this->resolveComponentMarketPrices($components, $marketOverrides);

        $weights = $cfg['component_weights'] ?? [];
        $componentRows = [];
        $componentTotal = 0.0;

        foreach ($components as $component) {
            $componentKey = (string) ($component['weight_key'] ?? '');
            $componentName = (string) ($component['name'] ?? 'Unknown Component');
            $qty = max(1, (int) ($component['quantity'] ?? 1));
            $marketPrice = (float) ($marketPrices[$componentName] ?? 0);
            $weight = (float) ($weights[$componentKey] ?? 1.0);
            $applied = $marketPrice * $qty * $weight;

            $componentTotal += $applied;
            $componentRows[] = [
                'name' => $componentName,
                'market_price' => $marketPrice,
                'applied_value' => round($applied, 2),
                'quantity' => $qty,
                'weight_multiplier' => $weight,
            ];
        }

        $trueValue = $baseValue + $componentTotal;

        $ratioMax = (float) ($cfg['statistics']['manipulation_ratio_max'] ?? 3.0);
        $ratio = ($lbin > 0 && $slbin > 0) ? ($slbin / $lbin) : 0.0;
        $manipulated = $ratio > $ratioMax;

        $tax = $this->calculateTax(max($lbin, $trueValue));
        $profitCoins = $trueValue - ($lbin + $tax);
        $profitPct = $lbin > 0 ? ($profitCoins / $lbin) * 100 : 0.0;

        $confidence = $this->calculateConfidenceScore(
            $profitPct,
            $liquidity24h,
            $manipulated,
            (int) ($cfg['statistics']['min_daily_volume'] ?? 5)
        );

        $thresholdCoins = (float) ($cfg['profit_thresholds']['min_profit_coins'] ?? 500000);
        $thresholdPct = (float) ($cfg['profit_thresholds']['min_profit_percentage'] ?? 10.0);

        $isSnipe = ! $manipulated
            && $profitCoins >= $thresholdCoins
            && $profitPct >= $thresholdPct;

        return [
            'item_uuid' => (string) ($auction['item_uuid'] ?? $auction['auction_uuid'] ?? ''),
            'item_name' => (string) ($auction['item_name'] ?? 'Unknown Item'),
            'tier' => (string) ($auction['tier'] ?? 'UNKNOWN'),
            'analysis' => [
                'is_snipe' => $isSnipe,
                'manipulated' => $manipulated,
                'prices' => [
                    'lbin' => round($lbin, 2),
                    'slbin' => round($slbin, 2),
                    'base_value_median' => round($baseValue, 2),
                    'true_value' => round($trueValue, 2),
                ],
                'components_found' => $componentRows,
                'profit_metrics' => [
                    'estimated_tax' => round($tax, 2),
                    'potential_profit_coins' => round($profitCoins, 2),
                    'profit_percentage' => round($profitPct, 2),
                ],
                'confidence_score' => round($confidence, 1),
                'liquidity_24h' => $liquidity24h,
            ],
            'command' => '/viewauction ' . str_replace('-', '', (string) ($auction['item_uuid'] ?? $auction['auction_uuid'] ?? '')),
        ];
    }

    /**
     * @param  array<int, float>  $prices
     */
    private function computeIqrMedianBaseValue(array $prices, float $iqrMultiplier): float
    {
        if ($prices === []) {
            return 0.0;
        }

        sort($prices);

        $q1 = $this->percentile($prices, 25);
        $q3 = $this->percentile($prices, 75);
        $iqr = $q3 - $q1;

        $lower = $q1 - ($iqrMultiplier * $iqr);
        $upper = $q3 + ($iqrMultiplier * $iqr);

        $filtered = array_values(array_filter($prices, static function (float $p) use ($lower, $upper): bool {
            return $p >= $lower && $p <= $upper;
        }));

        if ($filtered === []) {
            $filtered = $prices;
        }

        return $this->median($filtered);
    }

    /**
     * @param  array<int, float>  $values
     */
    private function percentile(array $values, float $p): float
    {
        if ($values === []) {
            return 0.0;
        }

        $n = count($values);
        if ($n === 1) {
            return $values[0];
        }

        $rank = ($p / 100) * ($n - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);

        if ($low === $high) {
            return $values[$low];
        }

        $weight = $rank - $low;
        return $values[$low] + ($values[$high] - $values[$low]) * $weight;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $values[$mid];
        }

        return ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /**
     * @param  array<int, float|int|string>  $prices
     * @return array<int, float>
     */
    private function sanitizePrices(array $prices): array
    {
        $out = [];
        foreach ($prices as $price) {
            $value = (float) $price;
            if ($value > 0) {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $auction
     * @return array<int, array{name:string,weight_key:string,quantity:int}>
     */
    private function extractComponentsFromAuction(array $auction): array
    {
        $components = [];
        $extra = $this->extractExtraAttributes($auction);

        if ($extra === []) {
            return $components;
        }

        if ((int) ($extra['rarity_upgrades'] ?? 0) > 0) {
            $components[] = ['name' => 'Recombobulator 3000', 'weight_key' => 'recombobulator', 'quantity' => 1];
        }

        $hotCount = max(0, (int) ($extra['hot_potato_count'] ?? 0));
        $hotBooks = min(10, $hotCount);
        $fumingBooks = max(0, $hotCount - 10);

        if ($hotBooks > 0) {
            $components[] = ['name' => 'Hot Potato Book', 'weight_key' => 'hot_potato_book', 'quantity' => $hotBooks];
        }

        if ($fumingBooks > 0) {
            $components[] = ['name' => 'Fuming Potato Book', 'weight_key' => 'fuming_potato_book', 'quantity' => $fumingBooks];
        }

        $enchantments = is_array($extra['enchantments'] ?? null) ? $extra['enchantments'] : [];
        foreach ($enchantments as $ench => $level) {
            $enchantName = Str::of((string) $ench)->replace('_', ' ')->title()->toString();
            $levelInt = (int) $level;
            if ($levelInt <= 0) {
                continue;
            }

            $isUltimate = Str::startsWith((string) $ench, config('bin_sniper.ultimate_enchant_prefixes', ['ultimate_']));
            $isHighTier = $levelInt >= 6;

            if (! $isUltimate && ! $isHighTier) {
                continue;
            }

            $components[] = [
                'name' => $enchantName . ' ' . $this->toRoman($levelInt),
                'weight_key' => $isUltimate ? 'ultimate_enchants' : 't6_t7_enchants',
                'quantity' => 1,
            ];
        }

        $stars = max((int) ($extra['upgrade_level'] ?? 0), (int) ($extra['dungeon_item_level'] ?? 0));
        if ($stars > 5) {
            $components[] = [
                'name' => 'Master Star',
                'weight_key' => 'master_stars',
                'quantity' => $stars - 5,
            ];
        }

        $gems = is_array($extra['gems'] ?? null) ? $extra['gems'] : [];
        $flawless = 0;
        $perfect = 0;
        array_walk_recursive($gems, static function ($value) use (&$flawless, &$perfect): void {
            $text = strtoupper((string) $value);
            if (str_contains($text, 'FLAWLESS')) {
                $flawless++;
            }
            if (str_contains($text, 'PERFECT')) {
                $perfect++;
            }
        });

        if ($flawless > 0) {
            $components[] = ['name' => 'Flawless Gemstone', 'weight_key' => 'gemstones_flawless', 'quantity' => $flawless];
        }
        if ($perfect > 0) {
            $components[] = ['name' => 'Perfect Gemstone', 'weight_key' => 'gemstones_perfect', 'quantity' => $perfect];
        }

        if (isset($extra['dye_item']) || isset($extra['dye']) || isset($extra['color'])) {
            $dyeName = (string) ($extra['dye_item'] ?? $extra['dye'] ?? 'Dye');
            $components[] = ['name' => Str::of($dyeName)->replace('_', ' ')->title()->toString(), 'weight_key' => 'dyes', 'quantity' => 1];
        }

        if ((int) ($extra['art_of_war_count'] ?? 0) > 0 || (bool) ($extra['art_of_war'] ?? false)) {
            $components[] = ['name' => 'The Art of War', 'weight_key' => 'art_of_war', 'quantity' => 1];
        }

        return $components;
    }

    /**
     * @param  array<int, array{name:string,weight_key:string,quantity:int}>  $components
     * @param  array<string, float|int|string>  $marketOverrides
     * @return array<string, float>
     */
    private function resolveComponentMarketPrices(array $components, array $marketOverrides): array
    {
        $prices = [];

        $componentIds = config('bin_sniper.component_market_ids', []);
        $neededIds = [];
        $neededNames = [];

        foreach ($components as $component) {
            $name = (string) $component['name'];
            $key = (string) $component['weight_key'];

            if (isset($marketOverrides[$name])) {
                $prices[$name] = (float) $marketOverrides[$name];
                continue;
            }

            $id = $componentIds[$key] ?? null;
            if (is_string($id) && $id !== '') {
                $neededIds[$name] = $id;
            } else {
                $neededNames[$name] = true;
            }
        }

        if ($neededIds !== []) {
            $bazaarRows = DB::table('bazaar_prices')
                ->whereIn('product_id', array_values($neededIds))
                ->get(['product_id', 'buy_price', 'sell_price']);

            $byProduct = [];
            foreach ($bazaarRows as $row) {
                $sell = (float) ($row->sell_price ?? 0);
                $buy = (float) ($row->buy_price ?? 0);
                $byProduct[(string) $row->product_id] = $sell > 0 ? $sell : $buy;
            }

            foreach ($neededIds as $name => $productId) {
                if (isset($byProduct[$productId])) {
                    $prices[$name] = (float) $byProduct[$productId];
                }
            }
        }

        if ($neededNames !== []) {
            $names = array_keys($neededNames);
            $rows = DB::table('bin_snapshots')
                ->select('item_name', DB::raw('MIN(price) as min_price'))
                ->where('recorded_at', '>=', now()->subHours(24))
                ->whereIn('item_name', $names)
                ->groupBy('item_name')
                ->get();

            foreach ($rows as $row) {
                $prices[(string) $row->item_name] = (float) ($row->min_price ?? 0);
            }
        }

        return $prices;
    }

    /**
     * @param  array<string, mixed>  $auction
     * @return array<string, mixed>
     */
    private function extractExtraAttributes(array $auction): array
    {
        $nbtBase64 = (string) ($auction['item_bytes'] ?? $auction['nbt_data'] ?? '');
        if ($nbtBase64 === '') {
            return [];
        }

        $parsed = NbtParser::parseBase64Gzip($nbtBase64);
        if (! is_array($parsed)) {
            return [];
        }

        $items = $parsed['i'] ?? null;
        if (! is_array($items) || ! isset($items[0]) || ! is_array($items[0])) {
            return [];
        }

        return (array) (($items[0]['tag']['ExtraAttributes'] ?? []));
    }

    private function calculateTax(float $price): float
    {
        $brackets = config('bin_sniper.tax_brackets', []);
        foreach ($brackets as $row) {
            $max = $row['max_price'] ?? null;
            $rate = (float) ($row['rate'] ?? 0.0);
            if ($max === null || $price <= (float) $max) {
                return $price * $rate;
            }
        }

        return 0.0;
    }

    private function calculateConfidenceScore(float $profitPct, int $liquidity24h, bool $manipulated, int $minDailyVolume): float
    {
        if ($manipulated) {
            return 0.0;
        }

        $score = 55.0;
        $score += min(20.0, max(0.0, $profitPct));
        $score += min(25.0, $liquidity24h * 2.0);

        if ($liquidity24h < $minDailyVolume) {
            $score *= 0.5;
        }

        return max(0.0, min(100.0, $score));
    }

    private function toRoman(int $number): string
    {
        $map = [
            'M' => 1000,
            'CM' => 900,
            'D' => 500,
            'CD' => 400,
            'C' => 100,
            'XC' => 90,
            'L' => 50,
            'XL' => 40,
            'X' => 10,
            'IX' => 9,
            'V' => 5,
            'IV' => 4,
            'I' => 1,
        ];

        $n = max(1, $number);
        $result = '';

        foreach ($map as $roman => $value) {
            while ($n >= $value) {
                $result .= $roman;
                $n -= $value;
            }
        }

        return $result;
    }
}
