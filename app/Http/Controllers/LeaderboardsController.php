<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SubscriptionFeatureService;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardsController extends Controller
{
    public function __construct(
        private readonly SubscriptionFeatureService $subscriptionFeatureService,
    ) {
    }

    public function index(): Response
    {
        $rows = User::query()
            ->with('entitlement')
            ->orderByDesc('karma_score')
            ->limit(50)
            ->get()
            ->values()
            ->map(function (User $user, int $index) {
                $features = $this->subscriptionFeatureService->forUser($user);

                return [
                    'rank' => $index + 1,
                    'display_name' => $user->minecraft_username ?: ($user->discord_username ?: $user->name),
                    'karma_score' => (int) ($user->karma_score ?? 0),
                    'tier_tag' => $features['leaderboard_tag'],
                ];
            });

        return Inertia::render('Leaderboards/Index', [
            'rows' => $rows,
        ]);
    }
}
