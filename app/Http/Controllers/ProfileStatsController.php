<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileStatsController extends Controller
{
    public function index(Request $request): Response
    {
        $username = $request->query('username');

        return Inertia::render('ProfileStats/Index', [
            'minecraftUsername' => $username ?: $request->user()?->minecraft_username,
        ]);
    }
}
