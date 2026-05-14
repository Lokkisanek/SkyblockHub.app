<?php

use App\Http\Controllers\BazaarController;
use App\Http\Controllers\BinSniperController;
use App\Http\Controllers\AnaliticsController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\CraftingArbitrageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DungeonPartyController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\FunnelAnalyticsController;
use App\Http\Controllers\LeaderboardsController;
use App\Http\Controllers\MayorController;
use App\Http\Controllers\NpcFlipsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\SocialProofMetricsController;
use App\Http\Controllers\ProfileStatsController;
use App\Services\SocialProofMetricsService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $socialProofMetrics = app(SocialProofMetricsService::class)->getMetrics();

    return Inertia::render('Welcome', [
        'canLogin' => !auth()->check(),
        'socialProofMetrics' => $socialProofMetrics,
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

/** Hypixel developer dashboard: human-readable ownership + operator Minecraft name. */
Route::view('/hypixel-developer-verification', 'hypixel-developer-verification')->name('hypixel.developer-verification');

/** If Hypixel asks for a token file, set HYPIXEL_SITE_VERIFICATION in .env (same value as meta tag). */
Route::get('/hypixel-verification.txt', function () {
    $token = config('hypixel.site_verification.meta_content');

    if ($token === '') {
        abort(404);
    }

    return response($token, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('hypixel.verification-file');

Route::get('/api/social-proof-metrics', SocialProofMetricsController::class)
    ->middleware('throttle:60,1')
    ->name('api.social-proof.metrics');

Route::post('/cookie-consent', [CookieConsentController::class, 'store'])->name('cookie-consent.store');
Route::post('/analytics/funnel-event', [FunnelAnalyticsController::class, 'store'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->middleware('throttle:120,1')
    ->name('analytics.funnel-event');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/visit/{minecraftUuid}', [DashboardController::class, 'visit'])->name('dashboard.visit');
Route::post('/dashboard/save', [DashboardController::class, 'save'])->middleware('auth')->name('dashboard.save');
Route::get('/dashboard/info', fn () => redirect()->route('dashboard'))->name('dashboard.info');

Route::get('/leaderboards/info', function () {
    return Inertia::render('LeaderboardsInfo');
})->name('leaderboards.info');

Route::get('/bazaar', [BazaarController::class, 'index'])->name('bazaar');
Route::get('/npc-flips', [NpcFlipsController::class, 'index'])->name('npc-flips');
Route::get('/profile-stats', [ProfileStatsController::class, 'index'])->name('profile-stats');
Route::get('/event-timer', [EventsController::class, 'index'])->name('event-timer');
Route::get('/mayors', [MayorController::class, 'index'])->name('mayors');
Route::get('/leaderboards', [LeaderboardsController::class, 'index'])->name('leaderboards');

Route::get('/about', function () {
    return Inertia::render('About');
})->name('about');

Route::get('/privacy', function () {
    return Inertia::render('Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Terms');
})->name('terms');

Route::get('/pricing', function () {
    return redirect()->to('https://buymeacoffee.com/lokkisan');
})->name('pricing');
Route::get('/analytics', function () {
    return redirect()->route('admin.index');
})->name('analytics.redirect');

Route::middleware('auth')->group(function () {
    Route::post('/onboarding/complete-step', [OnboardingController::class, 'completeStep'])->name('onboarding.complete-step');
    Route::post('/onboarding/dismiss', [OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('testing.admin')->group(function () {
        Route::get('/admin', [AnaliticsController::class, 'index'])->name('admin.index');
        Route::get('/analitics', fn () => redirect()->route('admin.index'))->name('analitics.index');

        Route::get('/dungeon-party', [DungeonPartyController::class, 'index'])->name('dungeon-party');
        Route::post('/dungeon-party', [DungeonPartyController::class, 'store'])->name('dungeon-party.store');
        Route::delete('/dungeon-party', [DungeonPartyController::class, 'destroy'])->name('dungeon-party.destroy');

        // Portfolio Tracker
        Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio');
        Route::post('/portfolio', [PortfolioController::class, 'store'])->name('portfolio.store');
        Route::post('/portfolio/sell', [PortfolioController::class, 'sell'])->name('portfolio.sell');
        Route::delete('/portfolio', [PortfolioController::class, 'destroy'])->name('portfolio.destroy');

        // Crafting Arbitrage
        Route::get('/crafting', [CraftingArbitrageController::class, 'index'])->name('crafting');

        // Lowest BIN Sniper
        Route::get('/bin-sniper', [BinSniperController::class, 'index'])->name('bin-sniper');
        Route::post('/bin-sniper/alert', [BinSniperController::class, 'storeAlert'])->name('bin-sniper.alert.store');
        Route::delete('/bin-sniper/alert', [BinSniperController::class, 'destroyAlert'])->name('bin-sniper.alert.destroy');
        Route::patch('/bin-sniper/alert', [BinSniperController::class, 'toggleAlert'])->name('bin-sniper.alert.toggle');
    });
});

// SEO Routes
Route::get('/sitemap.xml', function () {
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
    $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;

    $urls = [
        ['url' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
        ['url' => url('/dashboard'), 'changefreq' => 'daily', 'priority' => '0.9'],
        ['url' => url('/bazaar'), 'changefreq' => 'daily', 'priority' => '0.9'],
        ['url' => url('/npc-flips'), 'changefreq' => 'daily', 'priority' => '0.8'],
        ['url' => url('/profile-stats'), 'changefreq' => 'weekly', 'priority' => '0.7'],
        ['url' => url('/event-timer'), 'changefreq' => 'daily', 'priority' => '0.8'],
        ['url' => url('/leaderboards'), 'changefreq' => 'weekly', 'priority' => '0.7'],
    ];

    foreach ($urls as $page) {
        $sitemap .= '  <url>'.PHP_EOL;
        $sitemap .= '    <loc>'.$page['url'].'</loc>'.PHP_EOL;
        $sitemap .= '    <changefreq>'.$page['changefreq'].'</changefreq>'.PHP_EOL;
        $sitemap .= '    <priority>'.$page['priority'].'</priority>'.PHP_EOL;
        $sitemap .= '  </url>'.PHP_EOL;
    }

    $sitemap .= '</urlset>';

    return response($sitemap, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

require __DIR__.'/auth.php';
