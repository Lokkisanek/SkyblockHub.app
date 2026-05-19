<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'discord_username' => 'lokkisan',
            'minecraft_username' => 'Lokkisanecek',
        ]);
    }

    public function test_hypixel_health_reports_invalid_key_via_query_param(): void
    {
        config(['hypixel.api_key' => 'bad-key']);

        Http::fake([
            'api.hypixel.net/v2/counts*' => Http::response([
                'success' => false,
                'cause' => 'Invalid API key',
            ], 200),
        ]);

        $health = app(AdminOperationsService::class)->hypixelApiHealth(true);

        $this->assertSame('invalid_key', $health['status']);
        $this->assertStringContainsString('Invalid API key', (string) $health['label']);
    }

    public function test_hypixel_health_reports_throttle(): void
    {
        config(['hypixel.api_key' => 'test-key']);

        Http::fake([
            'api.hypixel.net/v2/counts*' => Http::response([
                'success' => false,
                'cause' => 'Daily developer key throttle',
            ], 429),
        ]);

        $health = app(AdminOperationsService::class)->hypixelApiHealth(true);

        $this->assertSame('throttled', $health['status']);
        $this->assertStringContainsString('throttle', strtolower((string) $health['message']));
    }

    public function test_admin_can_refresh_hypixel_health(): void
    {
        config(['hypixel.api_key' => 'test-key']);

        Http::fake([
            'api.hypixel.net/v2/counts*' => Http::response([
                'success' => true,
                'playerCount' => 42000,
            ]),
        ]);

        $this->actingAs($this->adminUser())
            ->postJson(route('admin.operations.refresh-hypixel'))
            ->assertOk()
            ->assertJsonPath('hypixel.status', 'ok')
            ->assertJsonPath('hypixel.player_count', 42000);
    }

    public function test_operations_snapshot_includes_core_keys(): void
    {
        config(['hypixel.api_key' => '']);

        $snapshot = app(AdminOperationsService::class)->buildSnapshot();

        $this->assertArrayHasKey('hypixel', $snapshot);
        $this->assertArrayHasKey('profiles', $snapshot);
        $this->assertArrayHasKey('leaderboard', $snapshot);
        $this->assertArrayHasKey('users', $snapshot);
        $this->assertArrayHasKey('guild_crawl', $snapshot);
    }
}
