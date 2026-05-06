<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelAnalyticsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_track_allowed_funnel_event(): void
    {
        $response = $this->postJson('/analytics/funnel-event', [
            'event_name' => 'landing_cta_click',
            'properties' => [
                'cta' => 'discord_login',
            ],
            'context' => [
                'path' => '/',
            ],
        ]);

        $response->assertAccepted();

        $this->assertDatabaseHas('funnel_events', [
            'event_name' => 'landing_cta_click',
            'path' => '/',
        ]);
    }

    public function test_rejects_unknown_event_name(): void
    {
        $response = $this->postJson('/analytics/funnel-event', [
            'event_name' => 'unknown_event',
        ]);

        $response->assertStatus(422);
    }

    public function test_trial_start_creates_funnel_event(): void
    {
        $user = User::factory()->create([
            'discord_id' => '1234567890',
            'discord_username' => 'Tester',
        ]);

        $this->actingAs($user)
            ->post('/billing/trial', [
                'tier' => 'vip',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('funnel_events', [
            'event_name' => 'trial_start',
            'user_id' => $user->id,
        ]);
    }
}
