# SkyblockHub

[![Release](https://img.shields.io/github/v/release/Lokkisanek/SkyblockHub.play?label=release)](https://github.com/Lokkisanek/SkyblockHub.play/releases)
[![Last commit](https://img.shields.io/github/last-commit/Lokkisanek/SkyblockHub.play)](https://github.com/Lokkisanek/SkyblockHub.play)

**A modern web app for Hypixel SkyBlock** — stats, bazaar, leaderboards, and trading tools in one place.

🌐 **[skyblockhub.app](https://skyblockhub.app)**

![SkyblockHub](public/img/screenshot.png)

---

## About

SkyblockHub is a fan-made project for Hypixel SkyBlock players. The goal is to replace a dozen browser tabs with one fast interface: bazaar prices, player profiles, rankings, flips, and a party finder.

It is **not** an official Hypixel product. Data comes from public APIs and from profiles the community looks up and links on the site.

---

## What you get

| | |
|---|---|
| **Bazaar** | Live prices and trends (including realtime updates) |
| **Profile Stats** | Skills, slayers, networth, gear — search any player |
| **Leaderboards** | Community rankings (level, networth, skills, slayer XP, and more) |
| **NPC & crafting flips** | Help finding profitable trades |
| **BIN Sniper** | Alerts for underpriced auctions |
| **Dungeon Party Finder** | Find dungeon groups faster |
| **Portfolio** | Track buys and sells (when signed in) |
| **Account** | Discord login, optional VIP / MVP tiers |

---

## Why SkyblockHub

- **One place** instead of juggling several external sites  
- **Fast UI** — Vue + Inertia, clean layout  
- **Community leaderboard** — grows as more profiles are fetched and cached  
- **Open source** — inspect how it works or contribute

---

## Built with

Laravel · Vue 3 · Inertia.js · Tailwind CSS · Laravel Reverb · Vite  

Full profile networth uses [skyhelper-networth](https://www.npmjs.com/package/skyhelper-networth).

---

## Data sources

| Source | Used for |
|--------|----------|
| [Hypixel Public API](https://api.hypixel.net/) | Profiles, bazaar, guilds, player data |
| App cache & ingest | Leaderboards, periodic profile refresh |
| Community | Player searches on the site expand the profile database |

SkyblockHub is **not** approved, sponsored, or affiliated with Hypixel Inc. or Mojang / Microsoft.

---

## Self-hosting

This repo includes the full application (backend and frontend). For your own instance, see `.env.example` — we keep setup details out of the README on purpose; options are documented in the env file comments.

---

## Contributing

Ideas, bug reports, and pull requests are welcome.

1. [Open an issue](https://github.com/Lokkisanek/SkyblockHub.play/issues) or submit a PR  
2. For larger changes, briefly describe the plan in an issue first  
3. Keep each PR focused on one topic  

A star on GitHub helps the project get noticed — thanks if you find SkyblockHub useful.

---

## License

[MIT](https://opensource.org/licenses/MIT)
