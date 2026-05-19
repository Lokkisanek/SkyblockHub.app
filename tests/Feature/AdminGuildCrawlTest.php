<?php

namespace Tests\Feature;

use App\Jobs\ProcessGuildCrawlJob;
use App\Models\User;
use App\Support\AdminGuildCrawlStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminGuildCrawlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(AdminGuildCrawlStatus::CACHE_KEY);
        Cache::forget(AdminGuildCrawlStatus::LOCK_KEY);
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'discord_username' => 'lokkisan',
            'minecraft_username' => 'Lokkisanecek',
        ]);
    }

    public function test_guest_cannot_access_guild_crawl_status(): void
    {
        $this->getJson(route('admin.guild-crawl.status'))->assertUnauthorized();
    }

    public function test_non_admin_cannot_start_guild_crawl(): void
    {
        $user = User::factory()->create([
            'discord_username' => 'other',
            'minecraft_username' => 'other',
        ]);

        $this->actingAs($user)
            ->postJson(route('admin.guild-crawl.start'), [
                'guild_list' => 'Test Guild',
            ])
            ->assertForbidden();
    }

    public function test_start_rejects_sync_queue_driver(): void
    {
        config([
            'hypixel.api_key' => 'test-key',
            'queue.default' => 'sync',
        ]);

        $response = $this->actingAs($this->adminUser())
            ->postJson(route('admin.guild-crawl.start'), [
                'guild_list' => 'Test Guild',
            ])
            ->assertStatus(422);

        $this->assertStringContainsString(
            'QUEUE_CONNECTION',
            (string) $response->json('message'),
        );
    }

    public function test_admin_can_start_guild_crawl_and_poll_status(): void
    {
        Bus::fake();
        config([
            'hypixel.api_key' => 'test-key',
            'queue.default' => 'database',
            'queue.connections.database.retry_after' => 7500,
        ]);

        $this->actingAs($this->adminUser())
            ->postJson(route('admin.guild-crawl.start'), [
                'guild_list' => "Guild Alpha\nGuild Beta",
                'max_guilds' => 10,
                'member_limit' => 100,
                'delay_ms' => 500,
                'new_only' => true,
            ])
            ->assertOk()
            ->assertJsonPath('guild_crawl.status', 'queued');

        Bus::assertDispatched(ProcessGuildCrawlJob::class, function (ProcessGuildCrawlJob $job): bool {
            return count($job->guildNames) === 2
                && $job->guildNames[0] === 'Guild Alpha'
                && $job->options['max_guilds'] === 10;
        });

        $this->actingAs($this->adminUser())
            ->getJson(route('admin.guild-crawl.status'))
            ->assertOk()
            ->assertJsonPath('guild_crawl.status', 'queued')
            ->assertJsonPath('guild_crawl.guild_names_requested', 2);
    }

    public function test_start_rejects_empty_guild_list(): void
    {
        config([
            'hypixel.api_key' => 'test-key',
            'queue.default' => 'database',
            'queue.connections.database.retry_after' => 7500,
        ]);

        $this->actingAs($this->adminUser())
            ->postJson(route('admin.guild-crawl.start'), [
                'guild_list' => "  \n  ",
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_request_cancel(): void
    {
        AdminGuildCrawlStatus::merge([
            'status' => 'ingesting',
            'message' => 'Running',
        ]);

        $this->actingAs($this->adminUser())
            ->postJson(route('admin.guild-crawl.cancel'))
            ->assertOk()
            ->assertJsonPath('guild_crawl.cancel_requested', true);
    }

    public function test_cancel_clears_stale_running_crawl(): void
    {
        Cache::put(AdminGuildCrawlStatus::CACHE_KEY, [
            'status' => 'ingesting',
            'message' => 'Stuck',
            'processed' => 123,
            'total_members' => 1433,
            'updated_at' => now()->subMinutes(30)->toIso8601String(),
        ], now()->addDay());

        $this->actingAs($this->adminUser())
            ->postJson(route('admin.guild-crawl.cancel'))
            ->assertOk()
            ->assertJsonPath('guild_crawl.status', 'cancelled');

        $this->assertFalse(AdminGuildCrawlStatus::isRunning());
    }
}
