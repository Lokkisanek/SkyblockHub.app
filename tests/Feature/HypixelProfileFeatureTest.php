<?php

namespace Tests\Feature;

use App\Models\ProfileCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HypixelProfileFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_endpoint_uses_cached_profile_when_hypixel_is_unavailable(): void
    {
        $uuid = '1234567890abcdef1234567890abcdef';
        $username = 'Lokkisanecek';

        ProfileCache::query()->create([
            'minecraft_uuid' => $uuid,
            'profile_id' => 'profile-1',
            'cute_name' => 'Strawberry',
            'raw_data' => [
                'cute_name' => 'Strawberry',
                'selected' => true,
                'game_mode' => 'normal',
                'data' => [
                    'skyblock_level' => 42,
                    'networth' => [
                        'networth' => 123456789,
                    ],
                ],
            ],
            'fetched_at' => now(),
        ]);

        Http::fake([
            'https://api.mojang.com/users/profiles/minecraft/*' => Http::response([
                'id' => $uuid,
            ], 200),
            'https://api.hypixel.net/*' => Http::response([
                'success' => false,
                'cause' => 'temporary upstream failure',
            ], 500),
        ]);

        $this->getJson('/api/profile/minecraft/'.$username)
            ->assertOk()
            ->assertJsonPath('source', 'db-cache')
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.username', $username)
            ->assertJsonPath('data.profiles.profile-1.cute_name', 'Strawberry')
            ->assertJsonPath('data.profiles.profile-1.data.skyblock_level', 42);

        $this->getJson('/api/skycrypt/'.$username)
            ->assertOk()
            ->assertJsonPath('source', 'db-cache')
            ->assertJsonPath('data.uuid', $uuid);
    }
}
