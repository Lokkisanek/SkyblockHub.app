<?php

namespace Tests\Feature;

use App\Mail\AdminAnalyticsReviewDigestMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminAnalyticsDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_digest_command_sends_mail_to_testing_admin(): void
    {
        Mail::fake();
        Http::fake([
            'https://discord.test/webhook' => Http::response([], 204),
        ]);

        config()->set('services.discord.analytics_webhook_url', 'https://discord.test/webhook');

        User::factory()->create([
            'email' => 'admin@example.com',
            'discord_username' => 'lokkisan',
            'minecraft_username' => 'Lokkisanecek',
        ]);

        Artisan::call('analytics:review-digest', ['--days' => 7]);

        Mail::assertSent(AdminAnalyticsReviewDigestMail::class, function (AdminAnalyticsReviewDigestMail $mail): bool {
            return (string) ($mail->report['owner'] ?? '') === 'growth';
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://discord.test/webhook'
                && str_contains((string) $request->body(), 'SkyblockHub weekly review');
        });
    }
}