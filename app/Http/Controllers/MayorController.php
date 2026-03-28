<?php

namespace App\Http\Controllers;

use App\Services\MayorService;
use Inertia\Inertia;
use Inertia\Response;

class MayorController extends Controller
{
    public function __construct(
        private readonly MayorService $mayorService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Mayors/Index', [
            'mayors' => $this->mayorService->getAllMayors(),
            'currentMayorName' => $this->mayorService->getCurrentMayorData()['name'] ?? null,
        ]);
    }
}
