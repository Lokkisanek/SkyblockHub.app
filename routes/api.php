<?php

use App\Http\Controllers\Api\SkyCryptProxyController;
use App\Http\Controllers\Api\SocialProofMetricsController;
use App\Http\Controllers\Api\BinSniperAnalysisController;
use App\Http\Controllers\Api\BazaarController as ApiBazaarController;
use App\Http\Controllers\CraftingArbitrageController;
use App\Http\Controllers\KarmaController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| SkyCrypt Profile Proxy
|--------------------------------------------------------------------------
*/
Route::get('/skycrypt/{username}', [SkyCryptProxyController::class, 'profile'])
    ->middleware('throttle:30,1')
    ->where('username', '[A-Za-z0-9_]{1,16}')
    ->name('api.skycrypt.profile');

/*
|--------------------------------------------------------------------------
| Karma Voting
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/karma/vote', [KarmaController::class, 'vote'])->name('api.karma.vote');
    Route::get('/karma/{targetId}', [KarmaController::class, 'status'])->name('api.karma.status');
});

/*
|--------------------------------------------------------------------------
| Bazaar Intelligence API
|--------------------------------------------------------------------------
*/
Route::prefix('v1/bazaar')->group(function () {
    Route::get('/live', [ApiBazaarController::class, 'live']);
    Route::get('/arbitrage/recipes', [ApiBazaarController::class, 'recipeArbitrage']);
    Route::get('/history/{productId}', [ApiBazaarController::class, 'history']);
});

/*
|--------------------------------------------------------------------------
| Crafting Arbitrage API
|--------------------------------------------------------------------------
*/
Route::get('/arbitrage/crafting', [CraftingArbitrageController::class, 'api']);

/*
|--------------------------------------------------------------------------
| BIN Sniper Advanced Analysis API
|--------------------------------------------------------------------------
*/
Route::post('/bin-sniper/analyze', [BinSniperAnalysisController::class, 'analyze'])
    ->middleware('throttle:30,1')
    ->name('api.bin-sniper.analyze');

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('api.stripe.webhook');

Route::get('/social-proof-metrics', SocialProofMetricsController::class)
    ->middleware('throttle:60,1')
    ->name('api.social-proof.metrics');
