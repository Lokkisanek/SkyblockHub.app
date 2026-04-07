<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\Auth\LinkMinecraftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Discord OAuth Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('auth/discord', [DiscordController::class, 'redirect'])
        ->name('auth.discord');

    Route::get('auth/discord/callback', [DiscordController::class, 'callback'])
        ->name('auth.discord.callback');
});

/*
|--------------------------------------------------------------------------
| Named login route (redirects to home for slide-panel auth)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', fn () => redirect('/'))
        ->name('login');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('mc/link', [LinkMinecraftController::class, 'verify'])
        ->name('mc.link.verify');

    Route::post('mc/link/direct', [LinkMinecraftController::class, 'linkDirect'])
        ->name('mc.link.direct');

    Route::post('mc/unlink', [LinkMinecraftController::class, 'unlink'])
        ->name('mc.unlink');

    Route::get('auth/discord/link', [DiscordController::class, 'redirectLink'])
        ->name('auth.discord.link');

    Route::get('auth/discord/link/callback', [DiscordController::class, 'callbackLink'])
        ->name('auth.discord.link.callback');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
