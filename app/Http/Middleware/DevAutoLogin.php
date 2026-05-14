<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DevAutoLogin
{
    /**
     * Sentinel Discord id reserved for the throwaway local dev account.
     * Real Discord snowflakes are numeric; this string will never collide.
     */
    private const LOCAL_DEV_DISCORD_ID = '__local_dev_login__';

    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local')) {
            return $next($request);
        }

        if (! config('app.dev_auto_login')) {
            return $next($request);
        }

        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        $user = User::query()->firstOrCreate(
            ['discord_id' => self::LOCAL_DEV_DISCORD_ID],
            [
                'name' => 'Local Dev',
                'discord_username' => 'local-dev',
                'is_mc_linked' => true,
                'minecraft_username' => 'LocalDev',
                'minecraft_uuid' => null,
                'password' => null,
            ]
        );

        if (! $user->is_mc_linked) {
            $user->forceFill(['is_mc_linked' => true])->save();
        }

        Auth::guard('web')->login($user, remember: false);

        return $next($request);
    }
}
