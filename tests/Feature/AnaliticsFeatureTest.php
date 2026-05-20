<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\LocalDevAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnaliticsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_page(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_non_admin_user_gets_forbidden_on_admin_page(): void
    {
        $user = User::factory()->create([
            'discord_username' => 'not-admin',
            'minecraft_username' => 'not-admin-mc',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_testing_admin_can_access_admin_page(): void
    {
        $user = User::factory()->create([
            'discord_username' => 'lokkisan',
            'minecraft_username' => 'Lokkisanecek',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Index'));
    }

    public function test_local_dev_user_cannot_access_admin_outside_local_env(): void
    {
        $user = User::factory()->create([
            'discord_id' => LocalDevAccount::DISCORD_ID,
            'discord_username' => 'local-dev',
            'minecraft_username' => 'LocalDev',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_legacy_analitics_url_redirects_to_admin_page(): void
    {
        $user = User::factory()->create([
            'discord_username' => 'lokkisan',
            'minecraft_username' => 'Lokkisanecek',
        ]);

        $this->actingAs($user)
            ->get('/analitics')
            ->assertRedirect('/admin');
    }
}
