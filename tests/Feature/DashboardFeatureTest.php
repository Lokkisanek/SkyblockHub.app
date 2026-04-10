<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDashboard;
use App\Models\UserEntitlement;
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

    public function test_linked_user_can_save_dashboard_slot_one(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        $response = $this->actingAs($user)->post('/dashboard/save', [
            'slot_index' => 1,
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
            'slot_index' => 1,
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
                    'h' => 6,
                    'settings' => ['username' => 'Lokkisanek', 'show_hotbar' => false],
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $dashboard = UserDashboard::query()->where('user_id', $user->id)->where('slot_index', 1)->first();

        $this->assertNotNull($dashboard);
        $this->assertSame(2, $dashboard->widgets()->count());
        $this->assertSame(['skin_view_widget', 'inventory_gui_widget'], $dashboard->widgets()->pluck('type')->all());
    }

    public function test_overlapping_widgets_are_rejected(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        $response = $this->actingAs($user)
            ->from('/dashboard')
            ->post('/dashboard/save', [
                'slot_index' => 1,
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
                        'h' => 6,
                        'settings' => ['username' => 'B', 'show_hotbar' => false],
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('dashboard')->assertRedirect('/dashboard');
    }

    public function test_slot_two_is_locked_without_active_entitlement(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        $response = $this->actingAs($user)
            ->from('/dashboard')
            ->post('/dashboard/save', [
                'slot_index' => 2,
                'is_public' => false,
                'widgets' => [
                    [
                        'type' => 'skin_view_widget',
                        'title' => 'Locked slot attempt',
                        'x' => 1,
                        'y' => 1,
                        'w' => 3,
                        'h' => 5,
                        'settings' => ['username' => 'Lokkisanek'],
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
                'slot_index' => 1,
                'is_public' => false,
                'widgets' => [
                    [
                        'type' => 'inventory_gui_widget',
                        'title' => 'Too far right',
                        'x' => 14,
                        'y' => 1,
                        'w' => 8,
                        'h' => 6,
                        'settings' => ['username' => 'Lokkisanek', 'show_hotbar' => false],
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('dashboard')->assertRedirect('/dashboard');
    }

    public function test_slot_two_unlocks_with_active_entitlement(): void
    {
        $user = User::factory()->create([
            'is_mc_linked' => true,
        ]);

        UserEntitlement::query()->create([
            'user_id' => $user->id,
            'dashboard_slots_unlocked' => 3,
            'status' => 'active',
            'provider' => 'stripe',
            'stripe_customer_id' => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
        ]);

        $response = $this->actingAs($user)->post('/dashboard/save', [
            'slot_index' => 2,
            'is_public' => true,
            'widgets' => [
                [
                    'type' => 'skin_view_widget',
                    'title' => 'Enchanted Sugar',
                    'x' => 1,
                    'y' => 1,
                    'w' => 3,
                    'h' => 5,
                    'settings' => ['username' => 'Lokkisanek'],
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $dashboard = UserDashboard::query()->where('user_id', $user->id)->where('slot_index', 2)->first();

        $this->assertNotNull($dashboard);
        $this->assertTrue($dashboard->is_public);
    }
}
