<?php

namespace App\Http\Controllers;

use App\Services\OnboardingChecklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingChecklistService $onboardingChecklistService,
    ) {
    }

    public function completeStep(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'step' => ['required', 'string'],
        ]);

        $user = $request->user();
        if ($user) {
            $this->onboardingChecklistService->markStep($user, (string) $data['step']);
        }

        return back(status: 303);
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $this->onboardingChecklistService->dismiss($user);
        }

        return back(status: 303);
    }
}
