<?php

namespace App\Http\Controllers;

use App\Models\BinAlert;
use App\Models\BinSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class BinSniperController extends Controller
{
    private const MINIMUM_PROFIT = 500000.0;
    private const MINIMUM_PERCENTAGE = 10.0;

    public function index(Request $request): Response|JsonResponse
    {
        $search = $request->input('search', '');
        $sort = $request->input('sort', 'detected_at');
        $direction = $request->input('direction', 'desc');
        $tier = $request->input('tier', '');
        $snipes = $this->buildSnipes($search, $tier, $sort, $direction);

        if ($request->boolean('feed')) {
            return response()->json([
                'snipes' => $snipes,
                'generated_at' => now()->toIso8601String(),
            ]);
        }

        // User alerts
        $alerts = [];
        if ($request->user()) {
            $alerts = BinAlert::where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->get();
        }

        return Inertia::render('BinSniper/Index', [
            'snipes'    => $snipes,
            'alerts'    => $alerts,
            'filters'   => [
                'search'    => $search,
                'sort'      => $sort,
                'direction' => $direction,
                'tier'      => $tier,
            ],
            'constraints' => [
                'minimum_profit' => self::MINIMUM_PROFIT,
                'minimum_percentage' => self::MINIMUM_PERCENTAGE,
                'ignore_manipulated' => true,
            ],
        ]);
    }

    private function buildSnipes(string $search, string $tier, string $sort, string $direction): array
    {
        $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = BinSnapshot::query()
            ->where('recorded_at', '>=', now()->subHours(24));

        if ($search !== '') {
            $query->where('item_name', $likeOperator, "%{$search}%");
        }

        if ($tier !== '') {
            $query->where('tier', $tier);
        }

        $rows = $query
            ->orderByDesc('recorded_at')
            ->get([
                'id',
                'item_name',
                'item_id',
                'internal_name',
                'item_key',
                'auction_uuid',
                'price',
                'tier',
                'ends_at',
                'recorded_at',
            ]);

        $snipes = $rows
            ->groupBy(function (BinSnapshot $row) {
                if ($row->item_key) {
                    return $row->item_key;
                }

                $fallbackKey = trim((string) ($row->item_id ?? '')) . '|' . trim((string) ($row->internal_name ?? ''));
                return $fallbackKey !== '|' ? $fallbackKey : (string) $row->item_name;
            })
            ->map(function (Collection $group) {
                $priceSorted = $group->sortBy('price')->values();
                if ($priceSorted->count() < 2) {
                    return null;
                }

                $lbinAuction = $priceSorted->get(0);
                $slbinAuction = $priceSorted->get(1);
                $lbin = (float) $lbinAuction->price;
                $slbin = (float) $slbinAuction->price;

                if ($lbin <= 0 || $slbin <= $lbin) {
                    return null;
                }

                $profitMargin = $slbin - $lbin - ($slbin * 0.01);
                $profitPercentage = $lbin > 0 ? ($profitMargin / $lbin) * 100 : 0;
                $avgPrice = (float) $group->avg(fn (BinSnapshot $snap) => (float) $snap->price);
                $isManipulated = $avgPrice > 0 && $slbin > ($avgPrice * 2);

                $itemLiquidity = $this->calculateItemLiquidity($group, $lbin);
                $score = ($profitMargin * 0.6) + (($itemLiquidity * 1_000_000) * 0.4);

                $marketStability = $avgPrice > 0
                    ? max(0.0, min(100.0, 100 - (abs($lbin - $avgPrice) / $avgPrice) * 100))
                    : 40.0;
                $depthScore = min(100.0, $group->count() * 8);
                $profitSignal = min(100.0, max(0.0, $profitPercentage * 1.5));
                $confidenceScore = round(
                    ($itemLiquidity * 0.45)
                    + ($marketStability * 0.30)
                    + ($depthScore * 0.15)
                    + ($profitSignal * 0.10),
                    1,
                );

                return [
                    'item_name' => (string) $lbinAuction->item_name,
                    'item_id' => (string) ($lbinAuction->item_id ?? ''),
                    'internal_name' => (string) ($lbinAuction->internal_name ?? ''),
                    'tier' => $lbinAuction->tier,
                    'auction_uuid' => (string) $lbinAuction->auction_uuid,
                    'viewauction_command' => '/viewauction ' . $lbinAuction->auction_uuid,
                    'lbin' => $lbin,
                    'slbin' => $slbin,
                    'avg_price_24h' => $avgPrice,
                    'profit_margin' => $profitMargin,
                    'profit_percentage' => $profitPercentage,
                    'item_liquidity' => round($itemLiquidity, 1),
                    'score' => round($score, 1),
                    'confidence_score' => max(0.0, min(100.0, $confidenceScore)),
                    'is_manipulated' => $isManipulated,
                    'tax_amount' => $slbin * 0.01,
                    'profit_after_tax' => $profitMargin,
                    'flip_roi_percentage' => $lbin > 0 ? ($profitMargin / $lbin) * 100 : 0,
                    'active_auctions' => $group->count(),
                    'detected_at' => optional($group->max('recorded_at'))?->toIso8601String(),
                    'ends_at' => optional($lbinAuction->ends_at)?->toIso8601String(),
                    'texture_path' => $this->guessTexturePath((string) ($lbinAuction->internal_name ?? ''), (string) $lbinAuction->item_name),
                ];
            })
            ->filter()
            ->filter(fn (array $entry) => $entry['profit_margin'] >= self::MINIMUM_PROFIT)
            ->filter(fn (array $entry) => $entry['profit_percentage'] >= self::MINIMUM_PERCENTAGE)
            ->filter(fn (array $entry) => $entry['is_manipulated'] === false)
            ->values();

        $sortableMap = [
            'item_name' => 'item_name',
            'price' => 'lbin',
            'profit' => 'profit_margin',
            'score' => 'score',
            'confidence' => 'confidence_score',
            'detected_at' => 'detected_at',
        ];

        $sortField = $sortableMap[$sort] ?? 'detected_at';
        $descending = $direction === 'desc';

        return $snipes
            ->sortBy($sortField, SORT_REGULAR, $descending)
            ->take(100)
            ->values()
            ->all();
    }

    private function calculateItemLiquidity(Collection $group, float $lbin): float
    {
        $activeAuctions = $group
            ->filter(fn (BinSnapshot $entry) => $entry->ends_at !== null && $entry->ends_at->isFuture())
            ->count();
        $closeRange = $group
            ->filter(fn (BinSnapshot $entry) => (float) $entry->price <= ($lbin * 1.10))
            ->count();
        $recentListings = $group
            ->filter(fn (BinSnapshot $entry) => $entry->recorded_at !== null && $entry->recorded_at->gte(now()->subMinutes(30)))
            ->count();

        $score = ($activeAuctions * 8.0) + ($closeRange * 4.0) + ($recentListings * 6.0);

        if ($activeAuctions <= 1) {
            $score *= 0.35;
        }

        return max(0.0, min(100.0, $score));
    }

    private function guessTexturePath(string $internalName, string $itemName): string
    {
        $seed = $internalName !== '' ? $internalName : $itemName;
        $token = strtolower(preg_replace('/[^a-z0-9]+/', '_', $seed) ?? '');
        $token = trim($token, '_');

        return '/item/' . ($token !== '' ? $token : 'paper');
    }

    public function storeAlert(Request $request)
    {
        $validated = $request->validate([
            'item_name'       => 'required|string|max:255',
            'threshold_price' => 'required|numeric|min:1',
        ]);

        BinAlert::create([
            'user_id'         => $request->user()->id,
            'item_name'       => $validated['item_name'],
            'threshold_price' => $validated['threshold_price'],
        ]);

        return back()->with('success', 'Alert created.');
    }

    public function destroyAlert(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
        ]);

        BinAlert::where('id', $validated['id'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('success', 'Alert removed.');
    }

    public function toggleAlert(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer',
        ]);

        $alert = BinAlert::where('id', $validated['id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $alert->update(['is_active' => !$alert->is_active]);

        return back()->with('success', 'Alert updated.');
    }
}
