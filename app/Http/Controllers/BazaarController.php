<?php

namespace App\Http\Controllers;

use App\Jobs\FetchHypixelBazaarJob;
use App\Models\BazaarPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BazaarController extends Controller
{
    public function index(Request $request): Response
    {
        if ($request->boolean('refresh')) {
            app(FetchHypixelBazaarJob::class)->handle();
        }
        // Margin per item: instabuy revenue (after 1.25% tax) minus instasell cost
        // buy_price = instabuy price (HIGH, what buyers pay)
        // sell_price = instasell price (LOW, what sellers receive)
        $marginSql = '((bazaar_prices.buy_price * 0.9875) - bazaar_prices.sell_price)';
        $marginPercentSql = "(CASE WHEN bazaar_prices.buy_price > 0 THEN (({$marginSql} / bazaar_prices.buy_price) * 100) ELSE 0 END)";

        // Hourly throughput: moving_week / 168 (hours in a week)
        $hourlyInstabuysSql = '(bazaar_prices.sell_moving_week / 168.0)';
        $hourlyInstasellsSql = '(bazaar_prices.buy_moving_week / 168.0)';

        // Coins/hour = margin × min(hourly instabuys, hourly instasells)
        $coinsPerHourSql = "({$marginSql} * MIN({$hourlyInstabuysSql}, {$hourlyInstasellsSql}))";

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
                'bazaar_prices.sell_moving_week',
                'bazaar_prices.buy_moving_week',
                DB::raw("{$marginSql} as margin"),
                DB::raw("{$marginPercentSql} as margin_percent"),
                DB::raw("{$hourlyInstabuysSql} as hourly_instabuys"),
                DB::raw("{$hourlyInstasellsSql} as hourly_instasells"),
                DB::raw("{$coinsPerHourSql} as coins_per_hour"),
            ]);
        $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        // Filter by minimum daily trading volume (based on actual transactions, not order book)
        $minDailyVolume = max((int) $request->input('min_daily_volume', 100), 0);
        $dailyInstabuysSql = '(bazaar_prices.sell_moving_week / 7.0)';
        $dailyInstasellsSql = '(bazaar_prices.buy_moving_week / 7.0)';

        $maxBuyPrice = $request->filled('max_buy_price')
            ? max((float) $request->input('max_buy_price'), 0)
            : null;
        $minMargin = $request->filled('min_margin')
            ? (float) $request->input('min_margin')
            : null;
        $minMarginPercent = $request->filled('min_margin_percent')
            ? (float) $request->input('min_margin_percent')
            : null;

        // Only show items with positive margin and sufficient daily trading volume
        $query->whereRaw("MIN({$dailyInstabuysSql}, {$dailyInstasellsSql}) >= ?", [$minDailyVolume])
              ->whereRaw("{$marginSql} > 0");

        if ($maxBuyPrice !== null) {
            $query->where('bazaar_prices.buy_price', '<=', $maxBuyPrice);
        }
        if ($minMargin !== null) {
            $query->whereRaw("{$marginSql} >= ?", [$minMargin]);
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

        // Sorting — default by coins_per_hour
        $sortBy = $request->input('sort', 'coins_per_hour');
        $sortDir = $request->input('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowed = ['name', 'sell_price', 'buy_price', 'coins_per_hour', 'margin', 'margin_percent', 'hourly_instabuys'];
        if (! in_array($sortBy, $allowed, true)) {
            $sortBy = 'coins_per_hour';
            $sortDir = 'desc';
        }

        $rawSorts = [
            'coins_per_hour' => $coinsPerHourSql,
            'margin' => $marginSql,
            'margin_percent' => $marginPercentSql,
            'hourly_instabuys' => $hourlyInstabuysSql,
        ];

        if (isset($rawSorts[$sortBy])) {
            $query->orderByRaw("{$rawSorts[$sortBy]} {$sortDir}");
        } else {
            $column = in_array($sortBy, ['name'], true) ? "bazaar_products.{$sortBy}" : "bazaar_prices.{$sortBy}";
            $query->orderBy($column, $sortDir);
        }

        $items = $query->paginate(50)->withQueryString();

        // Best picks — top items across different criteria (unfiltered except positive margin + min volume)
        $bestPicksBase = BazaarPrice::query()
            ->join('bazaar_products', 'bazaar_prices.product_id', '=', 'bazaar_products.product_id')
            ->select([
                'bazaar_prices.product_id',
                'bazaar_products.name',
                'bazaar_prices.sell_price',
                'bazaar_prices.buy_price',
                'bazaar_prices.sell_moving_week',
                'bazaar_prices.buy_moving_week',
                DB::raw("{$marginSql} as margin"),
                DB::raw("{$marginPercentSql} as margin_percent"),
                DB::raw("{$hourlyInstabuysSql} as hourly_instabuys"),
                DB::raw("{$hourlyInstasellsSql} as hourly_instasells"),
                DB::raw("{$coinsPerHourSql} as coins_per_hour"),
            ])
            ->whereRaw("{$marginSql} > 0")
            ->whereRaw("MIN({$dailyInstabuysSql}, {$dailyInstasellsSql}) >= 100");

        $bestCoinsPerHour = (clone $bestPicksBase)->orderByRaw("{$coinsPerHourSql} DESC")->first();
        $bestMargin = (clone $bestPicksBase)->orderByRaw("{$marginSql} DESC")->first();
        $bestThroughput = (clone $bestPicksBase)
            ->orderByRaw("MIN({$hourlyInstabuysSql}, {$hourlyInstasellsSql}) DESC")
            ->first();

        return Inertia::render('Bazaar/Index', [
            'items'   => $items,
            'best_picks' => [
                'coins_per_hour' => $bestCoinsPerHour,
                'margin' => $bestMargin,
                'throughput' => $bestThroughput,
            ],
            'filters' => [
                'search'   => $search,
                'category' => $category,
                'sort'     => $sortBy,
                'dir'      => $sortDir,
                'min_daily_volume' => $minDailyVolume,
                'max_buy_price' => $maxBuyPrice,
                'min_margin' => $minMargin,
                'min_margin_percent' => $minMarginPercent,
            ],
        ]);
    }
}
