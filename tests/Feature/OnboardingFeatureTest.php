<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OnboardingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_complete_onboarding_step(): void
    {
        $this->post('/onboarding/complete-step', [
            'step' => 'open_dashboard',
        ])->assertRedirect('/login');
    }

    public function test_authenticated_user_can_complete_step(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/onboarding/complete-step', [
                'step' => 'open_dashboard',
            ])
            ->assertStatus(303);

        $onboarding = UserOnboarding::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($onboarding);
        $this->assertContains('open_dashboard', $onboarding->completed_steps ?? []);
    }

    public function test_linked_minecraft_user_has_link_step_completed_in_shared_props(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('onboarding.steps', 2)
                ->where('onboarding.segment', 'linked')
            );
    }
}
