# Social Proof Onboarding

Datum: 2026-04-11
Scope: Landing page social proof (metriky, use-case screenshoty, testimonials)

## Co je uz implementovano v UI

- Landing obsahuje social proof sekci se 3 castmi:
- Statistiky (3 metriky)
- Use-case screenshot placeholders (3 karty)
- Testimonial placeholders (3 citace)
- Aktivni tracking metrik je napojen na DB + API polling (60s refresh na landingu).

Aktualni stav metrik:
- Snapshot (live DB): 2026-04-11 13:46:02
- Registrovani hraci: 4
- Sledovane flipy: 26,339
- Nactene profily (pocet vyhledani v Profile Stats): 1

Co zustava dodelat:
- Nahradit screenshot placeholdery realnymi use-case obrazky
- Nahradit testimonial placeholdery realnymi overenymi citacemi

## Co je potreba dodat (produkce)

## 1) Metriky (realna cisla)

Potrebne metriky:
- Registrovani hraci
- Sledovane flipy (Bazaar + NPC)
- Nactene profily

Doporucene zdroje:
- `users` tabulka: total count
- Flip eventy/logy: sum vsech analyzovanych flipu
- `profile_searches` tabulka: total count vyhledani hrace ve Profile Stats

Pravidla:
- Pouzivat cache (napr. obnoveni po 5-15 min)
- Hodnoty zobrazovat jako zkracene (`12.4k`, `1.9M`)
- Mit fallback na posledni znamou hodnotu, kdyz metrika selze

## 2) Use-case screenshoty

Potrebne screenshoty:
- Bazaar Flips (top spread + margin + /bz flow)
- NPC Arbitrage (buy vs npc sell + profit + stackability)
- Profile Browser (gear + networth + dungeons)

Specifikace assetu:
- Format: WEBP
- Rozliseni: 1280 x 720
- Max velikost: 350 KB na obrazek
- Cesta (navrh):
  - `public/img/social-proof/bazaar-usecase.webp`
  - `public/img/social-proof/npc-usecase.webp`
  - `public/img/social-proof/profile-usecase.webp`

Kvalitativni pravidla:
- Realna data, zadny lorem ipsum
- Zvyranene dulezite hodnoty (profit, margin, networth)
- Stejny vizualni styl (tmave pozadi, konzistentni spacing)

## 3) Testimonials

Potrebne 3 overene citace:
- 1x Bazaar orientovany hrac
- 1x Event/Mayor orientovany hrac
- 1x Guild officer / advanced user

Pozadovana struktura kazde citace:
- Kratka veta (max 140 znaku)
- Display jmeno nebo nick
- Role (napr. "Bazaar Flipper")
- Volitelne: guild tag

Pravidla duveryhodnosti:
- Mit souhlas s publikaci citace
- Nemit vymyslene identity
- Drzet se konkretniho benefitu (rychlost, jistota, workflow)

## 4) Technicka implementace (stav)

Backend/API:
- HOTOVO: Inertia prop + API endpoint `/api/social-proof-metrics`
- HOTOVO: Agregace dat + cache vrstva (5 min)

Frontend:
- HOTOVO: Placeholder hodnoty nahrazeny realnymi metrikami
- Nahradit placeholder screenshot blocks za `<img>` elementy
- Nahradit placeholder citace realnymi testimonials

Observability:
- Logovat zobrazeni social proof sekce (impression)
- Logovat kliky na screenshoty (pokud budou klikatelne)
- Logovat vliv na CTR do billing/discord CTA

## 5) Akceptacni kriterie

- Landing zobrazuje realna cisla bez hardcoded placeholderu
- Screenshoty jsou produkcni assety v `public/img/social-proof/`
- Testimonials jsou realne a schvalene
- Social proof funguje na mobile i desktopu
- Bez layout shiftu pri nahrani metrik/screenshotu

## 6) Obsahovy checklist pred launchi

- Overit, ze cisla nejsou starsi nez 24 hodin
- Overit pravopis a ton hlasu v EN + CS verzi
- Overit, ze testimonial texty nejsou prilis dlouhe
- Overit kontrast textu (WCAG AA)
- Overit, ze social proof podporuje hlavni claim v hero sekci
