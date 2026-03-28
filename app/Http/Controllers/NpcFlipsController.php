<?php

namespace App\Http\Controllers;

use App\Jobs\FetchHypixelBazaarJob;
use App\Models\BazaarPrice;
use App\Services\NpcFlipService;
use App\Services\PlayerBazaarTaxService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NpcFlipsController extends Controller
{
    public function __construct(
        private readonly NpcFlipService $npcFlipService,
        private readonly PlayerBazaarTaxService $playerBazaarTaxService,
    ) {
    }

    public function index(Request $request): Response
    {
        if ($request->boolean('refresh')) {
            // Manual refresh pulls newest Bazaar snapshot from Hypixel.
            app(FetchHypixelBazaarJob::class)->handle();
        }

        $taxMeta = $this->playerBazaarTaxService->getTaxMetaForUser(Auth::user());
        $currentTaxRate = (float) ($taxMeta['rate'] ?? 0.01);

        $query = BazaarPrice::query()
            ->join('bazaar_products', 'bazaar_prices.product_id', '=', 'bazaar_products.product_id')
            ->select([
                'bazaar_prices.product_id',
                'bazaar_products.name',
                'bazaar_products.category',
                'bazaar_products.npc_sell_price',
                'bazaar_prices.buy_price',
                'bazaar_prices.sell_price',
                'bazaar_prices.sell_volume',
                'bazaar_prices.buy_volume',
                'bazaar_prices.sell_moving_week',
                'bazaar_prices.buy_moving_week',
                'bazaar_prices.sell_orders',
                'bazaar_prices.buy_orders',
            ])
            ->where('bazaar_prices.buy_price', '>', 0)
            ->where('bazaar_prices.sell_volume', '>', 0);

        $hasCompactor = $request->boolean('has_compactor');

        // Search filter
        if ($search = $request->input('search')) {
            $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('bazaar_products.name', $likeOperator, "%{$search}%")
                  ->orWhere('bazaar_prices.product_id', $likeOperator, "%{$search}%");
            });
        }

        $items = $query->get();

        // Calculate flip metrics for each item
        $flips = $items
            ->mapWithKeys(function ($item) use ($currentTaxRate, $hasCompactor) {
                // Skip items without NPC price data
                if (!$item->npc_sell_price || $item->npc_sell_price <= 0) {
                    return [];
                }

                $metrics = $this->npcFlipService->calculateFlipMetrics(
                    $item->product_id,
                    (float) $item->sell_price,
                    (float) $item->npc_sell_price,
                    $this->deriveVolume24h($item),
                    $currentTaxRate,
                    $hasCompactor,
                    $this->deriveSpreadPercent((float) $item->buy_price, (float) $item->sell_price),
                );

                return [
                    $item->product_id => [
                        'product_id' => $item->product_id,
                        'name' => $item->name,
                        'category' => $item->category,
                        // BZ Buy is instant buy from bazaar (quick_status.sellPrice).
                        'buy_price' => (float) $item->sell_price,
                        // Expose raw quick_status prices for debugging/transparency.
                        'bazaar_insta_sell_price' => (float) $item->buy_price,
                        'bazaar_insta_buy_price' => (float) $item->sell_price,
                        'sell_price' => (float) $item->sell_price,
                        'npc_sell_price' => (float) $item->npc_sell_price,
                        'sell_volume' => (float) $item->sell_volume,
                        'buy_volume' => (float) $item->buy_volume,
                        'sell_moving_week' => (float) ($item->sell_moving_week ?? 0),
                        'buy_moving_week' => (float) ($item->buy_moving_week ?? 0),
                        'sell_orders' => (int) $item->sell_orders,
                        'buy_orders' => (int) $item->buy_orders,
                        ...$metrics,
                        'profitability_label' => $this->npcFlipService->getProfitabilityLabel($metrics['coins_per_hour']),
                    ],
                ];
            })
            ->values();

        // Hard safety filters requested by user.
        $flips = $flips
            ->filter(fn ($flip) => ($flip['profit_per_item'] ?? 0) > 0)
            ->filter(fn ($flip) => ($flip['roi'] ?? 0) >= 1.1)
            ->values();

        // Sorting
        $sortBy = $request->input('sort', 'best_pick_score');
        $sortDir = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $flips = match ($sortBy) {
            'coins_per_hour' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['coins_per_hour'] <=> $b['coins_per_hour']
                : $b['coins_per_hour'] <=> $a['coins_per_hour']),
            'max_profit' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['max_profit'] <=> $b['max_profit']
                : $b['max_profit'] <=> $a['max_profit']),
            'npc_margin' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['npc_margin'] <=> $b['npc_margin']
                : $b['npc_margin'] <=> $a['npc_margin']),
            'hours_before_limited' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['hours_before_limited'] <=> $b['hours_before_limited']
                : $b['hours_before_limited'] <=> $a['hours_before_limited']),
            'best_pick_score' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['best_pick_score'] <=> $b['best_pick_score']
                : $b['best_pick_score'] <=> $a['best_pick_score']),
            'buy_price' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['buy_price'] <=> $b['buy_price']
                : $b['buy_price'] <=> $a['buy_price']),
            'npc_sell_price' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['npc_sell_price'] <=> $b['npc_sell_price']
                : $b['npc_sell_price'] <=> $a['npc_sell_price']),
            'profit_per_item' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['profit_per_item'] <=> $b['profit_per_item']
                : $b['profit_per_item'] <=> $a['profit_per_item']),
            'one_hour_instasells' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['one_hour_instasells'] <=> $b['one_hour_instasells']
                : $b['one_hour_instasells'] <=> $a['one_hour_instasells']),
            'profit_per_inventory' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['profit_per_inventory'] <=> $b['profit_per_inventory']
                : $b['profit_per_inventory'] <=> $a['profit_per_inventory']),
            'is_stackable' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? ((int) $a['is_stackable']) <=> ((int) $b['is_stackable'])
                : ((int) $b['is_stackable']) <=> ((int) $a['is_stackable'])),
            'time_to_fill_minutes' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['time_to_fill_minutes'] <=> $b['time_to_fill_minutes']
                : $b['time_to_fill_minutes'] <=> $a['time_to_fill_minutes']),
            'efficiency_score' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? $a['efficiency_score'] <=> $b['efficiency_score']
                : $b['efficiency_score'] <=> $a['efficiency_score']),
            'name' => $flips->sort(fn ($a, $b) => $sortDir === 'asc'
                ? strcmp($a['name'], $b['name'])
                : strcmp($b['name'], $a['name'])),
            default => $flips->sortByDesc('coins_per_hour'),
        };

        // Pagination
        $perPage = (int) $request->input('per_page', 50);
        $page = (int) $request->input('page', 1);
        $total = $flips->count();
        $flipsForPage = $flips->slice(($page - 1) * $perPage, $perPage)->values();

        // Best picks analysis (only from profitable flips)
        $profitableFlips = $flips->filter(fn ($flip) => $flip['profit_per_item'] > 0)->toArray();
        $bestPicks = $this->npcFlipService->rankBestPicks($profitableFlips);
        $overallBestPick = $this->npcFlipService->findBestPick($profitableFlips);

        return Inertia::render('NpcFlips/Index', [
            'flips' => $flipsForPage,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
            'filters' => [
                'search' => $request->input('search', ''),
                'sort' => $sortBy,
                'dir' => $sortDir,
            ],
            'best_picks' => $bestPicks,
            'overall_best_pick' => $overallBestPick,
            'tax_meta' => $taxMeta,
            'has_compactor' => $hasCompactor,
        ]);
    }

    private function deriveVolume24h(object $item): float
    {
        $sellMovingWeek = (float) ($item->sell_moving_week ?? 0);
        $buyMovingWeek = (float) ($item->buy_moving_week ?? 0);

        // For instant buying from Bazaar we consume sell side liquidity.
        // movingWeek is 7-day traded amount, so 24h estimate = movingWeek / 7.
        if ($sellMovingWeek > 0) {
            return $sellMovingWeek / 7;
        }

        if ($buyMovingWeek > 0) {
            return $buyMovingWeek / 7;
        }

        // Fallback for legacy snapshots without movingWeek fields.
        return max((float) ($item->sell_volume ?? 0), 0);
    }

    private function deriveSpreadPercent(float $instaSellPrice, float $instaBuyPrice): float
    {
        if ($instaBuyPrice <= 0) {
            return 100;
        }

        return abs($instaBuyPrice - $instaSellPrice) / $instaBuyPrice * 100;
    }
}

