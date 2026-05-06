<?php

namespace Tests\Feature;

use App\Models\FunnelEvent;
use App\Services\AdminAnalyticsReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_includes_onboarding_and_upgrade_variant_performance(): void
    {
        FunnelEvent::query()->create([
            'event_name' => 'onboarding_view',
            'properties' => ['variant' => 'a'],
            'occurred_at' => now(),
        ]);

        FunnelEvent::query()->create([
            'event_name' => 'onboarding_step_complete',
            'properties' => ['variant' => 'a'],
            'occurred_at' => now(),
        ]);

        FunnelEvent::query()->create([
            'event_name' => 'upgrade_prompt_impression',
            'properties' => ['variant' => 'b'],
            'occurred_at' => now(),
        ]);

        FunnelEvent::query()->create([
            'event_name' => 'upgrade_prompt_cta',
            'properties' => ['variant' => 'b'],
            'occurred_at' => now(),
        ]);

        $report = app(AdminAnalyticsReportService::class)->buildReport(7);

        $this->assertSame(1, $report['eventCounts']['onboarding_view']);
        $this->assertSame(1, $report['eventCounts']['onboarding_step_complete']);

        $this->assertSame('a', $report['onboardingExperimentVariants'][0]['variant']);
        $this->assertSame(100.0, $report['onboardingExperimentVariants'][0]['completion_rate_pct']);

        $this->assertSame('b', $report['experimentVariants'][1]['variant']);
        $this->assertSame(100.0, $report['experimentVariants'][1]['cta_rate_pct']);
    }
}