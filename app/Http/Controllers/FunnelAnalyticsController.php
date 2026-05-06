<?php

namespace App\Http\Controllers;

use App\Services\FunnelAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunnelAnalyticsController extends Controller
{
    public function __construct(
        private readonly FunnelAnalyticsService $funnelAnalyticsService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_name' => ['required', 'string'],
            'properties' => ['nullable', 'array'],
            'context' => ['nullable', 'array'],
            'context.path' => ['nullable', 'string', 'max:255'],
        ]);

        $eventName = (string) $validated['event_name'];

        if (! $this->funnelAnalyticsService->isAllowedEvent($eventName)) {
            return response()->json([
                'message' => 'Event is not allowed.',
            ], 422);
        }

        $this->funnelAnalyticsService->trackFromRequest(
            $request,
            $eventName,
            (array) ($validated['properties'] ?? [])
        );

        return response()->json(['accepted' => true], 202);
    }
}
