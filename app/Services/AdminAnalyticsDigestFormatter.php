<?php

namespace App\Services;

class AdminAnalyticsDigestFormatter
{
    /**
     * @param array<string, mixed> $report
     */
    public function buildDiscordDigestMessage(array $report): string
    {
        $kpis = (array) ($report['kpis'] ?? []);
        $onboardingWinner = collect($report['onboardingExperimentVariants'] ?? [])
            ->filter(fn ($row) => isset($row['completion_rate_pct']))
            ->sortByDesc('completion_rate_pct')
            ->first();
        $lines = [
            '**SkyblockHub weekly review**',
            'Owner: ' . ($report['owner'] ?? 'growth'),
            sprintf('Onboarding events: %s', number_format((int) ($kpis['totalEvents'] ?? 0))),
            sprintf(
                'Onboarding completion: %s%%',
                isset($kpis['onboardingCompletionRatePct'])
                    ? number_format((float) $kpis['onboardingCompletionRatePct'], 1)
                    : 'n/a',
            ),
        ];

        if ($onboardingWinner) {
            $lines[] = sprintf(
                'Onboarding winner: variant %s (%s%% completion)',
                strtoupper((string) ($onboardingWinner['variant'] ?? '?')),
                isset($onboardingWinner['completion_rate_pct']) ? number_format((float) $onboardingWinner['completion_rate_pct'], 1) : 'n/a',
            );
        }

        return implode("\n", array_filter($lines));
    }
}