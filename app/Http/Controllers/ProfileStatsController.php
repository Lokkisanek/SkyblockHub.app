<?php

namespace App\Http\Controllers;

use App\Models\ProfileSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ProfileStatsController extends Controller
{
    public function index(Request $request): Response
    {
        $username = trim((string) $request->query('username', ''));

        if ($username !== '') {
            ProfileSearch::query()->create([
                'username' => mb_substr($username, 0, 16),
                'user_id' => $request->user()?->id,
                'searched_at' => now(),
            ]);

            Cache::forget('landing_social_proof_metrics_v1');
        }

        return Inertia::render('ProfileStats/Index', [
            'minecraftUsername' => $username ?: $request->user()?->minecraft_username,
        ]);
    }
}
