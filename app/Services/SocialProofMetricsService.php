<?php

namespace App\Services;

use App\Models\BazaarHistory;
use App\Models\ProfileSearch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SocialProofMetricsService
{
    /**
     * Return public social-proof metrics with a short cache window.
     *
     * @return array{active_online:int,tracked_flips:int,profiles_loaded:int,updated_at:string}
     */
    public function getMetrics(): array
    {
        return Cache::remember('landing_social_proof_metrics_v2', now()->addMinutes(5), function (): array {
            try {
                return [
                    'active_online' => $this->countActiveOnline(),
                    'tracked_flips' => BazaarHistory::query()->count(),
                    'profiles_loaded' => ProfileSearch::query()->count(),
                    'updated_at' => now()->toIso8601String(),
                ];
            } catch (\Throwable $e) {
                Log::warning('Failed to build social proof metrics.', [
                    'error' => $e->getMessage(),
                ]);

                return [
                    'active_online' => 0,
                    'tracked_flips' => 0,
                    'profiles_loaded' => 0,
                    'updated_at' => now()->toIso8601String(),
                ];
            }
        });
    }

    /**
     * Approximate concurrent visitors: unique sessions seen within the configured window.
     */
    private function countActiveOnline(): int
    {
        if (! Schema::hasTable('site_presence')) {
            return 0;
        }

        $minutes = max(1, (int) config('social_proof.active_presence_minutes', 5));
        $since = now()->subMinutes($minutes)->getTimestamp();
        $retentionSeconds = max(3600, (int) config('social_proof.presence_retention_hours', 48) * 3600);
        $purgeBefore = now()->getTimestamp() - $retentionSeconds;

        try {
            DB::table('site_presence')->where('last_seen', '<', $purgeBefore)->delete();

            return (int) DB::table('site_presence')->where('last_seen', '>=', $since)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
