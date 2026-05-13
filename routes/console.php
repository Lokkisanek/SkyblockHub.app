<?php

use App\Jobs\AnalyzeMarketManipulationJob;
use App\Jobs\FetchHypixelBazaarJob;
use App\Mail\AdminAnalyticsReviewDigestMail;
use App\Models\User;
use App\Services\AdminAnalyticsDigestFormatter;
use App\Services\AdminAnalyticsReportService;
use App\Support\TestingAdminGate;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('analytics:review-digest {--days=7 : Number of days to summarize in the digest}', function () {
    $days = max(1, (int) $this->option('days') ?: 7);
    $report = app(AdminAnalyticsReportService::class)->buildReport($days);
    $discordWebhookUrl = (string) config('services.discord.analytics_webhook_url', '');

    $recipient = User::query()
        ->whereNotNull('email')
        ->get()
        ->first(fn (User $user): bool => TestingAdminGate::allows($user));

    $didSendEmail = false;
    $didSendDiscord = false;

    if ($recipient?->email) {
        Mail::to($recipient->email)->send(new AdminAnalyticsReviewDigestMail($report));
        $didSendEmail = true;
        $this->info(sprintf(
            'Sent analytics review digest to %s (%s).',
            $recipient->email,
            $recipient->discord_username ?? $recipient->name,
        ));
    }

    if ($discordWebhookUrl !== '') {
        $discordMessage = app(AdminAnalyticsDigestFormatter::class)->buildDiscordDigestMessage($report);
        $response = Http::asJson()->post($discordWebhookUrl, [
            'content' => $discordMessage,
        ]);

        if ($response->successful()) {
            $didSendDiscord = true;
            $this->info('Sent analytics review digest to Discord webhook.');
        } else {
            $this->warn(sprintf(
                'Discord webhook returned HTTP %s.',
                $response->status(),
            ));
        }
    }

    if (! $didSendEmail && ! $didSendDiscord) {
        $this->error('Admin analytics digest was not sent because no email recipient or Discord webhook was configured.');

        return 1;
    }

    return 0;
})->purpose('Send the admin analytics review digest');

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
*/
// BIN fetch scans many auction pages, so keep it infrequent to avoid DB/API pressure.
Schedule::command('bin:fetch')->everyThirtyMinutes()->withoutOverlapping(45);

// Run bazaar jobs inside the scheduler process with overlap locks.
// This prevents queue buildup when a fetch takes longer than expected.
Schedule::call(fn () => app(FetchHypixelBazaarJob::class)->handle())
    ->name('bazaar:fetch-live-prices')
    ->everyFiveMinutes()
    ->withoutOverlapping(15);

Schedule::call(fn () => app(AnalyzeMarketManipulationJob::class)->handle())
    ->name('bazaar:analyze-manipulation')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20);

Schedule::command('analytics:review-digest --days=7')
    ->name('analytics:review-digest')
    ->weeklyOn(1, '09:00')
    ->withoutOverlapping(120);
