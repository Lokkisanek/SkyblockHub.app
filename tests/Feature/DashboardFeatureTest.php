<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_dashboard_in_readonly_mode(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('requiresLogin', true)
                ->where('canEditDashboard', false)
            );
    }

    public function test_logged_in_unlinked_user_can_view_but_not_edit(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => false,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('requiresMinecraftLink', true)
                ->where('canEditDashboard', false)
            );
    }

    public function test_linked_user_can_save_dashboard(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        $response = $this->actingAs($user)->post('/dashboard/save', [
            'is_public' => false,
            'widgets' => [
                [
                    'type' => 'skin_view_widget',
                    'title' => 'Main Skin',
                    'x' => 1,
                    'y' => 1,
                    'w' => 3,
                    'h' => 5,
                    'settings' => [
                        'username' => 'Lokkisanek',
                    ],
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $dashboard = UserDashboard::query()->where('user_id', $user->id)->where('slot_index', 1)->first();

        $this->assertNotNull($dashboard);
        $this->assertSame(1, $dashboard->widgets()->count());
    }

    public function test_new_predefined_widget_types_can_be_saved(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        $response = $this->actingAs($user)->post('/dashboard/save', [
            'is_public' => false,
            'widgets' => [
                [
                    'type' => 'skin_view_widget',
                    'title' => '3D Skin',
                    'x' => 1,
                    'y' => 1,
                    'w' => 3,
                    'h' => 5,
                    'settings' => ['username' => 'Lokkisanek'],
                ],
                [
                    'type' => 'inventory_gui_widget',
                    'title' => 'Inventory',
                    'x' => 7,
                    'y' => 1,
                    'w' => 8,
                    'h' => 5,
                    'settings' => ['username' => 'Lokkisanek'],
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $dashboard = UserDashboard::query()->where('user_id', $user->id)->where('slot_index', 1)->first();

        $this->assertNotNull($dashboard);
        $this->assertSame(2, $dashboard->widgets()->count());
        $this->assertSame(['skin_view_widget', 'inventory_gui_widget'], $dashboard->widgets()->pluck('type')->all());
    }

    public function test_inventory_gui_widget_can_be_saved(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        $response = $this->actingAs($user)->post('/dashboard/save', [
            'is_public' => false,
            'widgets' => [
                [
                    'type' => 'inventory_gui_widget',
                    'title' => 'Inventory',
                    'x' => 1,
                    'y' => 1,
                    'w' => 8,
                    'h' => 5,
                    'settings' => ['username' => 'Lokkisanek'],
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $dashboard = UserDashboard::query()->where('user_id', $user->id)->where('slot_index', 1)->first();

        $this->assertNotNull($dashboard);
        $this->assertSame(1, $dashboard->widgets()->count());
        $this->assertSame('inventory_gui_widget', $dashboard->widgets()->first()->type);
    }

    public function test_profile_skills_widget_can_be_saved(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        $response = $this->actingAs($user)->post('/dashboard/save', [
            'is_public' => false,
            'widgets' => [
                [
                    'type' => 'profile_skills_widget',
                    'title' => 'Skills',
                    'x' => 1,
                    'y' => 1,
                    'w' => 6,
                    'h' => 5,
                    'settings' => ['username' => 'Lokkisanek'],
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $dashboard = UserDashboard::query()->where('user_id', $user->id)->where('slot_index', 1)->first();

        $this->assertNotNull($dashboard);
        $this->assertSame(1, $dashboard->widgets()->count());
        $this->assertSame('profile_skills_widget', $dashboard->widgets()->first()->type);
    }

    public function test_profile_collections_widget_can_be_saved(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        $response = $this->actingAs($user)->post('/dashboard/save', [
            'is_public' => false,
            'widgets' => [
                [
                    'type' => 'profile_collections_widget',
                    'title' => 'Collections',
                    'x' => 1,
                    'y' => 1,
                    'w' => 6,
                    'h' => 5,
                    'settings' => [
                        'username' => 'Lokkisanek',
                        'collection_selected_keys' => ['farming:WHEAT', 'mining:COBBLESTONE'],
                    ],
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $dashboard = UserDashboard::query()->where('user_id', $user->id)->where('slot_index', 1)->first();

        $this->assertNotNull($dashboard);
        $this->assertSame(1, $dashboard->widgets()->count());
        $this->assertSame('profile_collections_widget', $dashboard->widgets()->first()->type);
    }

    public function test_overlapping_widgets_are_rejected(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        $response = $this->actingAs($user)
            ->from('/dashboard')
            ->post('/dashboard/save', [
                'is_public' => false,
                'widgets' => [
                    [
                        'type' => 'skin_view_widget',
                        'title' => 'A',
                        'x' => 1,
                        'y' => 1,
                        'w' => 3,
                        'h' => 5,
                        'settings' => ['username' => 'A'],
                    ],
                    [
                        'type' => 'inventory_gui_widget',
                        'title' => 'B',
                        'x' => 2,
                        'y' => 2,
                        'w' => 8,
                        'h' => 5,
                        'settings' => ['username' => 'B'],
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('dashboard')->assertRedirect('/dashboard');
    }

    public function test_widget_outside_20x20_grid_is_rejected(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        $response = $this->actingAs($user)
            ->from('/dashboard')
            ->post('/dashboard/save', [
                'is_public' => false,
                'widgets' => [
                    [
                        'type' => 'inventory_gui_widget',
                        'title' => 'Too far right',
                        'x' => 14,
                        'y' => 1,
                        'w' => 8,
                        'h' => 5,
                        'settings' => ['username' => 'Lokkisanek'],
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('dashboard')->assertRedirect('/dashboard');
    }
}
