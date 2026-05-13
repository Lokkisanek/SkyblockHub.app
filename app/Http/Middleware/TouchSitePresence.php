<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TouchSitePresence
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->isStarted()) {
            return $next($request);
        }

        $sessionId = $request->session()->getId();
        if ($sessionId === '' || ! Schema::hasTable('site_presence')) {
            return $next($request);
        }

        try {
            DB::table('site_presence')->updateOrInsert(
                ['id' => $sessionId],
                ['last_seen' => time()]
            );
        } catch (\Throwable) {
            // Non-fatal: presence is best-effort.
        }

        return $next($request);
    }
}
