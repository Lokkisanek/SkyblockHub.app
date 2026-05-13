<?php

namespace App\Http\Controllers;

use App\Jobs\FetchHypixelBazaarJob;
use App\Models\BazaarPrice;
use App\Models\BazaarProduct;
use App\Services\PlayerBazaarTaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BazaarController extends Controller
{
    public function __construct(
        private readonly PlayerBazaarTaxService $playerBazaarTaxService,
    ) {}

    public function index(Request $request): Response
    {
        if ($request->boolean('refresh')) {
            app(FetchHypixelBazaarJob::class)->handle();
        }

        $flipTax = $this->playerBazaarTaxService->getBazaarFlipTaxForUser(Auth::user());
        $sellKeep = $this->sqlFloat($flipTax['sell_keep_multiplier'], 0.5, 1.0);
        $buyMult = $this->sqlFloat($flipTax['buy_cost_multiplier'], 1.0, 1.2);

        // Hypixel quick_status: buyPrice column = coins from instant SELL; sellPrice = coins paid on instant BUY.
        $marginSql = "((bazaar_prices.buy_price * {$sellKeep}) - (bazaar_prices.sell_price * {$buyMult}))";
        $entryCostSql = "(bazaar_prices.sell_price * {$buyMult})";
        $marginPercentSql = "(CASE WHEN {$entryCostSql} > 0 THEN (({$marginSql} / {$entryCostSql}) * 100) ELSE 0 END)";

        // Hourly throughput: moving_week / 168 (hours in a week)
        $hourlyInstabuysSql = '(bazaar_prices.sell_moving_week / 168.0)';
        $hourlyInstasellsSql = '(bazaar_prices.buy_moving_week / 168.0)';

        $minFunc = $this->getMinFunction($hourlyInstabuysSql, $hourlyInstasellsSql);
        $coinsPerHourSql = "({$marginSql} * {$minFunc})";

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

        $minDailyVolume = max((int) $request->input('min_daily_volume', 100), 0);
        $dailyInstabuysSql = '(bazaar_prices.sell_moving_week / 7.0)';
        $dailyInstasellsSql = '(bazaar_prices.buy_moving_week / 7.0)';

        $maxEntryCost = null;
        if ($request->filled('max_entry_cost')) {
            $maxEntryCost = max((float) $request->input('max_entry_cost'), 0);
        } elseif ($request->filled('max_buy_price')) {
            $maxEntryCost = max((float) $request->input('max_buy_price'), 0);
        }

        $minMargin = $request->filled('min_margin')
            ? (float) $request->input('min_margin')
            : null;
        $minMarginPercent = $request->filled('min_margin_percent')
            ? (float) $request->input('min_margin_percent')
            : null;

        $dailyMinFunc = $this->getMinFunction($dailyInstabuysSql, $dailyInstasellsSql);
        $query->whereRaw("{$dailyMinFunc} >= ?", [$minDailyVolume])
            ->whereRaw("{$marginSql} > 0");

        if ($maxEntryCost !== null) {
            $query->where('bazaar_prices.sell_price', '<=', $maxEntryCost);
        }
        if ($minMargin !== null) {
            $query->whereRaw("{$marginSql} >= ?", [$minMargin]);
        }
        if ($minMarginPercent !== null) {
            $query->whereRaw("{$marginPercentSql} >= ?", [$minMarginPercent]);
        }

        $search = $request->input('search');
        if ($search) {
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('bazaar_products.name', $likeOperator, "%{$search}%")
                    ->orWhere('bazaar_prices.product_id', $likeOperator, "%{$search}%");
            });
        }

        $category = $request->input('category');
        if ($category) {
            $query->where('bazaar_products.category', $category);
        }

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
            ->whereRaw("{$dailyMinFunc} >= 100");

        $bestCoinsPerHour = (clone $bestPicksBase)->orderByRaw("{$coinsPerHourSql} DESC")->first();
        $bestMargin = (clone $bestPicksBase)->orderByRaw("{$marginSql} DESC")->first();
        $bestThroughput = (clone $bestPicksBase)
            ->orderByRaw("{$minFunc} DESC")
            ->first();

        $topFlipsRows = (clone $bestPicksBase)
            ->orderByRaw("{$coinsPerHourSql} DESC")
            ->limit(3)
            ->get();

        $topFlips = $topFlipsRows->map(fn ($row) => [
            'product_id' => $row->product_id,
            'name' => $row->name,
            'coins_per_hour' => (float) ($row->coins_per_hour ?? 0),
            'margin' => (float) ($row->margin ?? 0),
            'hourly_instabuys' => (float) ($row->hourly_instabuys ?? 0),
            'hourly_instasells' => (float) ($row->hourly_instasells ?? 0),
        ])->values();

        $categories = BazaarProduct::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values()
            ->all();

        return Inertia::render('Bazaar/Index', [
            'items' => $items,
            'best_picks' => [
                'coins_per_hour' => $bestCoinsPerHour,
                'margin' => $bestMargin,
                'throughput' => $bestThroughput,
            ],
            'top_flips' => $topFlips,
            'flip_tax' => [
                'instant_buy_tax_rate' => $flipTax['instant_buy_tax_rate'],
                'instant_sell_tax_rate' => $flipTax['instant_sell_tax_rate'],
                'sell_keep_multiplier' => $flipTax['sell_keep_multiplier'],
                'buy_cost_multiplier' => $flipTax['buy_cost_multiplier'],
            ],
            'buy_tax_meta' => $flipTax['buy_tax_meta'],
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'category' => $category,
                'sort' => $sortBy,
                'dir' => $sortDir,
                'min_daily_volume' => $minDailyVolume,
                'max_entry_cost' => $maxEntryCost,
                'min_margin' => $minMargin,
                'min_margin_percent' => $minMarginPercent,
            ],
        ]);
    }

    private function sqlFloat(float $value, float $min, float $max): string
    {
        $v = max($min, min($max, $value));

        return sprintf('%.12F', $v);
    }

    private function getMinFunction(string $a, string $b): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return "CASE WHEN ({$a}) < ({$b}) THEN ({$a}) ELSE ({$b}) END";
        }

        return "LEAST({$a}, {$b})";
    }
}
