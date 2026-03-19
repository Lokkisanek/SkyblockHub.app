<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BinSniper\BinSniperValuationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BinSniperAnalysisController extends Controller
{
    public function __construct(private readonly BinSniperValuationService $valuationService)
    {
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auction' => ['required', 'array'],
            'auction.item_uuid' => ['nullable', 'string', 'max:64'],
            'auction.auction_uuid' => ['nullable', 'string', 'max:64'],
            'auction.item_name' => ['required', 'string', 'max:255'],
            'auction.tier' => ['nullable', 'string', 'max:32'],
            'auction.lbin_price' => ['nullable', 'numeric', 'min:0'],
            'auction.price' => ['nullable', 'numeric', 'min:0'],
            'auction.slbin_price' => ['nullable', 'numeric', 'min:0'],
            'auction.liquidity_24h' => ['nullable', 'integer', 'min:0'],
            'auction.item_bytes' => ['nullable', 'string'],
            'auction.nbt_data' => ['nullable', 'string'],
            'base_prices' => ['required', 'array', 'min:1'],
            'base_prices.*' => ['numeric', 'min:0'],
            'market_overrides' => ['sometimes', 'array'],
        ]);

        $result = $this->valuationService->analyzeAuction(
            $validated['auction'],
            $validated['base_prices'],
            $validated['market_overrides'] ?? []
        );

        return response()->json($result);
    }
}
