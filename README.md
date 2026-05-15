# SkyblockHub

[![Release](https://img.shields.io/github/v/release/Lokkisanek/SkyblockHub.play?label=release)](https://github.com/Lokkisanek/SkyblockHub.play/releases)
[![Last commit](https://img.shields.io/github/last-commit/Lokkisanek/SkyblockHub.play)](https://github.com/Lokkisanek/SkyblockHub.play)

Web app for [Hypixel SkyBlock](https://hypixel.net/) — bazaar prices, profile stats, leaderboards, flips, party finder, and more. Built with Laravel and Vue.

**Live site:** [skyblockhub.app](https://skyblockhub.app)

![SkyblockHub interface](public/img/screenshot.png)

---

## What it does

SkyblockHub pulls public Hypixel SkyBlock data and presents it in one place so you spend less time tabbing between tools.

| Area | What you get |
|------|----------------|
| **Bazaar** | Live prices and charts (WebSockets) |
| **Profile stats** | Skills, slayers, networth, gear — search any player |
| **Leaderboards** | Community rankings (level, networth, skills, and more) |
| **Flips** | NPC flips, crafting arbitrage, BIN sniper alerts |
| **Party finder** | Dungeon party listings |
| **Portfolio** | Track buys/sells and performance (signed-in users) |
| **Account** | Discord login, optional VIP/MVP via Stripe |

> **Note:** SkyblockHub is not affiliated with Hypixel or Mojang. You need your own [Hypixel API key](https://developer.hypixel.net/) to run a self-hosted instance.

---

## Tech stack

- **Backend:** Laravel 11, PHP 8.3+
- **Frontend:** Vue 3, Inertia.js, Tailwind CSS, Vite
- **Realtime:** Laravel Reverb
- **Networth (full profiles):** Node.js + [skyhelper-networth](https://www.npmjs.com/package/skyhelper-networth)
- **Database:** SQLite by default (MySQL/PostgreSQL supported)

---

## Requirements

- PHP 8.3+ with common extensions (`mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- Composer 2
- Node.js 20+ and npm
- Hypixel API key (for profile/bazaar features)

---

## Quick start (local)

```bash
git clone https://github.com/Lokkisanek/SkyblockHub.play.git
cd SkyblockHub.play

composer install
npm install

cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # if using SQLite
php artisan migrate
```

Set at least these in `.env`:

```dotenv
HYPIXEL_API_KEY=your_hypixel_api_key

# Discord OAuth (login)
DISCORD_CLIENT_ID=
DISCORD_CLIENT_SECRET=
DISCORD_REDIRECT_URI=http://127.0.0.1:8000/auth/discord/callback
```

Build frontend assets and run the app:

```bash
npm run build
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

### Development (all services)

Laravel’s dev script starts the HTTP server, queue worker, logs, and Vite:

```bash
composer dev
```

For realtime bazaar updates, also run Reverb in another terminal:

```bash
php artisan reverb:start
```

---

## Configuration

Copy `.env.example` to `.env` and adjust. Common groups:

| Group | Purpose |
|-------|---------|
| `APP_*` | App URL, environment, locale |
| `DB_*` | Database connection |
| `HYPIXEL_API_KEY` | Hypixel API (required for stats/bazaar) |
| `HYPIXEL_RATE_LIMIT` | Outbound API cap (default stays below Hypixel limits) |
| `DISCORD_*` | OAuth login |
| `REVERB_*` / `VITE_REVERB_*` | WebSockets for live bazaar |
| `STRIPE_*` | Optional VIP/MVP subscriptions |
| `PROFILE_INGEST_*` | Background profile refresh for leaderboards |
| `SENTRY_*` | Optional error tracking |

See `.env.example` for the full list and comments.

### Stripe (production)

1. Create monthly prices for VIP and MVP in the Stripe Dashboard.
2. Set `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_PRICE_VIP_MONTHLY`, `STRIPE_PRICE_MVP_MONTHLY`.
3. Webhook URL: `https://your-domain.com/api/stripe/webhook`
4. Events: `checkout.session.completed`, `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`

Trial logic is handled in the app, not in Stripe.

---

## Production

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- Point the web server document root to `public/`
- Run a queue worker: `php artisan queue:work`
- Run Reverb (or your WebSocket proxy): `php artisan reverb:start`
- Use a process manager (systemd, Supervisor) for long-running processes
- Schedule cron: `* * * * * php /path/to/artisan schedule:run`

### Leaderboard data (optional)

The leaderboard is built from cached SkyBlock profiles. To grow it on your own instance:

```bash
# Discover guild members and ingest new players (lightweight, leaderboard-focused)
php artisan profiles:crawl-guilds --guild="Your Guild Name" --member-limit=50 --new-only --delay-ms=2000

# Rebuild the flat leaderboard table
php artisan leaderboard:rebuild-snapshot
```

Bulk ingest and scheduled refresh are documented in `config/hypixel.php` and `.env.example` (`PROFILE_INGEST_*`).

---

## Tests

```bash
php artisan test
```

---

## Contributing

Issues and pull requests are welcome. For larger changes, open an issue first so we can align on approach.

1. Fork the repo
2. Create a branch (`git checkout -b feature/my-change`)
3. Commit and push
4. Open a pull request

Please run `php artisan test` and keep the diff focused.

---

## License

This project is open-source software licensed under the [MIT License](https://opensource.org/licenses/MIT).
