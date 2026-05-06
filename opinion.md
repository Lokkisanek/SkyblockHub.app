# SkyblockHub.play - Product Opinion (Audit)

Datum: 2026-05-06 (rev 7)
Scope: Produktovy a technicky audit proti aktualnimu stavu aplikace. Pokryva routing, frontend, API, analytics, onboarding, Sentry ops, mobile UX, billing, testy a CI.

---

## Executive Summary (rev 7)

Projekt je dnes technicky zraly pro public use a realne uzivatele. Hlavni produktove cesty jsou hotove, observability je zapojena a vyrazne pribyla operacni disciplina.

- Publikovatelnost: **93/100**
- Uspesnost (growth/retence/monetizace): **85/100**

Co to znamena:
- Frontend i backend bezne bezi v jednom stabilnim dev stacku.
- Analytics uz nejsou jen dashboard, ale i sbirany event layer, weekly review, digest mail a routing podle thresholdu.
- Sentry ma nejen integraci, ale i formalni runbook, ownership a triage pravidla.
- Dense tabulky uz nejsou jen responsivni v technickem smyslu, ale maji mobilni card fallbacky a QA checklist.

---

## Co Je Ted Silne

### 1) Core produkt a routing
- Landing, billing, dashboard, profile stats, bazaar, NPC flips, events, mayors, leaderboards a portfolio jsou pokryte jako souvisly produkt.
- `/admin` je primarni admin analytics route a legacy `/analitics` na ni presmerovava.
- `DashboardController` ma i public visit route pro sdilene dashboardy podle Minecraft UUID.
- Auth redirecty pres Discord a Microsoft uchovavaji intended target, takze login flow neni slepy konec.

### 2) Onboarding a activation
- Onboarding checklist je skutecny produktovy prvek, ne jen vizualni badge.
- Stav se uklada per user, segmentuje se na `unlinked`, `linked_free`, `trial`, `paid` a ma copy varianty `a`/`b`.
- Checklist se automaticky doplnuje podle route, takze first-session flow muze vedet uzivatele k prvnimu win momentu.
- Authenticated shell onboarding i zobrazuje primo v layoutu.

### 3) Analytics a growth workflow
- Funnel event layer existuje jako samostatna tabulka, model i service.
- Landing CTA, billing view, trial start, checkout start, checkout success a cancel se trackuji pres jednotny funnel service.
- Admin analytics dashboard pocita weekly review, source segmenty, conversion alerts i experiment varianty.
- Existuje weekly digest mail i scheduler, takze review neni jen manualni otevreni stranky.

### 4) Observability a ops disciplina
- Frontend Sentry bezi v `resources/js/app.js`, backend capture je v `bootstrap/app.php`.
- `config/ops.php` popisuje owner routing, triage pravidla a runbook pro frontend, backend, performance i release regressions.
- `docs/sentry_ops_runbook.md` vyslovne rika, co delat v prvnich 15 minutach, prvnich 4 hodinach a dalsi den.
- CI doplnuje release metadata do `.env`, aby release tagging sedel mezi buildem a produkci.

### 5) Mobile UX
- Hustsi tabulky na Billing, Admin Analytics a Leaderboards maji mobile card fallbacky.
- Pro tyto stranky je v repu i explicitni QA checklist pro 320/375/414/768px.
- Leaderboards uz nejsou jen tabulka, ale ma period layer, personal card, podium a action links.

### 6) Billing a upgrade prompts
- Billing ma prepsanou cenovou tabulku i mobilni karticky.
- Contextual upgrade prompts jsou zapojene do produktnich stranek a trackuji impression, CTA i compare akce.
- Stripe webhook i billing controller sbiraji funnel signaly, takze monetization path je viditelna v datech.

---

## Co Je Slabsi

### P0 - stale nejvetsi dopad na uspech
1. **Automatycky delivery alertu je porad spis workflow nez plny system**
  - Dashboard ukazuje weekly review a alerts, ale tim to nekonci.
  - Pokud se ma growth a ops ridit disciplinovane, je potreba dosadit konkretni notification kanaly a pravidelny follow-up rytmus.

2. **Experiment framework je zatim jen lehka verze**
  - Copy varianty existuji u onboarding promptu i upgrade promptu.
  - Chybi ale plnohodnotny statisticky layer pro rozhodovani, ne jen shromazdovani variant.

### P1 - dulezite pro retenci
1. **Leaderboards maji silny layout, ale ne kompletni progression system**
  - Period filtering, movement, personal card a mobile fallback uz jsou hotove.
  - Stale chybi badge/achievement layer a jasnejsi dlouhodobej progress framing.

2. **Business ops panel stale neexistuje jako samostatny produkt**
  - Analytics page je dobra, ale neni to plnohodnotny interni operations cockpit.
  - Chybi pohled na subscription health, churn, revenue trend a cohorty jako samostatna admin surface.

3. **Nektere minorni UX a QA mezery zustavaji**
  - Tabulky jsou uz pouzitelne, ale stale je potreba pravidelne ručni kontrola na skutecnych malych displejich.
  - Billing edge-case redirecty a guest flow jsou porad oblast, kterou je dobre drzet pod dohledem.

---

## Aktualni Stav Po Changech

### Routing a pages
- `/admin` je canonical entrypoint pro admin analytics.
- `/analitics` zustava jako kompatibilni legacy redirect.
- Dashboard ma beta info page a admin link pro testing admina.
- Landing ma tracking CTA a Discord invite block.

### Data a backend
- Profile cache se uklada i pro leaderboard ingest.
- SkyCrypt proxy ma DB-cache fallback, kdyz upstream neodpovida.
- Leaderboard API cte z profiles cache a enrichuje data o app user vazby, public dashboard flag, movement i status.

### Frontend
- i18n je sjednocenejsi pro SSR i klienta.
- Authenticated layout sdili onboarding props a zobrazuje onboarding checklist.
- Billing, leaderboards a admin analytics maji mobilni fallbacky misto pouheho horizontálního scrollu.

### Ops a release
- Sentry je pripojene na obou stranach stacku.
- Ops config, runbook a testy definuji ownera i triage.
- CI doplnuje release env promene, takze incident tagging je predvidatelnejsi.

---

## Bodove Hodnoceni

### A) Publikovatelnost: **93/100**

- Stabilita jadra a funkcnost: **29/30** - hlavni produktove cesty bezi, dev stack je stabilni a routing je cisty.
- Monetizace a entitlement logika: **18/20** - billing, trial, gating a upgrade prompts jsou zapojene.
- Compliance a duvera: **14/15** - privacy, terms, consent, about a safe redirects jsou v poradku.
- QA/CI pripravenost: **15/15** - testy pokryvaji routing, analytics, onboarding i ops config a CI nesedí jen na jednom build kroku.
- Operacni pripravenost: **17/20** - Sentry a runbook jsou solidni, ale alert delivery a follow-up workflow jeste neni plne automatizovany.

### B) Uspesnost: **85/100**

- Problem-solution fit: **25/30** - core value je jasna a produkt ma vice silnych modu.
- Activation: **17/20** - onboarding checklist a contextual prompts zlepsuji prvni session.
- Retence: **16/20** - leaderboards a dashboard progression uz davaji smysl, ale stale neni kompletni badge/progression loop.
- Monetization conversion: **14/15** - billing, trial a upgrade prompts jsou dobre propojene.
- Decision intelligence: **13/15** - analytics uz pomahaji, ale experiment framework je porad lehci nez by mel byt.

---

## Co Je Skutecne Kriticke

### P0 - bez toho se provoz lomi
1. Admin review musi mit jeden konkretni notifikacni kanál a jednoho ownera, jinak weekly digesty a alerty zustanou jen dashboardem bez akce.
2. Release tagging, Sentry routing a triage pravidla musi fungovat konzistentne napric frontendem, backendem a deployem, jinak nejde spolehlive rozhodovat o regresich.
3. Funnel reporting pro onboarding a upgrade prompty musi mit aspon minimalni experiment layer, aby se dalo poznat, co realne funguje a co jen vypada dobre.
4. Billing, trial a redirect edge-cases musi zustat pod kontrolou, protoze tady se lomi login, checkout a navraty z auth flow.
5. Leaderboards a public dashboardy musi zustat napojene na validni data cache a fallback chovani, jinak spadne hlavni produktova viditelnost.

### P1 - dulezite, ale az po stabilizaci P0
1. Interni ops cockpit pro billing, churn a revenue health.
2. Progresni vrstva pro leaderboards a retention loop.
3. Systematicky mobile QA na skutecnych malych displejich.

---

## Zaverecne Stanovisko

SkyblockHub je v aktualnim stavu **pripraveny na realny provoz**. Nejde uz o projekt, kde by byl problem v zakladni funkcnosti; nejvetsi zisky jsou ted v discipline, automatizaci a decision-making workflow.

Nejsilnejsi zmeny proti predchozi verzi jsou:
- admin analytics route a digest workflow,
- Sentry ops runbook s owner routingem,
- onboarding checklist s segmentaci a copy variantami,
- mobile-safe fallbacky pro dense tabulky,
- tracking funnelu a upgrade prompts napric produktem.

Zbyvajici mezery uz nejsou o tom, jestli produkt funguje. Jsou o tom, jak dobre se bude dal rict co zmenit, komu to prijde a jak rychle se z toho udela systematicky rozhodovaci proces.
