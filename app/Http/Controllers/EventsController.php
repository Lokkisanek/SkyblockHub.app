<?php

namespace App\Http\Controllers;

use App\Services\MayorService;
use App\Services\PerkService;
use Inertia\Inertia;
use Inertia\Response;

class EventsController extends Controller
{
    public function __construct(
        private readonly MayorService $mayorService,
        private readonly PerkService $perkService,
    ) {
    }

    public function index(): Response
    {
        $mayor = $this->mayorService->getCurrentMayorData();

        return Inertia::render('EventTimer/Index', [
            'mayor' => $mayor,
            'perkState' => $this->perkService->buildState($mayor),
            'electionTimeline' => $mayor['election'] ?? null,
        ]);
    }
}
