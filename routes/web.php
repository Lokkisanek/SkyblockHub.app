<?php

use App\Http\Controllers\BazaarController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BinSniperController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\CraftingArbitrageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DungeonPartyController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\LeaderboardsController;
use App\Http\Controllers\MayorController;
use App\Http\Controllers\NpcFlipsController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileStatsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => !auth()->check(),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::post('/cookie-consent', [CookieConsentController::class, 'store'])->name('cookie-consent.store');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::post('/dashboard/save', [DashboardController::class, 'save'])->middleware('auth')->name('dashboard.save');

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

Route::middleware('auth')->group(function () {
    Route::get('/billing', [BillingController::class, 'index'])->name('billing');
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::post('/billing/trial', [BillingController::class, 'startTrial'])->name('billing.trial');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('testing.admin')->group(function () {
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

require __DIR__.'/auth.php';
