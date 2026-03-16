<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BazaarHistory;
use App\Models\BazaarProduct;
use App\Services\ArbitrageService;
use App\Services\BazaarMathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BazaarController extends Controller
{
    public function __construct(
        private readonly BazaarMathService $bazaarMathService,
        private readonly ArbitrageService $arbitrageService,
    ) {
    }

    public function live(): JsonResponse
    {
        $rows = BazaarProduct::query()
            ->join('bazaar_prices', 'bazaar_products.product_id', '=', 'bazaar_prices.product_id')
            ->select([
                'bazaar_products.product_id',
                'bazaar_products.name',
                'bazaar_products.category',
                'bazaar_products.npc_sell_price',
                'bazaar_prices.buy_price',
                'bazaar_prices.sell_price',
                'bazaar_prices.buy_volume',
                'bazaar_prices.sell_volume',
                'bazaar_prices.buy_orders',
                'bazaar_prices.sell_orders',
                'bazaar_prices.updated_at',
            ])
            ->orderBy('bazaar_products.product_id')
            ->get()
            ->map(function ($row) {
                $margin = $this->bazaarMathService->calculateMargin((float) $row->buy_price, (float) $row->sell_price);
                $velocity = $this->bazaarMathService->calculateVelocity((int) $row->sell_volume, (int) $row->buy_volume);

                return [
                    'product_id' => $row->product_id,
                    'name' => $row->name,
                    'category' => $row->category,
                    'npc_sell_price' => (float) $row->npc_sell_price,
                    'buy_price' => (float) $row->buy_price,
                    'sell_price' => (float) $row->sell_price,
                    'buy_volume' => (int) $row->buy_volume,
                    'sell_volume' => (int) $row->sell_volume,
                    'buy_orders' => (int) $row->buy_orders,
                    'sell_orders' => (int) $row->sell_orders,
                    'updated_at' => $row->updated_at,
                    'margin' => $margin,
                    'velocity' => $velocity,
                ];
            })
            ->values();

        return response()->json($rows);
    }

    public function recipeArbitrage(): JsonResponse
    {
        $rows = $this->arbitrageService
            ->topProfitableRecipes(50)
            ->values();

        return response()->json($rows);
    }

    public function history(Request $request, string $productId): JsonResponse
    {
        $timeframe = $request->query('timeframe', '24h');

        [$since, $bucket] = match ($timeframe) {
            '1h' => [Carbon::now()->subHour(), 'hour'],
            '7d' => [Carbon::now()->subDays(7), 'day'],
            default => [Carbon::now()->subDay(), 'hour'],
        };

        $rows = BazaarHistory::query()
            ->where('product_id', $productId)
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get(['sell_price', 'recorded_at']);

        $candles = $rows
            ->groupBy(function (BazaarHistory $row) use ($bucket) {
                return $bucket === 'day'
                    ? $row->recorded_at->format('Y-m-d')
                    : $row->recorded_at->format('Y-m-d H:00:00');
            })
            ->map(function ($group, $key) {
                $prices = $group->pluck('sell_price')->map(fn ($v) => (float) $v)->values();

                return [
                    'bucket' => $key,
                    'open' => $prices->first(),
                    'high' => $prices->max(),
                    'low' => $prices->min(),
                    'close' => $prices->last(),
                ];
            })
            ->values();

        return response()->json([
            'product_id' => $productId,
            'timeframe' => $timeframe,
            'candles' => $candles,
        ]);
    }
}
