<?php

namespace App\Http\Controllers;

use App\Models\UserEntitlement;
use App\Models\TrialRedemption;
use App\Services\SubscriptionFeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionFeatureService $subscriptionFeatureService,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $features = $this->subscriptionFeatureService->forUser($user);

        return Inertia::render('Billing/Index', [
            'plans' => config('stripe.plans'),
            'trialDays' => (int) config('stripe.trial_days', 7),
            'subscriptionFeatures' => $features,
            'entitlement' => $user?->entitlement,
        ]);
    }

    public function startTrial(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $user->discord_id) {
            return back()->withErrors([
                'billing' => 'Trial requires a Discord-linked account.',
            ]);
        }

        $existingRedemption = TrialRedemption::query()
            ->where('discord_id', $user->discord_id)
            ->first();

        if ($existingRedemption) {
            return back()->withErrors([
                'billing' => 'Trial was already used for this Discord account.',
            ]);
        }

        $data = $request->validate([
            'tier' => ['required', 'string', 'in:vip,mvp'],
        ]);

        $entitlement = UserEntitlement::query()->firstOrNew(['user_id' => $user->id]);

        $trialDays = max(1, (int) config('stripe.trial_days', 7));
        $trialEndsAt = now()->addDays($trialDays);

        $entitlement->fill([
            'tier' => $data['tier'],
            'status' => 'trialing',
            'dashboard_slots_unlocked' => 3,
            'provider' => 'stripe',
            'trial_started_at' => now(),
            'trial_ends_at' => $trialEndsAt,
            'current_period_ends_at' => $trialEndsAt,
        ]);

        $entitlement->save();

        TrialRedemption::query()->create([
            'discord_id' => $user->discord_id,
            'first_user_id' => $user->id,
            'tier' => $data['tier'],
            'redeemed_at' => now(),
        ]);

        return back()->with('success', 'Trial activated.');
    }

    public function checkout(Request $request): HttpResponse
    {
        $user = $request->user();

        if (! $user || ! $user->discord_id) {
            return back()->withErrors([
                'billing' => 'Paid subscriptions require a Discord-linked account.',
            ]);
        }

        $data = $request->validate([
            'tier' => ['required', 'string', 'in:vip,mvp'],
        ]);

        $stripeSecret = (string) config('stripe.secret');
        if ($stripeSecret === '') {
            return back()->withErrors([
                'billing' => 'Stripe is not configured yet. Add STRIPE_SECRET first.',
            ]);
        }

        $priceId = (string) config("stripe.plans.{$data['tier']}.price_id");
        if ($priceId === '') {
            return back()->withErrors([
                'billing' => 'Stripe price ID is missing for selected tier.',
            ]);
        }

        $entitlement = UserEntitlement::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'provider' => 'stripe',
                'status' => 'inactive',
                'tier' => 'free',
                'dashboard_slots_unlocked' => 1,
            ]
        );

        try {
            $stripe = new StripeClient($stripeSecret);
            $resolvedPriceId = $this->resolvePriceId($stripe, $priceId);

            if (! $resolvedPriceId) {
                return back()->withErrors([
                    'billing' => 'Stripe plan is misconfigured. Use valid price_ or product with default price.',
                ]);
            }

            if (! $entitlement->stripe_customer_id) {
                $customer = $stripe->customers->create([
                    'email' => $user->email,
                    'name' => $user->name,
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'discord_id' => (string) $user->discord_id,
                    ],
                ]);

                $entitlement->stripe_customer_id = $customer->id;
                $entitlement->save();
            }

            $features = $this->subscriptionFeatureService->forUser($user);
            $trialDays = max(1, (int) config('stripe.trial_days', 7));
            $shouldApplyTrial = (bool) ($features['trial_eligible'] ?? false) && $trialDays > 0;

            $payload = [
                'mode' => 'subscription',
                'customer' => $entitlement->stripe_customer_id,
                'line_items' => [
                    [
                        'price' => $resolvedPriceId,
                        'quantity' => 1,
                    ],
                ],
                'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing'),
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'tier' => $data['tier'],
                ],
                'subscription_data' => [
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'tier' => $data['tier'],
                    ],
                ],
            ];

            if ($shouldApplyTrial) {
                $payload['subscription_data']['trial_period_days'] = $trialDays;
            }

            $session = $stripe->checkout->sessions->create($payload);

            if ($request->header('X-Inertia')) {
                return Inertia::location($session->url);
            }

            return redirect()->away($session->url);
        } catch (ApiErrorException $e) {
            report($e);

            return back()->withErrors([
                'billing' => 'Failed to create Stripe checkout session.',
            ]);
        }
    }

    public function success(): RedirectResponse
    {
        return redirect()->route('billing')->with('success', 'Checkout completed. Subscription will sync shortly.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();
        $entitlement = $user?->entitlement;

        if (! $entitlement) {
            return back()->withErrors([
                'billing' => 'No active subscription found for this account.',
            ]);
        }

        $stripeSecret = (string) config('stripe.secret');

        if ($stripeSecret !== '' && $entitlement->stripe_subscription_id) {
            try {
                $stripe = new StripeClient($stripeSecret);
                $stripe->subscriptions->cancel($entitlement->stripe_subscription_id, []);
            } catch (ApiErrorException $e) {
                report($e);
            }
        }

        $entitlement->update([
            'status' => 'inactive',
            'tier' => 'free',
            'dashboard_slots_unlocked' => 1,
            'current_period_ends_at' => null,
            'stripe_subscription_id' => null,
            'stripe_price_id' => null,
        ]);

        return back()->with('status', 'Subscription was cancelled.');
    }

    public function devToggleSubscription(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return back()->withErrors([
                'billing' => 'User not found.',
            ]);
        }

        $features = $this->subscriptionFeatureService->forUser($user);
        $hasSubscription = (bool) ($features['has_active_entitlement'] ?? false);

        $entitlement = UserEntitlement::query()->firstOrNew(['user_id' => $user->id]);

        if ($hasSubscription) {
            $entitlement->fill([
                'tier' => 'free',
                'status' => 'inactive',
                'dashboard_slots_unlocked' => 1,
                'provider' => $entitlement->provider ?: 'dev',
                'current_period_ends_at' => null,
                'trial_started_at' => null,
                'trial_ends_at' => null,
            ]);
        } else {
            $entitlement->fill([
                'tier' => 'vip',
                'status' => 'active',
                'dashboard_slots_unlocked' => 3,
                'provider' => 'dev',
                'current_period_ends_at' => now()->addYears(10),
                'trial_started_at' => null,
                'trial_ends_at' => null,
            ]);
        }

        $entitlement->save();

        return back()->with('status', $hasSubscription ? 'Dev subscription disabled.' : 'Dev subscription enabled (VIP).');
    }

    private function resolvePriceId(StripeClient $stripe, string $identifier): ?string
    {
        if ($identifier === '') {
            return null;
        }

        if (str_starts_with($identifier, 'price_')) {
            return $identifier;
        }

        if (str_starts_with($identifier, 'prod_')) {
            try {
                $product = $stripe->products->retrieve($identifier, []);
                $defaultPrice = $product->default_price ?? null;

                if (is_string($defaultPrice) && str_starts_with($defaultPrice, 'price_')) {
                    return $defaultPrice;
                }

                if (is_object($defaultPrice) && isset($defaultPrice->id) && str_starts_with((string) $defaultPrice->id, 'price_')) {
                    return (string) $defaultPrice->id;
                }
            } catch (ApiErrorException $e) {
                report($e);
            }
        }

        return null;
    }
}
