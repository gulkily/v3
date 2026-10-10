> **Feature plan:** [Step 1](./offline_indicator_corner_links_step1_solution_assessment.md) · [Step 2](./offline_indicator_corner_links_step2_feature_description.md) · [Step 3](./offline_indicator_corner_links_step3_development_plan.md) · [Step 4](./offline_indicator_corner_links_step4_implementation_summary.md)

# Offline Indicator Corner Links Step 1 Solution Assessment

## Original Query

Redesign the offline mode indicator to not take up extra vertical space (probably top corner?). Also, make it link to useful pages like /offline/.

## Understood Intent

The offline-mode bar is currently a full-width row above the reader content (with the label, an Outbox link, and archive/reader indicators). The goal is to remove that row from the page flow by moving the indicator into a screen corner, and to make it a navigation aid to offline pages such as `/offline/` (alongside the existing `/tools/outbox/` link).

## Problem

The offline-mode indicator consumes a full row of vertical reading space and offers little navigation beyond Outbox.

## Option A — Fixed corner badge with a small links menu

The bar becomes a compact, fixed-position badge in the top corner, overlaying the page instead of pushing content down. The "offline mode" label is the badge; activating it expands a small popover listing useful links (`/offline/`, Outbox, Offline Reading health) plus the archive/reader freshness indicators.

- Pros:
  - Zero vertical space in the page flow; works on narrow screens.
  - Room for several links and freshness details without crowding.
  - Reuses the existing bar element and indicator data.
- Cons:
  - Needs open/close behavior and keyboard/focus handling.
  - Fixed overlay may cover header controls in the corner.
  - Links are one tap further away.

## Option B — Fixed corner badge with always-visible inline links

Same corner placement, but a small non-collapsing badge shows the label plus one or two links (e.g. `/offline/`, Outbox) directly, with freshness indicators moved to hover title text or the health page.

- Pros:
  - No interaction state; links are one tap away.
  - Simplest to build and test.
- Cons:
  - Limited room, so only a couple of links fit; freshness indicators lose visibility.
  - Wider badge risks covering header content on narrow screens.

## Option C — Move the bar into the existing site header

Place the indicator inside the site header's existing row (no overlay), linking to `/offline/` and Outbox, instead of in the reader section.

- Pros:
  - No overlay or covering of content; consistent with site chrome.
- Cons:
  - Offline reader template owns the bar today; header changes touch shared layout for all pages and sites.
  - Header may still grow taller on narrow screens, missing the core goal.
  - Larger blast radius.

## Recommendation

Choose **Option B** (selected by the user). It removes the indicator from the page flow, keeps links one tap away with no open/close state, and stays inside the existing offline-reader bar. It forms one releasable vertical slice: an offline reader sees the corner badge and reaches `/offline/` or Outbox directly. Step 2 should validate early that the badge does not cover header controls on narrow screens, and decide where the freshness indicators (archive/reader revision) move, such as hover titles or the health page.

Approved Step 1?
