<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NpcFlipService
{
    private const INVENTORY_STACK_SIZE = 64;
    private const INVENTORY_SLOTS = 35;

    // NPC inventory caps per item (typical values from SkyBlock)
    private const NPC_INVENTORY_CAPS = [
        'WHEAT' => 384000,
        'CARROT_ITEM' => 384000,
        'POTATO_ITEM' => 384000,
        'NETHER_STALK' => 384000,
        'MELON' => 384000,
        'PUMPKIN' => 384000,
        'SUGAR_CANE' => 384000,
        'CACTUS' => 384000,
        'INK_SACK' => 64000,
        'COCOA_BEANS' => 160000,
        'SEEDS' => 384000,
        'MUSHROOM_COLLECTION' => 320000,
        'BROWN_MUSHROOM' => 320000,
        'RED_MUSHROOM' => 320000,
        'DIRT' => 1600000,
        'SANDSTONE' => 1600000,
        'COAL' => 384000,
        'DIAMOND' => 40000,
        'IRON_INGOT' => 192000,
        'GOLD_INGOT' => 384000,
        'EMERALD' => 64000,
        'ENCHANTED_DIAMOND' => 10000,
        'ENCHANTED_GOLD' => 10000,
        'ENCHANTED_IRON_BLOCK' => 5000,
        'BLAZE_ROD' => 192000,
    ];

    // Personal compactor output overrides requested by product rules.
    // ratio_base_per_output represents how many base items are needed for one target output.
    private const COMPACTOR_TARGET_OVERRIDES = [
        'BLAZE_ROD' => ['target_id' => 'ENCHANTED_BLAZE_POWDER', 'ratio_base_per_output' => 25600.0],
        'CACTUS' => ['target_id' => 'ENCHANTED_CACTUS_GREEN', 'ratio_base_per_output' => 25600.0],
        'SUGAR_CANE' => ['target_id' => 'ENCHANTED_SUGAR', 'ratio_base_per_output' => 160.0],
    ];

    public function __construct(
        private readonly PerkService $perkService,
    ) {
    }

    /**
    * Calculate NPC flip profitability metrics.
     *
     * @param string $productId
     * @param float $buyPrice (Bazaar buy price)
     * @param float $npcSellPrice
     * @param float $sellVolume (24h sell volume)
     * @param float $taxRate Current bazaar tax rate (defaults to 1%)
     * @return array<string, float|bool>
     */
    public function calculateFlipMetrics(
        string $productId,
        float $buyPrice,
        float $npcSellPrice,
        float $volume24h,
        float $taxRate = 0.01,
        bool $hasPersonalCompactor = false,
        ?float $spreadPercent = null,
    ): array {
        $compactorMeta = $this->resolveCompactorOutcome($productId, $npcSellPrice, $hasPersonalCompactor);

        $effectiveNpcSellPrice = (float) $compactorMeta['effective_npc_sell_price'];
        $canCompact = (bool) $compactorMeta['can_compact'];
        $compactorRatio = (float) $compactorMeta['compactor_ratio'];
        $compactorTargetId = (string) $compactorMeta['compactor_target_id'];
        $unitValue = (float) $compactorMeta['unit_value'];

        $taxedBuyPrice = $buyPrice * (1 + max(0, $taxRate));

        // User-requested formula: Pure_Profit = NPC_Price - (BZ_Buy_Price * (1 + Tax)).
        $profitPerItem = $effectiveNpcSellPrice - $taxedBuyPrice;
        $rawMargin = $effectiveNpcSellPrice - $buyPrice;

        $roi = $taxedBuyPrice > 0 ? ($effectiveNpcSellPrice / $taxedBuyPrice) : 0;

        // One-hour instasells is derived from 24h volume from API moving week data.
        $volume24h = max($volume24h, 0);
        $oneHourInstasells = max($volume24h / 24, 0.1);

        // Coins per hour from NPC flipping (can be negative when margin is negative)
        $coinsPerHour = $oneHourInstasells * $profitPerItem;

        // Get NPC inventory cap (default to safe conservative value)
        $npcCap = $this->getNpcInventoryCap($productId);
        $isStackable = $this->isStackable($productId) || $canCompact;

        // Personal compactor effectively increases base units storable per inventory by compactor ratio.
        $inventoryMultiplier = $hasPersonalCompactor && $canCompact ? $compactorRatio : 1.0;
        $inventoryUnits = self::INVENTORY_STACK_SIZE * self::INVENTORY_SLOTS * $inventoryMultiplier;
        $profitPerInventory = $profitPerItem * $inventoryUnits;
        $timeToFillMinutes = $oneHourInstasells > 0
            ? ($inventoryUnits / $oneHourInstasells) * 60
            : 999999;

        // Hours before NPC limited (assuming linear consumption at current rate)
        // This is: NPC Cap / One-Hour Rate
        $hoursBeforeLimited = $volume24h > 0 ? $npcCap / $oneHourInstasells : 24;
        $hoursBeforeLimited = min($hoursBeforeLimited, 24); // Cap at 24 hours (realistic limit)
        $isNpcLimited = $hoursBeforeLimited <= 0;

        // Maximum total profit before running out
        $maxProfit = $coinsPerHour * $hoursBeforeLimited;

        $bestPickScore = $this->calculateBestPickScore(
            $profitPerItem,
            $unitValue,
            $roi,
            $volume24h,
            $hasPersonalCompactor && $canCompact,
            $spreadPercent,
        );

        // Keep this score for backwards compatibility with existing UI sort.
        $efficiencyScore = min(100, max(0, $bestPickScore / 2));

        $profitPercent = $taxedBuyPrice > 0
            ? ($profitPerItem / $taxedBuyPrice) * 100
            : 0;

        // Avoid false positives on very low-value items where ROI explodes from tiny denominators.
        $isUltimateFlip = $roi > 1.5
            && $volume24h > 1_000_000
            && $taxedBuyPrice >= 0.1
            && $profitPerItem >= 5
            && $coinsPerHour >= 500_000;

        return [
            'npc_margin' => $profitPerItem,
            'raw_margin' => $rawMargin,
            'profit_per_item' => $profitPerItem,
            'profit_percent' => $profitPercent,
            'roi' => $roi,
            'taxed_buy_price' => $taxedBuyPrice,
            'tax_rate' => $taxRate,
            'volume_24h' => $volume24h,
            'one_hour_instasells' => $oneHourInstasells,
            'coins_per_hour' => $coinsPerHour,
            'hours_before_limited' => $hoursBeforeLimited,
            'max_profit' => $maxProfit,
            'is_stackable' => $isStackable,
            'can_compact' => $canCompact,
            'compactor_ratio' => $compactorRatio,
            'compactor_target_id' => $compactorTargetId,
            'unit_value' => $unitValue,
            'profit_per_inventory' => $profitPerInventory,
            'time_to_fill_minutes' => $timeToFillMinutes,
            'best_pick_score' => $bestPickScore,
            'is_npc_limited' => $isNpcLimited,
            'is_ultimate_flip' => $isUltimateFlip,
            'efficiency_score' => $efficiencyScore,
        ];
    }

    /**
     * Get NPC inventory cap for a product.
     * Falls back to conservative estimate based on rarity.
     */
    private function getNpcInventoryCap(string $productId): float
    {
        if (isset(self::NPC_INVENTORY_CAPS[$productId])) {
            return self::NPC_INVENTORY_CAPS[$productId];
        }

        // Conservative default: 100k for unknown items
        return 100000;
    }

    private function calculateBestPickScore(
        float $profitPerUnit,
        float $unitPrice,
        float $roi,
        float $volume24h,
        bool $hasCompactorBoost,
        ?float $spreadPercent = null,
    ): float
    {
        // VOLUME-FIRST SCORING (2026 revision)
        // Volume is the PRIMARY bottleneck - can't make money if you can't sell fast enough
        // Formula: Base_Score * Volume_Multiplier
        
        // Base profitability score (kept for consistency)
        $profitDensity = $profitPerUnit * $unitPrice * 0.4;
        $roiComponent = $roi * 30;

        // Volume multiplier (exponential, not logarithmic)
        // - 100k/day  (4k/hr)  = 1.0x multiplier
        // - 500k/day  (21k/hr) = 2.5x multiplier  
        // - 1M/day    (42k/hr) = 5.0x multiplier
        // - 5M/day    (208k/hr)= 25x multiplier
        // This ensures high-volume items are HEAVILY preferred
        $volumeMultiplier = pow(max($volume24h / 100_000, 1), 0.6);
        $volumeComponent = $volumeMultiplier;

        $baseScore = $profitDensity + $roiComponent;
        $score = $baseScore * $volumeComponent;

        // AGGRESSIVE volume gate: Items under 500k/day get massive penalty
        if ($volume24h < 500_000) {
            $score *= 0.3; // Reduce by 70% for low volume
        }

        // Critical: One-hour instasells must be reasonable
        // Less than 100 items/hour is practically impossible to maintain
        $oneHourVolume = max($volume24h / 24, 0.1);
        if ($oneHourVolume < 100) {
            $score *= 0.05; // Kill items with <100/hour sells 
        }

        // Personal compactor bonus requested by user.
        if ($hasCompactorBoost) {
            $score += 50; // Increased from 25
        }

        // Price stability check (spread spike penalty).
        if ($spreadPercent !== null && $spreadPercent > 12) {
            $score *= 0.5; // Cut in half for unstable spreads
        }

        return max($score, 0); // Ensure non-negative
    }

    private function isStackable(string $productId): bool
    {
        return str_starts_with($productId, 'ENCHANTED_');
    }

    /**
     * Rank flips by multiple criteria to find BEST PICK.
     * Returns top items across different strategies.
     * 
     * CRITICAL: Filters heavily on volume24h to avoid dead flips
     * - Minimum 500k/day (20k+/hour) for strict best picks
     * - Minimum 200k/day (8k+/hour) for other rankings
     *
     * @param array $flips Array of flip data with calculated metrics
     * @return array{overall: array, strict_best_pick: array, hourly_profit: array, sustainability: array, consistency: array, stackable: array}
     */
    public function rankBestPicks(array $flips): array
    {
        // Safety filters from product requirements.
        // NOTE: Strict volume gate - minimum 200k/day (8.3k per hour)
        $safeFlips = collect($flips)
            ->filter(fn ($flip) => ($flip['profit_per_item'] ?? 0) > 0)
            ->filter(fn ($flip) => ($flip['roi'] ?? 0) >= 1.1)
            ->filter(fn ($flip) => ($flip['volume_24h'] ?? 0) >= 200_000) // Increased from 50k
            ->filter(fn ($flip) => !($flip['is_npc_limited'] ?? false));

        // Strict best picks: VERY high volume + good margin
        // Must have 500k+/day volume (20k+/hour min throughput)
        $bestPickCandidates = $safeFlips
            ->filter(fn ($flip) => ($flip['roi'] ?? 0) >= 1.5)
            ->filter(fn ($flip) => ($flip['volume_24h'] ?? 0) >= 500_000); // Increased from 50k

        // Sort by weighted best-pick score.
        $byBestPick = $safeFlips
            ->sortByDesc('best_pick_score')
            ->take(5)
            ->values()
            ->toArray();

        $byStrictBestPick = $bestPickCandidates
            ->sortByDesc('best_pick_score')
            ->take(5)
            ->values()
            ->toArray();

        // Sort by coins_per_hour descending (pure profit rate)
        $byHourly = $safeFlips
            ->sortByDesc('coins_per_hour')
            ->take(5)
            ->values()
            ->toArray();

        // Sort by hours_before_limited descending (longest lasting)
        $bySustainability = $safeFlips
            ->sortByDesc('hours_before_limited')
            ->take(5)
            ->values()
            ->toArray();

        // Consistency: prefer high steady volume with good margins
        $byConsistency = $safeFlips
            ->sortByDesc(fn ($f) => $f['coins_per_hour'] * sqrt(max($f['hours_before_limited'], 1)))
            ->take(5)
            ->values()
            ->toArray();

        $byStackable = $safeFlips
            ->where('is_stackable', true)
            ->sortByDesc('best_pick_score')
            ->take(5)
            ->values()
            ->toArray();

        return [
            'overall' => $byBestPick,
            'strict_best_pick' => $byStrictBestPick,
            'hourly_profit' => $byHourly,
            'sustainability' => $bySustainability,
            'consistency' => $byConsistency,
            'stackable' => $byStackable,
        ];
    }

    /**
     * Find the single BEST PICK using multi-factor analysis.
     *
     * Balances:
     * - Coins per hour (profitability)
     * - Sustainability (hours viable)
     * - Risk (volatility, depletion speed)
     * 
     * CRITICAL: Enforces minimum 500k/day volume (20k+/hour) to ensure item can actually be sold
     */
    public function findBestPick(array $flips): ?array
    {
        if (empty($flips)) {
            return null;
        }

        // Best pick follows weighted score with STRICT volume requirements
        // Must have 500k/day minimum to be considered (20k+/hour throughput)
        $scored = collect($flips)
            ->filter(fn ($flip) => ($flip['profit_per_item'] ?? 0) > 0)
            ->filter(fn ($flip) => ($flip['roi'] ?? 0) >= 1.5)
            ->filter(fn ($flip) => ($flip['volume_24h'] ?? 0) >= 500_000) // Increased from 50k
            ->filter(fn ($flip) => !($flip['is_npc_limited'] ?? false))
            ->map(fn ($flip) => [
                ...$flip,
                'composite_score' => $flip['best_pick_score'] ?? 0,
            ])
            ->sortByDesc('composite_score')
            ->first();

        return $scored;
    }

    /**
     * Get text description of profitability level.
     */
    public function getProfitabilityLabel(float $coinsPerHour): string
    {
        if ($coinsPerHour >= 10_000_000) {
            return 'LEGENDARY';
        }
        if ($coinsPerHour >= 5_000_000) {
            return 'EPIC';
        }
        if ($coinsPerHour >= 1_000_000) {
            return 'RARE';
        }
        if ($coinsPerHour >= 100_000) {
            return 'UNCOMMON';
        }

        return 'COMMON';
    }

    /**
     * Determine whether base item can be compacted to an enchanted form with better NPC value density.
     *
     * @return array{can_compact: bool, compactor_ratio: float, compactor_target_id: string, effective_npc_sell_price: float, unit_value: float}
     */
    private function resolveCompactorOutcome(string $productId, float $baseNpcSellPrice, bool $hasCompactor): array
    {
        $default = [
            'can_compact' => false,
            'compactor_ratio' => 1.0,
            'compactor_target_id' => '',
            'effective_npc_sell_price' => $baseNpcSellPrice,
            'unit_value' => $baseNpcSellPrice,
        ];

        if (! $hasCompactor) {
            return $default;
        }

        $map = Cache::remember('npc:compactor_map:v1', 600, function () {
            $recipes = DB::table('recipes')->get(['output_product_id', 'output_quantity', 'ingredients_json']);
            $outputNpcById = DB::table('bazaar_products')
                ->where('npc_sell_price', '>', 0)
                ->pluck('npc_sell_price', 'product_id');

            $byBase = [];

            foreach ($recipes as $recipe) {
                $ingredients = json_decode((string) $recipe->ingredients_json, true);
                if (! is_array($ingredients) || count($ingredients) !== 1) {
                    continue;
                }

                $ingredient = $ingredients[0] ?? null;
                if (! is_array($ingredient)) {
                    continue;
                }

                $baseId = (string) ($ingredient['item_id'] ?? '');
                $ingredientQty = (float) ($ingredient['quantity'] ?? 0);
                $outputQty = max((float) ($recipe->output_quantity ?? 1), 1);
                $targetId = (string) ($recipe->output_product_id ?? '');
                $targetNpc = (float) ($outputNpcById[$targetId] ?? 0);

                if ($baseId === '' || $targetId === '' || $ingredientQty <= 0 || $targetNpc <= 0) {
                    continue;
                }

                $ratioBasePerOutput = $ingredientQty / $outputQty;
                if ($ratioBasePerOutput <= 1) {
                    continue;
                }

                $equivalentNpcPerBase = $targetNpc / $ratioBasePerOutput;
                $existing = $byBase[$baseId] ?? null;

                if (! $existing || $equivalentNpcPerBase > $existing['equivalent_npc_per_base']) {
                    $byBase[$baseId] = [
                        'target_id' => $targetId,
                        'ratio_base_per_output' => $ratioBasePerOutput,
                        'target_npc_sell_price' => $targetNpc,
                        'equivalent_npc_per_base' => $equivalentNpcPerBase,
                    ];
                }
            }

            // Force specific compactor chains for better in-game behavior alignment.
            foreach (self::COMPACTOR_TARGET_OVERRIDES as $baseId => $override) {
                $targetId = (string) ($override['target_id'] ?? '');
                $ratioBasePerOutput = (float) ($override['ratio_base_per_output'] ?? 0);
                $targetNpc = (float) ($outputNpcById[$targetId] ?? 0);

                if ($targetId === '' || $ratioBasePerOutput <= 1 || $targetNpc <= 0) {
                    continue;
                }

                $byBase[$baseId] = [
                    'target_id' => $targetId,
                    'ratio_base_per_output' => $ratioBasePerOutput,
                    'target_npc_sell_price' => $targetNpc,
                    'equivalent_npc_per_base' => $targetNpc / $ratioBasePerOutput,
                ];
            }

            return $byBase;
        });

        $entry = $map[$productId] ?? null;
        if (! $entry) {
            return $default;
        }

        $effectiveNpc = max($baseNpcSellPrice, (float) $entry['equivalent_npc_per_base']);

        return [
            'can_compact' => true,
            'compactor_ratio' => (float) $entry['ratio_base_per_output'],
            'compactor_target_id' => (string) $entry['target_id'],
            'effective_npc_sell_price' => $effectiveNpc,
            // Value density priority should favor expensive compressed unit value.
            'unit_value' => (float) $entry['target_npc_sell_price'],
        ];
    }
}
