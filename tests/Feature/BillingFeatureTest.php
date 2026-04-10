<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\TrialRedemption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_billing_page(): void
    {
        $this->get('/billing')
            ->assertRedirect('/login');
    }

    public function test_trial_requires_discord_linked_account(): void
    {
        $user = User::factory()->create([
            'discord_id' => null,
        ]);

        $this->actingAs($user)
            ->from('/billing')
            ->post('/billing/trial', [
                'tier' => 'vip',
            ])
            ->assertSessionHasErrors('billing')
            ->assertRedirect('/billing');
    }

    public function test_discord_user_can_start_trial_once(): void
    {
        $user = User::factory()->create([
            'discord_id' => '1234567890',
            'discord_username' => 'Tester',
        ]);

        $this->actingAs($user)
            ->post('/billing/trial', [
                'tier' => 'mvp',
            ])
            ->assertSessionHasNoErrors();

        $entitlement = UserEntitlement::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($entitlement);
        $this->assertSame('trialing', $entitlement->status);
        $this->assertSame('mvp', $entitlement->tier);
        $this->assertSame(3, $entitlement->dashboard_slots_unlocked);
        $this->assertNotNull($entitlement->trial_started_at);
        $this->assertNotNull($entitlement->trial_ends_at);

        $this->actingAs($user)
            ->from('/billing')
            ->post('/billing/trial', [
                'tier' => 'vip',
            ])
            ->assertSessionHasErrors('billing')
            ->assertRedirect('/billing');
    }

    public function test_trial_remains_locked_for_same_discord_after_account_deletion(): void
    {
        $user = User::factory()->create([
            'discord_id' => 'same_discord_1',
            'discord_username' => 'DiscordA',
        ]);

        $this->actingAs($user)
            ->post('/billing/trial', [
                'tier' => 'vip',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('trial_redemptions', [
            'discord_id' => 'same_discord_1',
        ]);

        $user->delete();

        $recreatedUser = User::factory()->create([
            'discord_id' => 'same_discord_1',
            'discord_username' => 'DiscordA',
        ]);

        $this->actingAs($recreatedUser)
            ->from('/billing')
            ->post('/billing/trial', [
                'tier' => 'mvp',
            ])
            ->assertSessionHasErrors('billing')
            ->assertRedirect('/billing');

        $this->assertSame(1, TrialRedemption::query()->where('discord_id', 'same_discord_1')->count());
    }
}
