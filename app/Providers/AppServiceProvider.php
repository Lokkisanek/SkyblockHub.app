<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // In production always resolve frontend assets from the build manifest.
        // This prevents accidental reliance on a stale public/hot file.
        if (app()->environment('production')) {
            Vite::useHotFile(storage_path('framework/vite.hot'));
        }

        Vite::prefetch(concurrency: 3);

        Event::listen(
            \SocialiteProviders\Manager\SocialiteWasCalled::class,
            \SocialiteProviders\Discord\DiscordExtendSocialite::class.'@handle',
        );

        Event::listen(
            \SocialiteProviders\Manager\SocialiteWasCalled::class,
            \SocialiteProviders\Microsoft\MicrosoftExtendSocialite::class.'@handle',
        );
    }
}
