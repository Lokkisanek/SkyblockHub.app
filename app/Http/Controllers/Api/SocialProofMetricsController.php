<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SocialProofMetricsService;
use Illuminate\Http\JsonResponse;

class SocialProofMetricsController extends Controller
{
    public function __invoke(SocialProofMetricsService $metricsService): JsonResponse
    {
        return response()->json([
            'metrics' => $metricsService->getMetrics(),
        ]);
    }
}
