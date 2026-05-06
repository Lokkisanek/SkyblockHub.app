<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SentryOpsConfigTest extends TestCase
{
    #[Test]
    public function sentry_ops_config_defines_routing_and_triage_rules(): void
    {
        $config = config('ops.sentry');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('routing', $config);
        $this->assertArrayHasKey('triage', $config);
        $this->assertArrayHasKey('runbook', $config);

        $this->assertArrayHasKey('frontend_runtime', $config['routing']);
        $this->assertArrayHasKey('backend_runtime', $config['routing']);
        $this->assertArrayHasKey('performance_regression', $config['routing']);
        $this->assertArrayHasKey('release_regression', $config['routing']);

        $this->assertContains('Assign frontend issues to frontend, backend errors to backend, and cross-cutting or release regressions to ops.', $config['triage']['rules']);
        $this->assertContains('Open the Sentry issue and confirm environment, release, and owner tags.', $config['runbook']['first_15_minutes']);
    }
}