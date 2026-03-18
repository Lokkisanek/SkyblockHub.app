<?php

namespace App\Http\Middleware;

use App\Support\TestingAdminGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTestingAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! TestingAdminGate::allows($request->user())) {
            abort(403);
        }

        return $next($request);
    }
}
