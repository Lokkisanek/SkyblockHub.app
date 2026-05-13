<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\LeaderboardController;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Leaderboards/Index', [
            'sortColumns' => LeaderboardController::sortColumnsForClient(),
        ]);
    }
}
