# SkyblockHub

SkyblockHub je moderní a svižný nástroj pro hráče Hypixel Skyblock, který šetří čas a peníze. S námi snadno:

- Sledujete ceny itemů na Bazaaru v reálném čase
- Spravujete a analyzujete svoje investice v `Portfolio`
- Najdete vhodné hráče do `Dungeon Party` během pár kliků
- Využíváte crafting arbitráže k rychlému zisku
- Dostáváte upozornění na výhodné BIN aukce pomocí `BIN Sniper`

SkyblockHub kombinuje silný backend postavený na Laravelu s moderním Vue/Inertia frontendem — výsledkem je rychlá, responzivní aplikace ideální pro aktivní hráče i obchodníky.

**Hlavní výhody**

- Realtime data přes WebSockets pro okamžité aktualizace
- Snadné nastavení a lokální běh (SQLite výchozí)
- Rozšiřitelná architektura — jednoduché přidání nových alertů a metrik

## Co v projektu najdete

- Sledování Bazaaru (`/bazaar`) s historickými cenami
- `Portfolio` pro správu vašich nákupů a investic
- `Crafting` nástroj pro zjištění profitabilních receptů
- `BIN Sniper` s notifikacemi
- `Dungeon Party` matchmaking

---

## Tech stack

- Backend: Laravel 11 (PHP 8.2+)
- Frontend: Vue 3 + Inertia.js + Tailwind CSS
- Realtime: Laravel Reverb (WebSockets)
- Databáze: SQLite (výchozí), lze přepnout na MySQL/Postgres
- Build: Vite

---

## Požadavky

- PHP 8.2+ s extensions: `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`
- Composer
- Node.js 18+ + npm

---

## Lokální spuštění (rychlé kroky)

1) Naklonujte repozitář a otevřete složku projektu

```bash
git clone <url-repozitare>
cd SkyblockHub.play
```

2) Nainstalujte backend závislosti

```bash
composer install
```

3) Vytvořte `.env` a vygenerujte klíč aplikace

```bash
cp .env.example .env
php artisan key:generate
```

4) (Volitelné) Spusťte migrace a seedy

```bash
php artisan migrate --seed
```

5) Nainstalujte frontend závislosti a spusťte development server

```bash
npm install
npm run dev
```

6) Spusťte aplikaci a volitelné služby ve vodiči shellu

```bash
# Web server
php artisan serve --host=127.0.0.1 --port=8000

# Reverb (WebSockets)
php artisan reverb:start

# Queue worker
php artisan queue:work
```

Aplikace bude dostupná na http://localhost:8000

---

## Poznámky pro produkci

- Spusťte `npm run build` pro produkční assets
- Nasazení směrujte na složku `public/` a nakonfigurujte env proměnné
- Pro dlouhodobý běh `queue:work` a `reverb:start` použijte process manager (např. systemd nebo supervisor)

---

Pokud chcete, mohu README ještě upravit, přidat screenshoty, badge nebo krátký PowerShell/Bash skript pro automatické nastavení.

