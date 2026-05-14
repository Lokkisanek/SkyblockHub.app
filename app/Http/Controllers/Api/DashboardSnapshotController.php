<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MayorService;
use App\Services\PerkService;
use Illuminate\Http\JsonResponse;

/**
 * Bundled read-only data for dashboard widgets (mayor, perks, election timeline).
 */
class DashboardSnapshotController extends Controller
{
    public function __construct(
        private readonly MayorService $mayorService,
        private readonly PerkService $perkService,
    ) {}

    public function __invoke(): JsonResponse
    {
        $mayor = $this->mayorService->getCurrentMayorData();
        $perkState = $this->perkService->buildState($mayor);

        return response()->json([
            'mayor' => $mayor,
            'perk_state' => $perkState,
            'election_timeline' => $mayor['election'] ?? null,
            'server_time' => time(),
        ]);
    }
}
