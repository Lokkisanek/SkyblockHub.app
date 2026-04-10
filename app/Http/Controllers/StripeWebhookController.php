<?php

namespace App\Http\Controllers;

use App\Models\UserEntitlement;
use App\Services\SubscriptionFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionFeatureService $subscriptionFeatureService,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        $webhookSecret = (string) config('stripe.webhook_secret');
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        if ($webhookSecret === '') {
            return response()->json(['message' => 'Webhook secret not configured.'], 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            report($e);

            return response()->json(['message' => 'Invalid signature.'], 400);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        $type = (string) ($event->type ?? '');
        $object = $event->data->object ?? null;

        switch ($type) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($object);
                break;
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $this->syncFromSubscriptionObject($object, null, null);
                break;
            default:
                break;
        }

        return response()->json(['received' => true]);
    }

    private function handleCheckoutCompleted(object $session): void
    {
        if (($session->mode ?? null) !== 'subscription') {
            return;
        }

        $userId = is_numeric($session->metadata->user_id ?? null) ? (int) $session->metadata->user_id : null;
        $tier = $this->subscriptionFeatureService->normalizeTier((string) ($session->metadata->tier ?? 'free'));
        $subscriptionId = (string) ($session->subscription ?? '');

        if ($subscriptionId === '') {
            return;
        }

        $stripeSecret = (string) config('stripe.secret');
        if ($stripeSecret === '') {
            return;
        }

        $stripe = new StripeClient($stripeSecret);
        $subscription = $stripe->subscriptions->retrieve($subscriptionId, []);

        $this->syncFromSubscriptionObject($subscription, $userId, $tier);
    }

    private function syncFromSubscriptionObject(object $subscription, ?int $userId, ?string $fallbackTier): void
    {
        $subscriptionId = (string) ($subscription->id ?? '');
        $customerId = (string) ($subscription->customer ?? '');
        $status = (string) ($subscription->status ?? 'inactive');
        $priceId = (string) ($subscription->items->data[0]->price->id ?? '');
        $productId = (string) ($subscription->items->data[0]->price->product ?? '');
        $tier = $this->tierFromPlanIdentifiers($priceId, $productId) ?? $fallbackTier ?? 'free';

        $isUsable = in_array($status, ['active', 'trialing'], true);
        $dashboardSlots = in_array($tier, ['vip', 'mvp'], true) && $isUsable ? 3 : 1;

        $periodEndTs = is_numeric($subscription->current_period_end ?? null) ? (int) $subscription->current_period_end : null;
        $trialStartTs = is_numeric($subscription->trial_start ?? null) ? (int) $subscription->trial_start : null;
        $trialEndTs = is_numeric($subscription->trial_end ?? null) ? (int) $subscription->trial_end : null;

        $entitlement = null;

        if ($userId) {
            $entitlement = UserEntitlement::query()->firstOrNew(['user_id' => $userId]);
        }

        if (! $entitlement && $subscriptionId !== '') {
            $entitlement = UserEntitlement::query()->where('stripe_subscription_id', $subscriptionId)->first();
        }

        if (! $entitlement && $customerId !== '') {
            $entitlement = UserEntitlement::query()->where('stripe_customer_id', $customerId)->first();
        }

        if (! $entitlement) {
            return;
        }

        if ($userId && ! $entitlement->user_id) {
            $entitlement->user_id = $userId;
        }

        $entitlement->provider = 'stripe';
        $entitlement->status = $isUsable ? $status : 'inactive';
        $entitlement->tier = $isUsable ? $tier : 'free';
        $entitlement->dashboard_slots_unlocked = $dashboardSlots;
        $entitlement->stripe_customer_id = $customerId !== '' ? $customerId : $entitlement->stripe_customer_id;
        $entitlement->stripe_subscription_id = $subscriptionId !== '' ? $subscriptionId : $entitlement->stripe_subscription_id;
        $entitlement->stripe_price_id = $priceId !== '' ? $priceId : $entitlement->stripe_price_id;
        $entitlement->current_period_ends_at = $periodEndTs ? now()->setTimestamp($periodEndTs) : null;

        if ($status === 'trialing') {
            $entitlement->trial_started_at = $trialStartTs ? now()->setTimestamp($trialStartTs) : ($entitlement->trial_started_at ?? now());
            $entitlement->trial_ends_at = $trialEndTs ? now()->setTimestamp($trialEndTs) : $entitlement->trial_ends_at;
        }

        $entitlement->save();
    }

    private function tierFromPlanIdentifiers(string $priceId, string $productId): ?string
    {
        if ($priceId === '' && $productId === '') {
            return null;
        }

        foreach ((array) config('stripe.plans', []) as $tier => $plan) {
            $configured = (string) ($plan['price_id'] ?? '');
            if ($configured === '') {
                continue;
            }

            if ($configured === $priceId || $configured === $productId) {
                return $this->subscriptionFeatureService->normalizeTier((string) $tier);
            }
        }

        return null;
    }
}
