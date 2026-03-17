# SkyblockHub

[![Release](https://img.shields.io/github/v/release/Lokkisanek/SkyblockHub.play?label=release)](https://github.com/Lokkisanek/SkyblockHub.play/releases)
[![Last commit](https://img.shields.io/github/last-commit/Lokkisanek/SkyblockHub.play)](https://github.com/Lokkisanek/SkyblockHub.play)
[![Top language](https://img.shields.io/github/languages/top/Lokkisanek/SkyblockHub.play)](https://github.com/Lokkisanek/SkyblockHub.play)

The modern, fast, and extensible tool for Hypixel Skyblock traders. Make smarter decisions, save time, and maximize profits.

## Why SkyblockHub?

- **Real-time Bazaar Prices** — Instant updates via WebSockets
- **Investment Portfolio** — Track your buys, sells, and profit
- **Crafting Arbitrage** — Find the most profitable recipes
- **BIN Sniper** — Smart alerts for underpriced auctions
- **Dungeon Party Matchmaking** — Quick player matching

**In short:** If you trade on Hypixel Skyblock, SkyblockHub saves you clicks and money.

---

## Features

- `/bazaar` — Price tracking, history, and trends
- `Portfolio` — Investment tracking and statistics
- `Crafting` — Discover profitable recipes
- `BIN Sniper` — Alerts for bargain auctions
- `Dungeon Party` — Fast player matchmaking

---

## Screenshot

![SkyblockHub Interface](public/img/screenshot.png)

---

## Tech Stack

- **Backend:** Laravel 11 (PHP 8.2+)
- **Frontend:** Vue 3 + Inertia.js + Tailwind CSS
- **Real-time:** Laravel Reverb (WebSockets)
- **Database:** SQLite (locally), MySQL/Postgres (production)
- **Build Tool:** Vite

---

## Quick Start

### Prerequisites
- PHP 8.2+
- Node.js 18+
- Composer
- npm or yarn

### Installation

1. **Clone the repository**

```bash
git clone https://github.com/Lokkisanek/SkyblockHub.play.git
cd SkyblockHub.play
```

2. **Install dependencies**

```bash
composer install
npm install
```

3. **Setup environment**

```bash
cp .env.example .env
php artisan key:generate
```

4. **Start development servers**

**Windows:**
```bash
./start-dev.bat
```

**macOS / Linux:**
```bash
chmod +x start-dev.sh
./start-dev.sh
```

This will automatically start:
- Vite dev server (http://localhost:5173)
- Laravel server (http://localhost:8000)
- Reverb WebSocket server (ws://localhost:8080)

5. **Open in browser**

Navigate to `http://localhost:8000` and log in via Discord

---

## Configuration

Edit `.env` to configure:

```dotenv
# Database
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Discord OAuth
DISCORD_CLIENT_ID=your_client_id
DISCORD_CLIENT_SECRET=your_client_secret
DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback
```

---

## Development

Available npm scripts:

```bash
npm run dev      # Start Vite dev server
npm run build    # Build for production
```

Available artisan commands:

```bash
php artisan serve              # Start Laravel server
php artisan reverb:start       # Start WebSocket server
php artisan queue:work         # Start queue worker
php artisan migrate            # Run database migrations
```

---

## Production Deployment

1. Build frontend assets:

```bash
npm run build
```

2. Configure your web server to point to the `public/` directory

3. Use a process manager (systemd, supervisor) to keep these running:
   - `php artisan reverb:start` (WebSocket server)
   - `php artisan queue:work` (Job queue)

4. Set up database backups and monitoring

---

## API Overview

### Bazaar Endpoints

- `GET /api/bazaar` — Get all bazaar products
- `GET /api/bazaar/{product_id}` — Get product history
- `GET /api/prices/{product_id}` — Get price trends

### Portfolio Endpoints

- `GET /api/portfolio` — Get user portfolio
- `POST /api/portfolio` — Add portfolio item
- `DELETE /api/portfolio/{item_id}` — Remove item

### Real-time Updates

Connect to WebSocket server for real-time:
- Bazaar price updates
- Portfolio changes
- Market manipulation alerts

---

## Contributing

Found a bug or have a feature idea?

1. Open an [issue](https://github.com/Lokkisanek/SkyblockHub.play/issues)
2. Create a [pull request](https://github.com/Lokkisanek/SkyblockHub.play/pulls)
3. Check the development guidelines

---

## License

This project is open source and available under the [MIT License](LICENSE).

---

## Support

Need help?

- 📖 Check the [documentation](docs/)
- 🐛 Report bugs on [GitHub Issues](https://github.com/Lokkisanek/SkyblockHub.play/issues)
- 💬 Discuss features on [GitHub Discussions](https://github.com/Lokkisanek/SkyblockHub.play/discussions)

---

**Made with ❤️ for Skyblock traders**
