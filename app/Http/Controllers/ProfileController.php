<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\SubscriptionFeatureService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'isMcLinked' => (bool) $user->is_mc_linked,
            'minecraftUsername' => $user->minecraft_username,
            'discordUsername' => $user->discord_username,
            'subscriptionFeatures' => app(SubscriptionFeatureService::class)->forUser($user),
            'paymentStatus' => [
                'tier' => $user->entitlement?->tier ?? 'free',
                'status' => $user->entitlement?->status ?? 'inactive',
                'trialEndsAt' => optional($user->entitlement?->trial_ends_at)?->toDateTimeString(),
                'currentPeriodEndsAt' => optional($user->entitlement?->current_period_ends_at)?->toDateTimeString(),
                'hasSubscription' => (bool) $user->entitlement?->stripe_subscription_id,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->safe()->only(['name']));

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $entitlement = $user->entitlement;
        $stripeSecret = (string) config('stripe.secret');

        if ($entitlement && $stripeSecret !== '' && $entitlement->stripe_subscription_id) {
            try {
                $stripe = new StripeClient($stripeSecret);
                $stripe->subscriptions->cancel($entitlement->stripe_subscription_id, []);
            } catch (ApiErrorException $e) {
                report($e);
            }
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
