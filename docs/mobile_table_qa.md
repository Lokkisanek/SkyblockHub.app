# Mobile Table QA

This checklist covers the densest table states in SkyblockHub.play. The goal is not only to avoid horizontal clipping, but to make the same data readable on a small viewport without losing meaning.

## Target Pages

- [Billing](../resources/js/Pages/Billing/Index.vue)
- [Admin Analytics](../resources/js/Pages/Analitics/Index.vue)
- [Leaderboards](../resources/js/Pages/Leaderboards/Index.vue)

## Breakpoints To Check

- 320px wide
- 375px wide
- 414px wide
- 768px wide

## What To Verify

1. Table content does not clip horizontally without an escape hatch.
2. Dense comparison tables switch to a readable mobile card layout where needed.
3. Row labels stay visible before values.
4. Buttons in table footers remain tappable and do not overlap text.
5. Long labels wrap cleanly instead of forcing hidden overflow.
6. Empty and loading states still fit on narrow screens.

## Page-Specific Notes

### Billing

- Verify the plan comparison still reads correctly on mobile.
- Confirm the mobile plan cards show price, summary, and CTA clearly.
- Check that trial and buy actions remain usable without zooming.

### Admin Analytics

- Verify funnel, experiment, and source segment tables collapse into cards at small widths.
- Confirm KPI cards remain readable and the page does not rely on hidden horizontal scroll.

### Leaderboards

- Confirm the podium and personal rank card reflow cleanly.
- Verify the row actions remain tappable on small screens.
- Check that hidden columns on smaller breakpoints do not leave awkward gaps.

## Done Means

The page is considered mobile-safe when all key values are readable at 320px, actions remain reachable, and no dense table requires pinch-zoom to understand the row.