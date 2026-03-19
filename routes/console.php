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
Schedule::command('bin:fetch')->everyThirtySeconds()->withoutOverlapping();
Schedule::job(new FetchHypixelBazaarJob())->everyMinute()->withoutOverlapping();
Schedule::job(new AnalyzeMarketManipulationJob())->everyFiveMinutes()->withoutOverlapping();
