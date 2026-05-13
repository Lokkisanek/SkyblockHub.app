# Hypixel API — žádost (limit 2000 znaků)

Kompletní text k vložení je v souboru **`docs/hypixel-application-2000.txt`** (~1334 znaků, ASCII). Otevři ho a zkopíruj celý obsah do formuláře.

---

## Obsah (stejné jako v .txt)

Project: SkyblockHub | Site: https://skyblockhub.app/ | Source: https://github.com/Lokkisanek/SkyblockHub.play | Applicant MC: Lokkisanecek

SkyblockHub: Hypixel SkyBlock tools—Bazaar/NPC analytics, read-only profile stats, leaderboards from cached SkyBlock data, event timers. Hypixel API key used only on our Laravel server (never in browser). Read-only public API v2: profiles, player, museum, bazaar, auctions, election, collections/items, leaderboards as needed. We do not modify accounts.

Traffic: self-capped 120 Hypixel requests/min (below 300/min limit). Optional scheduled profile ingest refreshes a capped batch (~100 UUIDs/run by default) with ~500ms spacing; ~2 API calls per UUID (profiles+player), not a tight loop.

Caching: central proxy with TTLs (seconds)—profiles 300, player 600, museum 300, bazaar 60, auctions 120, election 120, collections 86400, items 86400, leaderboards 900. On 429/5xx/timeout we may serve stale cache up to 1800s past TTL; limited retries with backoff.

Ownership: I deploy skyblockhub.app; public GitHub matches the live app. Info: https://skyblockhub.app/hypixel-developer-verification If Hypixel provides a domain verification token (meta tag, file, or DNS), I will publish it within 24h; our deployment supports env-based verification (meta on pages + /hypixel-verification.txt).
