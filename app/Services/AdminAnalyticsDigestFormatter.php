<?php

namespace App\Services;

class AdminAnalyticsDigestFormatter
{
    /**
     * @param array<string, mixed> $report
     */
    public function buildDiscordDigestMessage(array $report): string
    {
        $weekly = $report['weeklyReview']['current']['rates'] ?? [];
        $alerts = array_slice((array) ($report['conversionAlerts'] ?? []), 0, 3);
        $onboardingWinner = collect($report['onboardingExperimentVariants'] ?? [])
            ->filter(fn ($row) => isset($row['completion_rate_pct']))
            ->sortByDesc('completion_rate_pct')
            ->first();
        $upgradeWinner = collect($report['experimentVariants'] ?? [])
            ->filter(fn ($row) => isset($row['cta_rate_pct']))
            ->sortByDesc('cta_rate_pct')
            ->first();

        $lines = [
            '**SkyblockHub weekly review**',
            'Owner: ' . ($report['owner'] ?? 'growth'),
            sprintf('Landing -> Billing: %s%%', isset($weekly['landing_to_billing']) ? number_format((float) $weekly['landing_to_billing'], 1) : 'n/a'),
            sprintf('Billing -> Checkout: %s%%', isset($weekly['billing_to_checkout']) ? number_format((float) $weekly['billing_to_checkout'], 1) : 'n/a'),
            sprintf('Checkout Success: %s%%', isset($weekly['checkout_success']) ? number_format((float) $weekly['checkout_success'], 1) : 'n/a'),
        ];

        if ($onboardingWinner) {
            $lines[] = sprintf(
                'Onboarding winner: variant %s (%s%% completion)',
                strtoupper((string) ($onboardingWinner['variant'] ?? '?')),
                isset($onboardingWinner['completion_rate_pct']) ? number_format((float) $onboardingWinner['completion_rate_pct'], 1) : 'n/a',
            );
        }

        if ($upgradeWinner) {
            $lines[] = sprintf(
                'Upgrade prompt winner: variant %s (%s%% CTA rate)',
                strtoupper((string) ($upgradeWinner['variant'] ?? '?')),
                isset($upgradeWinner['cta_rate_pct']) ? number_format((float) $upgradeWinner['cta_rate_pct'], 1) : 'n/a',
            );
        }

        foreach ($alerts as $alert) {
            $lines[] = sprintf(
                'Alert: %s - %s',
                (string) ($alert['title'] ?? 'Alert'),
                (string) ($alert['message'] ?? ''),
            );
        }

        return implode("\n", array_filter($lines));
    }
}