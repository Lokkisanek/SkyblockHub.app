# SkyblockHub.play - Product Opinion (Audit)

Datum: 2026-04-11 (rev 2)
Scope: Produktovy a UX audit — aktualizovano po implementaci landing redesignu, GDPR, event timer notifikaci, textur a dalsich uprav.

---

## COMPLETED (od posledniho auditu)

### P0 — Landing + messaging
- [x] Landing rewrite — silny hero ("Make better SkyBlock decisions faster"), konkretni benefity, zero-placeholder.
- [x] 5 feature karet s emoji ikonami (Bazaar, NPC Arb, Profile Stats, Event Timer, Mayor Intel).
- [x] Discord CTA pro hosta / "Open Dashboard" pro prihlaseneho.
- [x] Pricing cards primo na landing (VIP $4.99, MVP $8.99 s 7-day free trial badge).
- [x] Footer s linky na moduly, GitHub, Patreon, legal, dev contact.
- [x] SEO meta tagy, OG tagy, canonical URL.
- [x] `canLogin` prop fix — guest CTA se ukazuje spravne.

### GDPR & compliance
- [x] CookieConsent popup (bottom-right) s "Allow all" / "Only essential".
- [x] DB audit trail (cookie_consents tabulka s user_id, session_id, ip, user_agent, consented_at).
- [x] Privacy Policy, Terms of Service — kompletni, GDPR-compliant.

### Event Timer notifikace
- [x] Service Worker registrace + `showNotification` cesta (opraveno z broken `postMessage`).
- [x] Konfirmacni notifikace pri zapnuti (immediate feedback).
- [x] In-app flash text ("✓ You'll be notified 5 min before this event").
- [x] Denied-permission flash message.
- [x] Custom ikony per event (Coflnet textury + lokalni fallback).
- [x] Badge (favicon.ico).
- [x] `visibilitychange` listener pro background-tab reliability.
- [x] Rozsirene okno detekce (20s → 60s).
- [x] `saveNotifyPrefs` error handling (try-catch).

### Textury
- [x] Prepnuti ze `sky.shiiyu.moe` na `sky.coflnet.com/static/icon/` (Bazaar, NPC Flips, Crafting).
- [x] Lepsi pokryti SkyBlock itemu.

### UX drobnosti
- [x] Survey popup (Google Forms, localStorage dismiss).
- [x] Logo v headeru vede na landing (`/`).
- [x] Pricing & FAQ stranka (`/pricing`) — comparison table (Free vs VIP vs MVP), 8 FAQ vcetne refund policy, trial pravidel, cancel procesu.
- [x] Linky na Pricing & FAQ ve footeru landingu, pod pricing cards na landingu, a na Billing strance.

---

## Executive Summary (rev 2)

SkyblockHub se od posledniho auditu vyrazne posunul v prvnim dojmu a complianci. Landing page je nyni profesionalni s jasnym value proposition, CTA flow a pricing. GDPR je pokryte. Event Timer notifikace funguji end-to-end vcetne custom ikon.

Aktualni odhad pripravenosti: **7.8/10** (z 6.5).

Nejvetsi zbyvajici mezery:
- Leaderboards jsou prilis zakladni (zadne filtry, trendy, seasons).
- Chybi onboarding flow pro nove uzivatele.
- Funnel analytika neexistuje.
- Admin-only features (BinSniper, Portfolio, DungeonParty) jsou viditelne v kodu bez feature-flag guardingu.

---

## Co je uz dobre

### Jadro
- Stabilni stack (Laravel + Vue/Inertia + testy).
- Funkcni Stripe test-mode flow (checkout + webhook + entitlement sync + trial anti-abuse).
- Subscription feature gating centralizovane.
- Dashboard s drag-drop, widgety, undo/redo, sloty, public/private.

### Landing & prvni dojem
- Profesionalni hero s jasnym slibem.
- Player search bar primo na landingu.
- Feature karty s konkretnimi popisy.
- Pricing karty s trial badge.
- Discord OAuth bez friction.
- SEO + OG tagy.

### Core features (production-ready)
- Bazaar flips — real-time tabulka, top 3 gated, trend indikatory, kopie /bz prikazy.
- NPC Flips — Best Pick Engine, tax-aware, stackability, compactor toggle.
- Profile Stats — SkyCrypt-level browser se vsemi taby (Gear, Inventory, Pets, Skills, Dungeons, Slayer, Collections).
- Event Timer — SkyBlock casovy system, kalendar, notifikace s custom ikonami.
- Crafting Arbitrage — receptury, real-time ceny pres WebSocket/polling.
- Mayors — historicky browser s perky a skiny.

### Compliance & trust
- GDPR cookie consent s DB audit trail.
- Privacy Policy + Terms of Service.
- Open-source transparence (GitHub link).
- About page s pribeh vyvojare.

---

## Zbyvajici problemy (prioritizovane)

### P0 — Kriticke pro publikaci
1. **Admin features bez feature-flag** — BinSniper, Portfolio, DungeonParty jsou v routes a navigaci, ale nejsou hotove pro public. Potreba skryt nebo oznacit jako beta/coming soon.
2. **Profil settings jazykovy mix** — casti v cestine ("Minecraft účet", "Propojeno") v jinak anglicke aplikaci.

### P1 — Vyrazne brzdy rustu
1. **Leaderboards jsou zakladni** — zadne casove filtry (daily/weekly/monthly), zadny personal rank, zadne movement arrows, zadne seasons/badges.
2. **Chybi onboarding** — novy uzivatel po loginu nevi kam jit. Zadny checklist, tutorial, nebo first-session guidance.
3. **Chybi social proof na landingu** — zadne cisla (pocet uzivatelu, flipu, atd.), zadne screenshoty use-case, zadne testimonials.
4. **Contextual upgrade prompts** — upgrade momenty jsou jen na Billing strance, chybi in-feature "unlock this with VIP" prompts.

### P2 — Data a optimalizace
1. **Funnel analytika neexistuje** — zadne event tracking (landing_cta_click, billing_view, trial_start, checkout_success, cancel).
2. **A/B testovani** — zadna infrastruktura pro experimenty.
3. **Mobile optimalizace** — tabulky (Bazaar, NPC) mohou byt stesnane na mobilu.

---

## Co je potreba dodelat pro "production-ready" publikaci

### 1) Feature-flag admin features (P0)
- BinSniper, Portfolio, DungeonParty skryt z navigace pro non-admin uzivatele.
- Nebo oznacit jako "Coming Soon" s disabled statem.

### 2) Jazykova konzistence (P0)
- Profile settings prelozit do anglictiny (nebo implementovat plny i18n).

### 3) Leaderboards upgrade (P1)
- Casove filtry (daily/weekly/monthly/all-time).
- Personal rank card + movement indicators.
- Sezony a odmeny (badges).
- Sdilitelny rank snapshot.

### 4) Onboarding flow (P1)
- In-app checklist po prvnim loginu:
  1. Propoj Minecraft ucet.
  2. Otevri dashboard.
  3. Aktivuj prvni layout/widget.
  4. Vyzkousej premium feature.
- Tooltip tour pro klicove stranky.

### 5) Social proof (P1)
- Pridej na landing: pocet registrovanych uzivatelu, pocet sledovanych flipu, pocet nactenych profilu.
- Screenshot/GIF ukazka Bazaar flipu v akci.

### 6) Funnel tracking (P2)
- Instrumentace eventu (landing_cta_click, billing_view, trial_start, checkout_start, checkout_success, cancel).
- Weekly review dashboard nebo export.

---

## Scoring

### Uspesnost implementace (co jsme udelali vs. co bylo v planu)
**8.5/10**
- P0 landing: HOTOVO (100 %)
- Billing FAQ/comparison: HOTOVO (100 %)
- GDPR: HOTOVO (nebylo v planu, bonus)
- Event Timer fix + custom notifikace: HOTOVO (nebylo v planu, bonus)
- Textury: HOTOVO (nebylo v planu, bonus)
- Billing FAQ/comparison: NEUDELANO (0 %)
- Leaderboard upgrade: NEUDELANO (0 %)
- Onboarding: NEUDELANO (0 %)
- Funnel tracking: NEUDELANO (0 %)

### Publikovatelnost (jak moc je to ready pro real users)
**7.5/10**

Co funguje pro publikaci:
+ Landing page je profesionalni a konvertibilni.
+ Core features (Bazaar, NPC, Profiles, Events, Mayors) jsou mature.
+ Billing flow je funkcni a bezpecny.
+ Legal/GDPR je pokryte.
+ Design je konzistentni a moderni.

Co brzdi publikaci:
- Admin features viditelne bez guardu (zmatou uzivatele).
- Profil settings v cestine.
- Zadny onboarding = vyssi churn nových uzivatelu.
- Leaderboards zakladni = slabsi retention.

### Doporuceny plan pro 100% publikovatelnost
1. **Okamzite (1 den)**: Hide admin features, fix CZ texty v settings.
2. **Tento tyden**: Onboarding checklist, social proof na landingu.
3. **Pristi tyden**: Leaderboard upgrade, funnel tracking.

---

## Zaverecne stanovisko (rev 2)

SkyblockHub se posunul z 6.5 na 7.8. Prvni dojem je vyrazne lepsi — landing page je nyni na urovni placeneho SaaS produktu. Compliance je solidni. Core features jsou mature a pripravene na provoz.

Pro plnou publikaci zbyvaji 2 quick-wins (schovat admin features, opravit CZ texty) a 2 vetsi bloky prace (onboarding, leaderboards). Po techto krockach je platforma pripravena na public launch.
