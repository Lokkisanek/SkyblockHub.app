<?php

namespace App\Http\Controllers;

use App\Models\CookieConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;

class CookieConsentController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse|Response
    {
        $validated = $request->validate([
            'level' => 'required|in:all,essential',
        ]);

        CookieConsent::create([
            'user_id' => $request->user()?->id,
            'session_id' => $request->session()->getId(),
            'level' => $validated['level'],
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'consented_at' => now(),
        ]);

        if ($request->header('X-Inertia')) {
            return back(status: 303);
        }

        return response()->json(['status' => 'ok']);
    }
}
