<?php

use App\Jobs\AnalyzeMarketManipulationJob;
use App\Jobs\FetchHypixelBazaarJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
