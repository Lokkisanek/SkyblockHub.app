<?php

namespace App\Http\Controllers;

use App\Models\BazaarPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BazaarController extends Controller
{
    public function index(Request $request): Response
    {
        $trueProfitSql = '((bazaar_prices.sell_price * 0.9875) - bazaar_prices.buy_price)';
        $marginPercentSql = "(CASE WHEN bazaar_prices.sell_price > 0 THEN (({$trueProfitSql} / bazaar_prices.sell_price) * 100) ELSE 0 END)";
        $profitScoreSql = "({$trueProfitSql} * bazaar_prices.sell_volume)";

        $query = BazaarPrice::query()
            ->join('bazaar_products', 'bazaar_prices.product_id', '=', 'bazaar_products.product_id')
            ->select([
                DB::raw('bazaar_prices.product_id as id'),
                'bazaar_prices.product_id',
                'bazaar_products.name',
                'bazaar_products.category',
                'bazaar_prices.sell_price',
                'bazaar_prices.buy_price',
                'bazaar_prices.sell_volume',
                'bazaar_prices.buy_volume',
                'bazaar_prices.sell_orders',
                'bazaar_prices.buy_orders',
                DB::raw('bazaar_prices.sell_volume as sell_moving_week'),
                DB::raw('bazaar_prices.buy_volume as buy_moving_week'),
                DB::raw("{$trueProfitSql} as true_profit"),
                DB::raw("{$marginPercentSql} as margin_percent"),
                DB::raw("{$profitScoreSql} as profit_score"),
            ]);
        $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $minDailyVolume = max((int) $request->input('min_daily_volume', 1000), 0);
        $maxBuyPrice = $request->filled('max_buy_price')
            ? max((float) $request->input('max_buy_price'), 0)
            : null;
        $minTrueProfit = $request->filled('min_true_profit')
            ? (float) $request->input('min_true_profit')
            : null;
        $minMarginPercent = $request->filled('min_margin_percent')
            ? (float) $request->input('min_margin_percent')
            : null;

        $query->where('bazaar_prices.sell_volume', '>=', $minDailyVolume)
              ->where('bazaar_prices.buy_volume', '>=', $minDailyVolume);
        if ($maxBuyPrice !== null) {
            $query->where('bazaar_prices.buy_price', '<=', $maxBuyPrice);
        }
        if ($minTrueProfit !== null) {
            $query->whereRaw("{$trueProfitSql} >= ?", [$minTrueProfit]);
        }
        if ($minMarginPercent !== null) {
            $query->whereRaw("{$marginPercentSql} >= ?", [$minMarginPercent]);
        }

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('name', $likeOperator, "%{$search}%")
                  ->orWhere('product_id', $likeOperator, "%{$search}%");
            });
        }

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Sorting
        $sortBy = $request->input('sort', 'profit_score');
        $sortDir = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowed = ['name', 'sell_price', 'buy_price', 'sell_volume', 'buy_volume', 'profit_score', 'true_profit', 'margin_percent'];
        if (! in_array($sortBy, $allowed, true)) {
            $sortBy = 'profit_score';
            $sortDir = 'desc';
        }

        if ($sortBy === 'profit_score') {
            $query->orderByRaw("{$profitScoreSql} {$sortDir}");
        } elseif ($sortBy === 'true_profit') {
            $query->orderByRaw("{$trueProfitSql} {$sortDir}");
        } elseif ($sortBy === 'margin_percent') {
            $query->orderByRaw("{$marginPercentSql} {$sortDir}");
        } else {
            $column = in_array($sortBy, ['name'], true) ? "bazaar_products.{$sortBy}" : "bazaar_prices.{$sortBy}";
            $query->orderBy($column, $sortDir);
        }

        $items = $query->paginate(50)->withQueryString();

        return Inertia::render('Bazaar/Index', [
            'items'   => $items,
            'filters' => [
                'search'   => $search,
                'category' => $category,
                'sort'     => $sortBy,
                'dir'      => $sortDir,
                'min_daily_volume' => $minDailyVolume,
                'max_buy_price' => $maxBuyPrice,
                'min_true_profit' => $minTrueProfit,
                'min_margin_percent' => $minMarginPercent,
            ],
        ]);
    }
}
