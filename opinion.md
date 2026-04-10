# SkyblockHub.play - Product Opinion (Audit)

Datum: 2026-04-10
Scope: Produktovy a UX audit po implementaci dashboard/billing/leaderboards/Stripe flow.

## Executive Summary
SkyblockHub ma dobry technicky zaklad a funkcni monetizacni backbone, ale zatim neni na urovni "professional gaming platform" z pohledu dojmu, konverzi a retention loopu.

Aktualni odhad pripravenosti: **6.5/10**.

Nejvetsi mezery nejsou v backendu, ale v:
- prvnim dojmu (landing + messaging),
- jasnosti value proposition,
- retention mechanikach (leaderboards/game-like loops),
- analytice monetizacniho funnelu.

## Co je uz dobre
- Stabilni jadro stacku (Laravel + Vue/Inertia + testy).
- Funkcni Stripe test-mode flow (checkout + webhook + entitlement sync).
- Subscription feature gating je centralizovane a udrzitelne.
- Trial anti-abuse je vyreseny perzistentne pres Discord identitu.
- Dashboard UX je po upravach konzistentnejsi (sloty, visibility status, Undo/Reset, stavy widgetu).
- Platby/cancel jsou dostupne ve Settings, ne v globalni navigaci.

## Hlavni problemy (prioritizovane)

### P0 - Kriticke pro dojem a konverzi
1. Landing pusobi nedokoncene (placeholder texty / slaba diferenciace).
2. Value proposition neni dost konkretni: proc zrovna tenhle produkt, proc ted, proc placeny tier.
3. Guest -> login -> paid flow nema dost silny "aha moment" hned v prvnich minutach.

Dopad: mensi duvera, nizsi trial start rate, nizsi checkout conversion.

### P1 - Vyrazne brzdy rustu
1. Leaderboards jsou funkcni, ale z hlediska engagementu prilis zakladni.
2. Chybi herni retention loop (seasons, streaks, rank movement, personal progress feedback).
3. Chybi social proof a credibility blocks (konkretni vysledky, ukazky value, proof of expertise).

Dopad: slabsi navratovost, slabsi virality/sdilitelnost.

### P2 - Data a optimalizace
1. Chybi nebo je slaba funnel analytika (view pricing -> start trial -> checkout -> activate -> retain/cancel).
2. Chybi systematicke A/B testovani messagingu a pricing presentation.

Dopad: rozhodovani podle dojmu misto dat, pomalejsi iterace.

## Co je potreba dodelat, aby to pusobilo "pro"

### 1) Landing + messaging redesign (nejvyssi leverage)
- Odstranit vsechny placeholdery.
- Udelat silny hero s jasnym slibem (1 veta), 3 konkretni benefity, 1 primarni CTA.
- Pridat sekci "Jak to funguje" (3 kroky), "Co ziskas hned" a "Pro koho to je".
- Pridat credibility prvky: ukazka realnych dat, screenshoty use-case, transparentni limity free/vip/mvp.

### 2) Monetizace UX (konverzni vrstva)
- Billing page rozsireni: plan comparison table, FAQ, cancel policy, immediate value recap.
- Lepse nacasovane upgrade momenty (contextual prompts uvnitr feature locku).
- Zobrazit "what unlocked" nejen po trial startu, ale i po successful checkoutu.

### 3) Leaderboards jako retention engine
- Casove filtry (daily/weekly/monthly/all-time).
- Personal rank card + movement (up/down vs minule obdobi).
- Sezony a odmeny (i symbolicke badges).
- Lepsi social loop (sdilitelny rank snapshot).

### 4) Onboarding prvnich 10 minut
- Kratky in-app checklist:
  1. propoj Discord/Minecraft,
  2. otevri dashboard,
  3. aktivuj prvni layout,
  4. vyzkousej locknutou premium feature.
- Cilem je rychly "time-to-value" a priprava na prirozeny upgrade.

### 5) Mereni a experimenty
- Instrumentace eventu:
  - landing_cta_click,
  - billing_view,
  - trial_start,
  - checkout_start,
  - checkout_success,
  - cancel_click,
  - cancel_confirmed.
- Definovat weekly review metriky: CTR, trial activation, paid conversion, week-1 retention.

## Doporuceny realizacni plan (4 tydny)

### Tyden 1
- Landing rewrite + odstraneni placeholderu.
- Billing comparison + FAQ.
- Zakladni funnel event tracking.

### Tyden 2
- Leaderboard filtry + personal rank card + trend arrows.
- Upgrade prompts ve spravnych in-app momentech.

### Tyden 3
- Onboarding checklist + first-session guidance.
- A/B test dvou variant hero + pricing messagingu.

### Tyden 4
- Vyhodnoceni dat, iterace textu/UI.
- Zlepseni retention loopu (seasons/badges) podle realneho usage.

## KPI cile (realisticke, orientacni)
- +30-50 % landing -> trial CTR.
- +15-25 % trial -> paid conversion.
- +20 % week-1 retention mezi trial uzivateli.
- Snizeni early cancel rate o 10-15 %.

## Zaverecne stanovisko
Produkt je uz dnes technicky solidni a pripraveny na dalsi rust. Nejvetsi prostor neni v low-level implementaci, ale v product presentation, retention designu a datech.

Pokud se udela vyse popsana P0/P1 vrstva, SkyblockHub se muze posunout z "funkcniho projektu" na platformu, ktera pusobi profesionalne, duveryhodne a komercne udrzitelne.
