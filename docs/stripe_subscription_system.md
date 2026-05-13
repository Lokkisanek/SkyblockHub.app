# Stripe Subscription System

## Goals
- Keep Free tier useful and sticky.
- Monetize premium depth with VIP and MVP plans.
- Require Discord-linked account for paid checkout and trial.

## Tiers

### Free
- Flips filters unlocked.
- Alert channels unlocked (web + Discord integration design kept free).
- Top flips: limited to 1.
- Refresh interval: 180s.
- Dashboard slots: 1.

### VIP ($4.99/month)
- Top flips: 3.
- Leaderboard tag: VIP.
- Faster refresh interval: 120s.
- Priority widget updates: enabled.
- Dashboard slots: 3.

### MVP ($8.99/month)
- Includes VIP features.
- Leaderboard tag: MVP.
- Faster refresh interval: 60s.
- AI-controlled flips panel.
- Trust score + risk badge on AI insights.
- Dashboard slots: 3.

## Stripe Environment Variables
Set these in `.env`:

- `STRIPE_KEY`
- `STRIPE_SECRET`
- `STRIPE_WEBHOOK_SECRET`
- `STRIPE_PRICE_VIP_MONTHLY`
- `STRIPE_PRICE_MVP_MONTHLY`

## Main Backend Pieces

- `app/Services/SubscriptionFeatureService.php`
  - Resolves usable entitlement and feature flags.
  - Provides tier-specific behavior for top flips, refresh speed, AI access, leaderboard tag, and dashboard slots.

- `app/Http/Controllers/BillingController.php`
  - Billing page render.
  - Stripe checkout and subscription cancellation.
  - Stripe Checkout session creation.
  - Enforces Discord-linked account for paid and trial flows.

- `app/Http/Controllers/StripeWebhookController.php`
  - Verifies Stripe webhook signatures.
  - Synchronizes local entitlements from subscription lifecycle events.

- `app/Http/Controllers/LeaderboardsController.php`
  - Produces leaderboard rows with VIP/MVP tags.

## Main Frontend Pieces

- `resources/js/Pages/Billing/Index.vue`
  - VIP/MVP pricing cards.
  - Trial and Subscribe actions.

- `resources/js/Pages/Bazaar/Index.vue`
  - Tier-gated Top Flips section.
  - Tier-based auto-refresh timer.
  - MVP-only AI trust-score panel.

- `resources/js/Pages/Dashboard.vue`
  - Priority widget updates status chip.
  - Upgrade CTA to Billing.

- `resources/js/Pages/Leaderboards/Index.vue`
  - Rank table with FREE/VIP/MVP tags.

## Routes

### Web
- `GET /billing`
- `POST /billing/trial`
- `POST /billing/checkout`
- `GET /billing/success`
- `GET /leaderboards`

### API
- `POST /api/stripe/webhook`

## Data Model Updates

`user_entitlements` now includes:
- `tier`
- `trial_started_at`
- `trial_ends_at`
- `stripe_price_id`

## Webhook Notes
- Configure Stripe endpoint to: `/api/stripe/webhook`
- Use signing secret in `STRIPE_WEBHOOK_SECRET`.
- Relevant events currently handled:
  - `checkout.session.completed`
  - `customer.subscription.created`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`

## What Still Needs Product Tuning
- Alert rule engine and advanced anti-spam controls can be implemented as a dedicated module.
- AI trust score can evolve to include volatility windows and anomaly detection weights.
- Add user-facing billing history and cancellation controls.
