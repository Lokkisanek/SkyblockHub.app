# SkyblockHub Instructions

Tento dokument je kompletní runbook pro lokální běh projektu od nuly.
Pouzij ho pri dalsim setupu nebo po cistem klonu repozitare.

## 1. Pozadavky

- macOS/Linux (skript `start-dev.sh`) nebo Windows (`start-dev.ps1`, `start-dev.bat`)
- PHP 8.2+
- Composer
- Node.js + npm
- SQLite (default lokal)

## 2. Prvni setup projektu

V rootu projektu spust:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Pro SQLite lokalni DB:

```bash
mkdir -p database
touch database/database.sqlite
```

## 3. .env minimum

Zkontroluj v `.env` minimalne:

- `APP_URL=http://localhost:8000`
- `DB_CONNECTION=sqlite`
- `DISCORD_CLIENT_ID=...`
- `DISCORD_CLIENT_SECRET=...`
- `DISCORD_REDIRECT_URI=${APP_URL}/auth/discord/callback`

## 4. Stripe test mode konfigurace

Pro test prostredi nastav v `.env`:

- `STRIPE_KEY=pk_test_...`
- `STRIPE_SECRET=sk_test_...`
- `STRIPE_WEBHOOK_SECRET=whsec_...`
- `STRIPE_PRICE_VIP_MONTHLY=price_...` nebo `prod_...`
- `STRIPE_PRICE_MVP_MONTHLY=price_...` nebo `prod_...`
- `STRIPE_TRIAL_DAYS=7`

Poznamka:
- Checkout podporuje jak `price_` ID, tak `prod_` ID. Pokud das `prod_`, backend pouzije default price produktu.

Po kazde zmene `.env` spust:

```bash
php artisan config:clear
```

## 5. Migrace + seed + data priprava

### Bezpecna varianta (prubezne)

```bash
php artisan migrate
php artisan recipes:seed
php artisan bazaar:fetch
php artisan bin:fetch
```

### Cista varianta (reset DB)

```bash
php artisan migrate:fresh
php artisan recipes:seed
php artisan bazaar:fetch
php artisan bin:fetch
```

## 6. Spusteni aplikace

### Varianta A: all-in-one skript (macOS/Linux)

```bash
bash ./start-dev.sh
```

Tento skript:
- zkontroluje dependencies
- pripravi `.env`
- spusti migrace
- seedne recipes
- zavola initial fetch (bazaar/bin)
- spusti Vite, Laravel, Reverb, queue a scheduler

### Varianta B: rucne v oddelenych terminalech

```bash
npm run dev
php artisan serve --host=127.0.0.1 --port=8000
php artisan reverb:start
php artisan queue:work
php artisan schedule:work
```

Aplikace pobězi na:
- `http://127.0.0.1:8000`

## 7. Stripe webhook pres CLI (test)

### 7.1 Stripe CLI bez systemove instalace (lokalni binarka v projektu)

Pokud neni `stripe` prikaz v PATH:

```bash
mkdir -p tools/stripe-cli
cd tools/stripe-cli
curl -L -o stripe.tar.gz https://github.com/stripe/stripe-cli/releases/download/v1.40.3/stripe_1.40.3_mac-os_arm64.tar.gz
tar -xzf stripe.tar.gz
chmod +x stripe
./stripe version
```

### 7.2 Spust listener

Z rootu projektu:

```bash
./tools/stripe-cli/stripe listen --forward-to http://127.0.0.1:8000/api/stripe/webhook --api-key sk_test_...
```

CLI vypise:

- `Your webhook signing secret is whsec_...`

Tuto hodnotu vloz do `.env`:

- `STRIPE_WEBHOOK_SECRET=whsec_...`

a pak:

```bash
php artisan config:clear
```

### 7.3 Otestuj webhook event

```bash
./tools/stripe-cli/stripe trigger customer.subscription.updated --api-key sk_test_...
```

Kdyz je vse OK, listener uvidi doruceni na `/api/stripe/webhook` s HTTP 200.

## 8. Subscription a billing logika (aktualni stav)

- Trial i placene plany vyzaduji Discord-linked ucet.
- Trial je one-time na ucet.
- Tiers:
  - Free: 1 top flip, pomalejsi refresh, 1 dashboard slot
  - VIP: top 3 flips, faster refresh, priority widget updates, leaderboard VIP tag, 3 sloty
  - MVP: vse z VIP + AI trust score panel + MVP tag

Hlavni endpoints:

- `GET /billing`
- `POST /billing/trial`
- `POST /billing/checkout`
- `POST /billing/cancel`
- `POST /api/stripe/webhook`

## 9. Rychly sanity check po setupu

```bash
php artisan route:list | grep -E "billing|leaderboards|stripe/webhook|dashboard"
php artisan test tests/Feature/BillingFeatureTest.php tests/Feature/DashboardFeatureTest.php tests/Feature/ProfileTest.php
```

## 10. Nejcastejsi problemy

### `stripe: command not found` / `stripe login` exit 127
Pouzij lokalni binarku:

```bash
./tools/stripe-cli/stripe version
```

### Homebrew instalace stripe CLI selze na outdated Command Line Tools
Pouzij lokalni binarku dle sekce 7.1 (bez brew).

### Kliknu na Subscribe a nic
Zkontroluj:
- `STRIPE_SECRET` je vyplnen
- plan ID je validni (`price_` nebo `prod_`)
- `php artisan config:clear`
- app bezi na stejnem URL jako `APP_URL`

### Webhook nepropisuje entitlement
Zkontroluj:
- listener bezi
- `STRIPE_WEBHOOK_SECRET` odpovida aktivnimu listeneru
- endpoint `/api/stripe/webhook` vraci 200 v listener logu

## 11. Bezpecnost

- Nikdy necommituj produkcni tajne klice.
- `.env` je lokalni soubor, drzet mimo git.
- Pro produkci pouzit oddelene live Stripe klice a webhook secret.
