<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LeaderboardsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Leaderboards/Index');
    }
}
