<?php

namespace App\Http\Controllers;

use App\Models\CookieConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CookieConsentController extends Controller
{
    public function store(Request $request): JsonResponse
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

        return response()->json(['status' => 'ok']);
    }
}
