> **Feature plan:** [Step 1](./offline_indicator_corner_links_step1_solution_assessment.md) · [Step 2](./offline_indicator_corner_links_step2_feature_description.md) · [Step 3](./offline_indicator_corner_links_step3_development_plan.md) · [Step 4](./offline_indicator_corner_links_step4_implementation_summary.md)

# Offline Indicator Corner Links Step 2 Feature Description

## Problem

The offline-mode bar takes a full row above reader content and links only to Outbox. Offline readers lose vertical space and have no quick route to the `/offline/` page.

## User stories

- As an offline reader, I want the offline indicator in a top corner so that it doesn't push content down.
- As an offline reader, I want direct links to `/offline/` and Outbox in the indicator so that I can reach offline tools without leaving context.

## Core requirements

- Show the indicator as a compact badge in a top corner, out of the page flow, adding no vertical space.
- Badge shows the "offline mode" label plus always-visible links to `/offline/` and the existing `/tools/outbox/`.
- Archive time and reader revision remain available (as hover titles on the badge) and the Offline Reading health page stays the detailed diagnostic surface.
- Badge appears only when the bar is currently shown, with no change to when offline mode is detected.
- Do not change snapshot contents, caching, routes, or Outbox behavior.

## Delivery scope

- Work type: application change.

## Completion boundary

- **Entry:** reader opens saved content offline, as today.
- **Outcome:** corner badge replaces the full-width bar; its links open `/offline/` and Outbox.
- **Recovery:** unknown freshness values keep safe fallbacks; links work from any offline page.
- **Release condition:** reader layout has no extra row, and both links work on wide and narrow screens.

## Risks

- **Overlap with header controls (high impact on narrow screens):** validate first at phone width; mitigate with a small badge and offset below or beside existing header chrome.
- **Loss of visible freshness info (medium):** confirm hover titles plus the health page suffice; touch devices lack hover, so Step 3 must verify the health link is reachable.
- **Fixed overlay covering page content or tap targets (medium):** check scrolled content and small viewports early; keep badge minimal and non-blocking.

## Shared component inventory

- **Offline-mode bar** (`templates/pages/offline_reader.php`, styles in `public/assets/site.css`, shown via `offline_reader.js`): extend as the single indicator; no second status element.
- **Archive/reader indicators:** reuse existing values; change presentation only.
- **`/offline/` page and `/tools/outbox/`:** existing destinations, reused as-is.
- **Offline Reading health page:** unchanged.

## User flow

1. Reader opens saved content offline.
2. A compact badge appears in the top corner, with no layout shift.
3. Reader taps `/offline/` or Outbox in the badge to navigate.

## Success criteria

- Offline reader content starts at the same vertical position as with the bar hidden.
- Badge shows the label and both links on desktop and 360px-wide screens without covering header controls.
- Both links navigate correctly; freshness values remain reachable.
- Existing offline rendering and tests continue to pass.

Approved Step 2?
