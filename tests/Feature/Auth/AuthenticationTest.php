<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_route_redirects_to_home_for_slide_panel_auth(): void
    {
        $response = $this->get('/login');

        $response->assertRedirect('/');
    }

    public function test_local_password_login_post_route_is_not_available(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(405);
        $this->assertGuest();
    }

    public function test_discord_auth_entrypoint_exists(): void
    {
        $response = $this->get(route('auth.discord', absolute: false));

        $response->assertStatus(302);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
