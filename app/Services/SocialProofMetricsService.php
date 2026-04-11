<?php

namespace App\Services;

use App\Models\BazaarHistory;
use App\Models\ProfileSearch;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SocialProofMetricsService
{
    /**
     * Return public social-proof metrics with a short cache window.
     *
     * @return array{registered_players:int,tracked_flips:int,profiles_loaded:int,updated_at:string}
     */
    public function getMetrics(): array
    {
        return Cache::remember('landing_social_proof_metrics_v1', now()->addMinutes(5), function (): array {
            try {
                return [
                    'registered_players' => User::query()->count(),
                    'tracked_flips' => BazaarHistory::query()->count(),
                    'profiles_loaded' => ProfileSearch::query()->count(),
                    'updated_at' => now()->toIso8601String(),
                ];
            } catch (\Throwable $e) {
                Log::warning('Failed to build social proof metrics.', [
                    'error' => $e->getMessage(),
                ]);

                return [
                    'registered_players' => 0,
                    'tracked_flips' => 0,
                    'profiles_loaded' => 0,
                    'updated_at' => now()->toIso8601String(),
                ];
            }
        });
    }
}
