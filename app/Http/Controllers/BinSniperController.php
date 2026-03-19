<?php

namespace App\Http\Controllers;

use App\Models\BinAlert;
use App\Models\BinSnapshot;
use App\Services\BinSniper\BinSniperValuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BinSniperController extends Controller
{
    public function __construct(private readonly BinSniperValuationService $valuationService)
    {
    }

    public function index(Request $request): Response|JsonResponse
    {
        $search = $request->input('search', '');
        $sort = $request->input('sort', 'detected_at');
        $direction = $request->input('direction', 'desc');
        $tier = $request->input('tier', '');
        $isFeed = $request->boolean('feed');
        $snipes = $this->buildSnipes($search, $tier, $sort, $direction, $isFeed);

        if ($isFeed) {
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
                'minimum_profit' => (float) config('bin_sniper.profit_thresholds.min_profit_coins', 500000),
                'minimum_percentage' => (float) config('bin_sniper.profit_thresholds.min_profit_percentage', 10.0),
                'ignore_manipulated' => true,
            ],
        ]);
    }

    private function buildSnipes(string $search, string $tier, string $sort, string $direction, bool $lightweight = false): array
    {
        $likeOperator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = BinSnapshot::query()
            ->where('recorded_at', '>=', $lightweight ? now()->subHours(8) : now()->subHours(24));

        if ($search !== '') {
            $query->where('item_name', $likeOperator, "%{$search}%");
        }

        if ($tier !== '') {
            $query->where('tier', $tier);
        }

        $rows = $query
            ->orderByDesc('recorded_at')
            ->when($lightweight, fn ($q) => $q->limit(5000))
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
            ->groupBy(fn (BinSnapshot $row) => $this->deriveBaseGroupKey($row))
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

                $priceSamples = $group
                    ->pluck('price')
                    ->map(static fn ($price) => (float) $price)
                    ->filter(static fn (float $price) => $price > 0)
                    ->values()
                    ->all();

                $liquidity24h = max(0, (int) $group
                    ->filter(fn (BinSnapshot $entry) => $entry->recorded_at !== null && $entry->recorded_at->gte(now()->subHours(24)))
                    ->count());

                $analysis = $this->valuationService->analyzeAuction([
                    'item_uuid' => (string) $lbinAuction->auction_uuid,
                    'auction_uuid' => (string) $lbinAuction->auction_uuid,
                    'item_name' => (string) $lbinAuction->item_name,
                    'tier' => (string) ($lbinAuction->tier ?? 'UNKNOWN'),
                    'lbin_price' => $lbin,
                    'slbin_price' => $slbin,
                    'liquidity_24h' => $liquidity24h,
                ], $priceSamples);

                $profitMargin = (float) ($analysis['analysis']['profit_metrics']['potential_profit_coins'] ?? 0);
                $profitPercentage = (float) ($analysis['analysis']['profit_metrics']['profit_percentage'] ?? 0);
                $avgPrice = (float) $group->avg(fn (BinSnapshot $snap) => (float) $snap->price);
                $itemLiquidity = $this->calculateItemLiquidity($group, $lbin);
                $isManipulated = (bool) ($analysis['analysis']['manipulated'] ?? false);
                $confidenceScore = (float) ($analysis['analysis']['confidence_score'] ?? 0);

                // Combined feed score: prioritize confidence and absolute profit, penalize manipulation.
                $score = ($confidenceScore * 10000) + max(0.0, $profitMargin) + ($itemLiquidity * 2500);
                if ($isManipulated) {
                    $score *= 0.2;
                }

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
                    'tax_amount' => (float) ($analysis['analysis']['profit_metrics']['estimated_tax'] ?? 0),
                    'profit_after_tax' => $profitMargin,
                    'flip_roi_percentage' => $profitPercentage,
                    'active_auctions' => $group->count(),
                    'detected_at' => optional($group->max('recorded_at'))?->toIso8601String(),
                    'ends_at' => optional($lbinAuction->ends_at)?->toIso8601String(),
                    'texture_path' => $this->guessTexturePath((string) ($lbinAuction->internal_name ?? ''), (string) $lbinAuction->item_name),
                    'analysis' => $analysis['analysis'] ?? null,
                ];
            })
            ->filter()
            ->filter(fn (array $entry) => $entry['profit_margin'] >= (float) config('bin_sniper.profit_thresholds.min_profit_coins', 500000))
            ->filter(fn (array $entry) => $entry['profit_percentage'] >= (float) config('bin_sniper.profit_thresholds.min_profit_percentage', 10.0))
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

    private function deriveBaseGroupKey(BinSnapshot $row): string
    {
        $internal = strtoupper(trim((string) ($row->internal_name ?? '')));
        if ($internal !== '') {
            $tokens = array_values(array_filter(explode('_', $internal)));
            if ($tokens !== []) {
                $isAbbreviation = count(array_filter($tokens, static fn (string $token) => strlen($token) === 1)) === count($tokens);

                if ($isAbbreviation && count($tokens) >= 3) {
                    return 'abbr:' . implode('_', array_slice($tokens, 1));
                }

                // Common full-name reforges as first token.
                $knownReforges = [
                    'FIERCE', 'SPICY', 'SHARP', 'GENTLE', 'EPIC', 'LEGENDARY', 'PRECISE', 'SPIRITUAL',
                    'WITHERED', 'FABLED', 'WARPED', 'HEATED', 'AUSPICIOUS', 'FORTUNATE', 'BLOOMING',
                    'COLOSSAL', 'SOFT', 'STRENGTHENED', 'REFINED', 'EXCELLENT', 'TOIL',
                ];

                if (count($tokens) >= 2 && in_array($tokens[0], $knownReforges, true)) {
                    return 'name:' . implode('_', array_slice($tokens, 1));
                }

                return 'name:' . implode('_', $tokens);
            }
        }

        $name = trim((string) $row->item_name);
        if ($name !== '') {
            $name = preg_replace('/[✪✫★☆⚚]+/u', ' ', $name) ?? $name;
            $name = preg_replace('/\s+/', ' ', $name) ?? $name;
            $name = trim($name);
            if ($name !== '') {
                $words = explode(' ', $name);
                if (count($words) > 2) {
                    $first = strtoupper($words[0]);
                    if (in_array($first, [
                        'FIERCE', 'SPICY', 'SHARP', 'GENTLE', 'EPIC', 'LEGENDARY', 'PRECISE', 'SPIRITUAL',
                        'WITHERED', 'FABLED', 'WARPED', 'HEATED', 'AUSPICIOUS', 'FORTUNATE', 'BLOOMING',
                        'COLOSSAL', 'SOFT', 'STRENGTHENED', 'REFINED', 'EXCELLENT', 'TOIL',
                    ], true)) {
                        array_shift($words);
                        $name = implode(' ', $words);
                    }
                }

                return 'text:' . Str::upper($name);
            }
        }

        return 'fallback:' . (string) $row->id;
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

        // Avoid invalid numeric-only texture filenames such as /img/textures/655.png.
        if ($token !== '' && preg_match('/^[0-9_]+$/', $token)) {
            return '/item/paper';
        }

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
