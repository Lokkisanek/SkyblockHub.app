<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreRoutesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_routes_load(): void
    {
        $this->get('/')->assertOk();
        $this->get('/dashboard')->assertOk();
        $this->get('/bazaar')->assertOk();
        $this->get('/npc-flips')->assertOk();
        $this->get('/profile-stats')->assertOk();
        $this->get('/event-timer')->assertOk();
        $this->get('/mayors')->assertOk();
        $this->get('/trust-index')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/privacy')->assertOk();
        $this->get('/terms')->assertOk();
    }

    public function test_authenticated_profile_routes_load(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
    }
}
