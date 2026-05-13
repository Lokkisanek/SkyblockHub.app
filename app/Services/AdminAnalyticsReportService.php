<?php

namespace App\Services;

use App\Models\FunnelEvent;
use Carbon\Carbon;

class AdminAnalyticsReportService
{
    private const SOURCE_TRACKED_EVENTS = [
        'landing_cta_click',
    ];

    private const TRACKED_EVENTS = [
        'landing_cta_click',
        'onboarding_view',
        'onboarding_step_complete',
        'onboarding_dismiss',
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

            $properties = is_array($event->properties) ? $event->properties : [];

            if (in_array($eventName, self::SOURCE_TRACKED_EVENTS, true)) {
                $source = $this->normalizeSource($properties, $event->referrer);
                $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
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

        $onboardingExperimentVariants = $this->buildOnboardingExperiment($from, $to);
        $owner = (string) config('ops.owners.growth', 'growth');
        $onboardingWinner = collect($onboardingExperimentVariants)
            ->filter(fn (array $row): bool => isset($row['completion_rate_pct']))
            ->sortByDesc('completion_rate_pct')
            ->first();

        $totalEvents = array_sum($counts);
        $landingCtaClicks = $counts['landing_cta_click'] ?? 0;
        $onboardingCompletionRate = $this->rate(
            $counts['onboarding_step_complete'] ?? 0,
            $counts['onboarding_view'] ?? 0,
        );

        $aiSummary = [
            'period_days' => $days,
            'owner' => $owner,
            'kpis' => [
                'total_events' => $totalEvents,
                'unique_users' => count($uniqueUsers),
                'unique_sessions' => count($uniqueSessions),
                'landing_cta_clicks' => $landingCtaClicks,
                'onboarding_completion_rate_pct' => $onboardingCompletionRate,
            ],
            'top_cta' => array_slice($ctaCounts, 0, 5, true),
            'top_sources' => array_slice($sourceCounts, 0, 5, true),
            'onboarding_experiment' => $onboardingExperimentVariants,
            'plain_english' => [
                sprintf('Tracked %d events in the last %d days.', $totalEvents, $days),
                sprintf('Landing CTA clicks: %d.', $landingCtaClicks),
                sprintf(
                    'Onboarding completion rate: %s%%.',
                    $onboardingCompletionRate === null ? 'n/a' : number_format((float) $onboardingCompletionRate, 1),
                ),
                sprintf('Most clicked landing CTA: %s.', array_key_first($ctaCounts) ?? 'n/a'),
                sprintf(
                    'Onboarding winner: variant %s at %s%% completion.',
                    strtoupper((string) ($onboardingWinner['variant'] ?? '?')),
                    isset($onboardingWinner['completion_rate_pct']) ? number_format((float) $onboardingWinner['completion_rate_pct'], 1) : 'n/a',
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
                'landingCtaClicks' => $landingCtaClicks,
                'onboardingCompletionRatePct' => $onboardingCompletionRate,
            ],
            'eventCounts' => $counts,
            'dailyLabels' => $dailyBuckets,
            'dailySeries' => $seriesByEvent,
            'topCtas' => array_slice($ctaCounts, 0, 8, true),
            'topSources' => array_slice($sourceCounts, 0, 8, true),
            'onboardingExperimentVariants' => $onboardingExperimentVariants,
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

    private function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 1);
    }

}