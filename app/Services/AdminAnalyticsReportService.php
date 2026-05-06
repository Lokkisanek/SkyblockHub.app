<?php

namespace App\Services;

use App\Models\FunnelEvent;
use Carbon\Carbon;

class AdminAnalyticsReportService
{
    private const FUNNEL_STEPS = [
        'landing_cta_click',
        'billing_view',
        'checkout_start',
        'checkout_success',
    ];

    private const SOURCE_TRACKED_EVENTS = [
        'landing_cta_click',
        'billing_view',
        'trial_start',
        'checkout_start',
        'checkout_success',
        'subscription_cancel',
    ];

    private const TRACKED_EVENTS = [
        'onboarding_view',
        'onboarding_step_complete',
        'onboarding_dismiss',
        ...self::SOURCE_TRACKED_EVENTS,
    ];

    public function buildReport(int $days = 30): array
    {
        if (! in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        $from = now()->startOfDay()->subDays($days - 1);
        $to = now()->endOfDay();

        $events = FunnelEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->get(['event_name', 'user_id', 'session_id', 'path', 'referrer', 'properties', 'occurred_at']);

        $counts = [];
        foreach (self::TRACKED_EVENTS as $eventName) {
            $counts[$eventName] = 0;
        }

        $ctaCounts = [];
        $sourceCounts = [];
        $funnelBySource = [];
        $dailyBuckets = $this->makeDailyBuckets($days);
        $seriesByEvent = [];

        foreach (self::TRACKED_EVENTS as $eventName) {
            $seriesByEvent[$eventName] = array_fill(0, count($dailyBuckets), 0);
        }

        $uniqueUsers = [];
        $uniqueSessions = [];

        foreach ($events as $event) {
            $eventName = (string) $event->event_name;
            if (! array_key_exists($eventName, $counts)) {
                continue;
            }

            $counts[$eventName]++;

            if ($event->user_id) {
                $uniqueUsers[(int) $event->user_id] = true;
            }

            if ($event->session_id) {
                $uniqueSessions[(string) $event->session_id] = true;
            }

            $dayKey = Carbon::parse($event->occurred_at)->format('Y-m-d');
            $bucketIndex = array_search($dayKey, $dailyBuckets, true);
            if ($bucketIndex !== false) {
                $seriesByEvent[$eventName][$bucketIndex]++;
            }

            if (in_array($eventName, self::SOURCE_TRACKED_EVENTS, true)) {
                $properties = is_array($event->properties) ? $event->properties : [];
                $source = $this->normalizeSource($properties, $event->referrer);
                $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;

                if (in_array($eventName, self::FUNNEL_STEPS, true)) {
                    if (! isset($funnelBySource[$source])) {
                        $funnelBySource[$source] = [];
                    }

                    $funnelBySource[$source][$eventName] = ($funnelBySource[$source][$eventName] ?? 0) + 1;
                }
            }

            if ($eventName === 'landing_cta_click') {
                $cta = trim((string) ($properties['cta'] ?? 'unknown'));
                if ($cta === '') {
                    $cta = 'unknown';
                }

                $ctaCounts[$cta] = ($ctaCounts[$cta] ?? 0) + 1;
            }
        }

        arsort($ctaCounts);
        arsort($sourceCounts);

        $funnel = $this->buildFunnel($counts);
        $sourceSegments = $this->buildSourceSegments($sourceCounts, $funnelBySource);
        $weeklyReview = $this->buildWeeklyReview();
        $conversionAlerts = $this->buildConversionAlerts($weeklyReview);
        $onboardingExperimentVariants = $this->buildOnboardingExperiment($from, $to);
        $experimentVariants = $this->buildUpgradePromptExperiment($from, $to);
        $owner = (string) config('ops.owners.growth', 'growth');
        $onboardingWinner = collect($onboardingExperimentVariants)
            ->filter(fn (array $row): bool => isset($row['completion_rate_pct']))
            ->sortByDesc('completion_rate_pct')
            ->first();
        $upgradeWinner = collect($experimentVariants)
            ->filter(fn (array $row): bool => isset($row['cta_rate_pct']))
            ->sortByDesc('cta_rate_pct')
            ->first();

        $totalEvents = array_sum($counts);
        $checkoutSuccessRate = $counts['checkout_start'] > 0
            ? round(($counts['checkout_success'] / $counts['checkout_start']) * 100, 1)
            : 0.0;

        $aiSummary = [
            'period_days' => $days,
            'owner' => $owner,
            'kpis' => [
                'total_events' => $totalEvents,
                'unique_users' => count($uniqueUsers),
                'unique_sessions' => count($uniqueSessions),
                'checkout_success_rate_pct' => $checkoutSuccessRate,
            ],
            'funnel' => $funnel,
            'top_cta' => array_slice($ctaCounts, 0, 5, true),
            'top_sources' => array_slice($sourceCounts, 0, 5, true),
            'weekly_review' => $weeklyReview,
            'conversion_alerts' => $conversionAlerts,
            'plain_english' => [
                sprintf('Tracked %d funnel events in the last %d days.', $totalEvents, $days),
                sprintf('Checkout success from checkout start is %s%%.', number_format($checkoutSuccessRate, 1)),
                sprintf('Most clicked landing CTA: %s.', array_key_first($ctaCounts) ?? 'n/a'),
                sprintf(
                    'Onboarding winner: variant %s at %s%% completion.',
                    strtoupper((string) ($onboardingWinner['variant'] ?? '?')),
                    isset($onboardingWinner['completion_rate_pct']) ? number_format((float) $onboardingWinner['completion_rate_pct'], 1) : 'n/a',
                ),
                sprintf(
                    'Upgrade prompt winner: variant %s at %s%% CTA rate.',
                    strtoupper((string) ($upgradeWinner['variant'] ?? '?')),
                    isset($upgradeWinner['cta_rate_pct']) ? number_format((float) $upgradeWinner['cta_rate_pct'], 1) : 'n/a',
                ),
            ],
        ];

        return [
            'rangeDays' => $days,
            'owner' => $owner,
            'kpis' => [
                'totalEvents' => $totalEvents,
                'uniqueUsers' => count($uniqueUsers),
                'uniqueSessions' => count($uniqueSessions),
                'checkoutSuccessRatePct' => $checkoutSuccessRate,
                'trialStarts' => $counts['trial_start'],
                'cancellations' => $counts['subscription_cancel'],
            ],
            'eventCounts' => $counts,
            'funnel' => $funnel,
            'dailyLabels' => $dailyBuckets,
            'dailySeries' => $seriesByEvent,
            'topCtas' => array_slice($ctaCounts, 0, 8, true),
            'topSources' => array_slice($sourceCounts, 0, 8, true),
            'sourceSegments' => $sourceSegments,
            'weeklyReview' => array_merge($weeklyReview, ['owner' => $owner]),
            'conversionAlerts' => $conversionAlerts,
            'onboardingExperimentVariants' => $onboardingExperimentVariants,
            'experimentVariants' => $experimentVariants,
            'aiSummary' => $aiSummary,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function makeDailyBuckets(int $days): array
    {
        $labels = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = now()->startOfDay()->subDays($i)->format('Y-m-d');
        }

        return $labels;
    }

    /**
     * @param array<string, int> $counts
     * @return array<int, array<string, int|float|string|null>>
     */
    private function buildFunnel(array $counts): array
    {
        $result = [];
        $prevCount = null;

        foreach (self::FUNNEL_STEPS as $step) {
            $current = (int) ($counts[$step] ?? 0);

            $conversion = null;
            $dropOff = null;

            if ($prevCount !== null && $prevCount > 0) {
                $conversion = round(($current / $prevCount) * 100, 1);
                $dropOff = round(100 - $conversion, 1);
            }

            $result[] = [
                'step' => $step,
                'count' => $current,
                'conversion_from_prev_pct' => $conversion,
                'dropoff_from_prev_pct' => $dropOff,
            ];

            $prevCount = $current;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function normalizeSource(array $properties, ?string $referrer): string
    {
        $source = $properties['source'] ?? null;
        if (is_string($source) && trim($source) !== '') {
            return $this->normalizeSourceLabel($source);
        }

        $utmSource = $properties['utm_source'] ?? null;
        if (is_string($utmSource) && trim($utmSource) !== '') {
            return $this->normalizeSourceLabel($utmSource);
        }

        if (! $referrer) {
            return 'direct';
        }

        $host = strtolower((string) (parse_url($referrer, PHP_URL_HOST) ?? ''));
        if ($host === '') {
            return 'direct';
        }

        $map = [
            'discord.com' => 'discord',
            'discord.gg' => 'discord',
            't.co' => 'twitter',
            'twitter.com' => 'twitter',
            'x.com' => 'twitter',
            'reddit.com' => 'reddit',
            'youtube.com' => 'youtube',
            'youtu.be' => 'youtube',
            'google.' => 'google',
            'bing.com' => 'bing',
            'hypixel.net' => 'hypixel',
        ];

        foreach ($map as $needle => $label) {
            if (str_contains($host, $needle)) {
                return $label;
            }
        }

        return $this->normalizeSourceLabel($host);
    }

    private function normalizeSourceLabel(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'unknown';
    }

    /**
     * @param array<string, int> $sourceCounts
     * @param array<string, array<string, int>> $funnelBySource
     * @return array<int, array<string, int|float|string>>
     */
    private function buildSourceSegments(array $sourceCounts, array $funnelBySource): array
    {
        $segments = [];
        $topSources = array_slice($sourceCounts, 0, 6, true);

        foreach ($topSources as $source => $total) {
            $stepCounts = [];
            foreach (self::FUNNEL_STEPS as $step) {
                $stepCounts[$step] = (int) ($funnelBySource[$source][$step] ?? 0);
            }

            $checkoutRate = $stepCounts['checkout_start'] > 0
                ? round(($stepCounts['checkout_success'] / $stepCounts['checkout_start']) * 100, 1)
                : 0.0;

            $segments[] = [
                'source' => $source,
                'total' => (int) $total,
                'landing_cta_click' => $stepCounts['landing_cta_click'],
                'billing_view' => $stepCounts['billing_view'],
                'checkout_start' => $stepCounts['checkout_start'],
                'checkout_success' => $stepCounts['checkout_success'],
                'checkout_success_rate_pct' => $checkoutRate,
            ];
        }

        return $segments;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWeeklyReview(): array
    {
        $currentFrom = now()->startOfDay()->subDays(6);
        $currentTo = now()->endOfDay();
        $previousFrom = now()->startOfDay()->subDays(13);
        $previousTo = now()->endOfDay()->subDays(7);

        $currentCounts = $this->countsForRange($currentFrom, $currentTo);
        $previousCounts = $this->countsForRange($previousFrom, $previousTo);

        $currentRate = $this->rate($currentCounts['checkout_success'], $currentCounts['checkout_start']);
        $previousRate = $this->rate($previousCounts['checkout_success'], $previousCounts['checkout_start']);

        $currentLandingToBilling = $this->rate($currentCounts['billing_view'], $currentCounts['landing_cta_click']);
        $previousLandingToBilling = $this->rate($previousCounts['billing_view'], $previousCounts['landing_cta_click']);

        $currentBillingToCheckout = $this->rate($currentCounts['checkout_start'], $currentCounts['billing_view']);
        $previousBillingToCheckout = $this->rate($previousCounts['checkout_start'], $previousCounts['billing_view']);

        return [
            'current' => [
                'from' => $currentFrom->toDateString(),
                'to' => $currentTo->toDateString(),
                'counts' => $currentCounts,
                'rates' => [
                    'landing_to_billing' => $currentLandingToBilling,
                    'billing_to_checkout' => $currentBillingToCheckout,
                    'checkout_success' => $currentRate,
                ],
            ],
            'previous' => [
                'from' => $previousFrom->toDateString(),
                'to' => $previousTo->toDateString(),
                'counts' => $previousCounts,
                'rates' => [
                    'landing_to_billing' => $previousLandingToBilling,
                    'billing_to_checkout' => $previousBillingToCheckout,
                    'checkout_success' => $previousRate,
                ],
            ],
            'delta' => [
                'checkout_success_rate_pp' => $this->deltaPoints($currentRate, $previousRate),
                'landing_to_billing_rate_pp' => $this->deltaPoints($currentLandingToBilling, $previousLandingToBilling),
                'billing_to_checkout_rate_pp' => $this->deltaPoints($currentBillingToCheckout, $previousBillingToCheckout),
                'trial_starts' => $currentCounts['trial_start'] - $previousCounts['trial_start'],
                'checkout_starts' => $currentCounts['checkout_start'] - $previousCounts['checkout_start'],
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildConversionAlerts(array $weeklyReview): array
    {
        $alerts = [];
        $thresholds = (array) config('ops.alerts', []);
        $owners = (array) config('ops.owners', []);

        $current = $weeklyReview['current']['rates'] ?? [];
        $previous = $weeklyReview['previous']['rates'] ?? [];
        $currentCounts = $weeklyReview['current']['counts'] ?? [];
        $previousCounts = $weeklyReview['previous']['counts'] ?? [];

        $comparisons = [
            [
                'key' => 'landing_to_billing',
                'label' => 'Landing -> Billing',
                'denominator' => 'landing_cta_click',
            ],
            [
                'key' => 'billing_to_checkout',
                'label' => 'Billing -> Checkout Start',
                'denominator' => 'billing_view',
            ],
            [
                'key' => 'checkout_success',
                'label' => 'Checkout Start -> Success',
                'denominator' => 'checkout_start',
            ],
        ];

        foreach ($comparisons as $comparison) {
            $key = $comparison['key'];
            $currentRate = $current[$key] ?? null;
            $previousRate = $previous[$key] ?? null;
            $previousBase = (int) ($previousCounts[$comparison['denominator']] ?? 0);

            $minimumPreviousSample = (int) ($thresholds['minimum_previous_sample'] ?? 20);

            if ($currentRate === null || $previousRate === null || $previousBase < $minimumPreviousSample) {
                continue;
            }

            $drop = $previousRate - $currentRate;
            $relativeDrop = $previousRate > 0 ? ($drop / $previousRate) * 100 : 0;
            $absoluteThreshold = (float) ($thresholds['conversion_drop_threshold_pp'] ?? 5.0);
            $relativeThreshold = (float) ($thresholds['conversion_relative_drop_threshold_pct'] ?? 25.0);

            if ($drop >= $absoluteThreshold || $relativeDrop >= $relativeThreshold) {
                $alerts[] = [
                    'title' => $comparison['label'],
                    'message' => sprintf('Dropped from %.1f%% to %.1f%% (-%.1fpp).', $previousRate, $currentRate, $drop),
                    'severity' => 'warning',
                    'owner' => $owners['growth'] ?? 'growth',
                    'thresholds' => [
                        'absolute_pp' => $absoluteThreshold,
                        'relative_pct' => $relativeThreshold,
                        'minimum_previous_sample' => $minimumPreviousSample,
                    ],
                ];
            }
        }

        if (empty($alerts)) {
            $alerts[] = [
                'title' => 'No conversion drops detected',
                'message' => 'Weekly conversion rates are stable within alert thresholds.',
                'severity' => 'ok',
                'owner' => $owners['ops'] ?? 'ops',
                'thresholds' => [
                    'absolute_pp' => (float) ($thresholds['conversion_drop_threshold_pp'] ?? 5.0),
                    'relative_pct' => (float) ($thresholds['conversion_relative_drop_threshold_pct'] ?? 25.0),
                    'minimum_previous_sample' => (int) ($thresholds['minimum_previous_sample'] ?? 20),
                ],
            ];
        }

        return $alerts;
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function buildUpgradePromptExperiment($from, $to): array
    {
        $events = FunnelEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->whereIn('event_name', [
                'upgrade_prompt_impression',
                'upgrade_prompt_cta',
                'upgrade_prompt_compare',
            ])
            ->get(['event_name', 'properties']);

        $variants = [
            'a' => [
                'impressions' => 0,
                'cta' => 0,
                'compare' => 0,
            ],
            'b' => [
                'impressions' => 0,
                'cta' => 0,
                'compare' => 0,
            ],
        ];

        foreach ($events as $event) {
            $properties = is_array($event->properties) ? $event->properties : [];
            $variant = $this->normalizeCopyVariant($properties['variant'] ?? null);
            if ($variant === null) {
                continue;
            }

            $bucket = match ($event->event_name) {
                'upgrade_prompt_impression' => 'impressions',
                'upgrade_prompt_cta' => 'cta',
                'upgrade_prompt_compare' => 'compare',
                default => null,
            };

            if ($bucket === null) {
                continue;
            }

            $variants[$variant][$bucket]++;
        }

        $rows = [];
        foreach ($variants as $variant => $stats) {
            $ctaRate = $this->rate($stats['cta'], $stats['impressions']);
            $compareRate = $this->rate($stats['compare'], $stats['impressions']);

            $rows[] = [
                'variant' => $variant,
                'impressions' => $stats['impressions'],
                'cta' => $stats['cta'],
                'compare' => $stats['compare'],
                'cta_rate_pct' => $ctaRate,
                'compare_rate_pct' => $compareRate,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function buildOnboardingExperiment($from, $to): array
    {
        $events = FunnelEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->whereIn('event_name', [
                'onboarding_view',
                'onboarding_step_complete',
                'onboarding_dismiss',
            ])
            ->get(['event_name', 'properties']);

        $variants = [
            'a' => [
                'views' => 0,
                'step_completions' => 0,
                'dismissals' => 0,
            ],
            'b' => [
                'views' => 0,
                'step_completions' => 0,
                'dismissals' => 0,
            ],
        ];

        foreach ($events as $event) {
            $properties = is_array($event->properties) ? $event->properties : [];
            $variant = $this->normalizeCopyVariant($properties['variant'] ?? null);
            if ($variant === null) {
                continue;
            }

            $bucket = match ($event->event_name) {
                'onboarding_view' => 'views',
                'onboarding_step_complete' => 'step_completions',
                'onboarding_dismiss' => 'dismissals',
                default => null,
            };

            if ($bucket === null) {
                continue;
            }

            $variants[$variant][$bucket]++;
        }

        $rows = [];
        foreach ($variants as $variant => $stats) {
            $completionRate = $this->rate($stats['step_completions'], $stats['views']);
            $dismissRate = $this->rate($stats['dismissals'], $stats['views']);

            $rows[] = [
                'variant' => $variant,
                'views' => $stats['views'],
                'step_completions' => $stats['step_completions'],
                'dismissals' => $stats['dismissals'],
                'completion_rate_pct' => $completionRate,
                'dismiss_rate_pct' => $dismissRate,
            ];
        }

        return $rows;
    }

    private function normalizeCopyVariant(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $variant = strtolower(trim($value));

        return in_array($variant, ['a', 'b'], true) ? $variant : null;
    }

    /**
     * @return array<string, int>
     */
    private function countsForRange($from, $to): array
    {
        $counts = [];
        foreach (self::TRACKED_EVENTS as $eventName) {
            $counts[$eventName] = 0;
        }

        $events = FunnelEvent::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->whereIn('event_name', self::TRACKED_EVENTS)
            ->get(['event_name']);

        foreach ($events as $event) {
            $eventName = (string) $event->event_name;
            $counts[$eventName] = ($counts[$eventName] ?? 0) + 1;
        }

        return $counts;
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 1);
    }

    private function deltaPoints(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return round($current - $previous, 1);
    }
}