> **Feature plan:** [Step 1](./nav_tree_step1_solution_assessment.md) · [Step 2](./nav_tree_step2_feature_description.md) · [Step 3](./nav_tree_step3_development_plan.md) · [Step 4](./nav_tree_step4_implementation_summary.md)

Debug mode: enabled ([log](./nav_tree_debug_log.md))

Effort study: `railspike-effort` / `v3/nav_tree`; [host ledger](../effort_ledger/nav_tree.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.70**

Epic: [browsing tree checklist](../browsing_tree_checklist.md), item 1 of 3. Step 2 approved 2026-11-25 with its proposed decisions as written.

## Completion Contract

- Normal entry: the operator turns on the new history flag in Tools → Feature flags. A reader browses, then opens Tools → History from the Tools "device" group.
- End-to-end outcome: the Step 2 user flow produces the expected tree. Each visit shows its title and time, and links to its page. Visits opened beside are marked. Clear empties the tree.
- Required recovery: full, missing or unreadable storage leaves every page working and History empty. A damaged history key is replaced on the next visit, not repaired. With the flag off, nothing is recorded and History is absent.
- Deployment and external checks: none beyond the usual Deploy now. No schema change, no server state and no new dependency.
- Release condition: the vertical slice passes in Chromium, Firefox and WebKit, including Beside on a desktop window. The focused tests and the smoke set pass; the full suite is left to the maintainer.

## Key Risks

- **High risk:** **duplicate or missing visits from the Beside pane.** Impact: a tangled tree on every main page with a pane open. Early validation: Stage 4's test, with a restored pane and a theme change. Mitigation: `open_beside.js` marks restores and Open alone; the recorder skips them.
- **New tabs and `sessionStorage`.** Impact: wrong parents in new tabs. Early validation: Stage 1 probe in all three engines. Mitigation: each cursor stores the tab's own ID, so a copied cursor is detected and the referrer rule takes over.
- **Storage size.** Impact: 2,000 visits crowd the site's other local data. Early validation: Stage 1 measures a full history. Mitigation: shorten stored titles or lower the cap before Stage 2.
- **First paint on History.** Impact: the page flashes an empty state. Early validation: Stage 5's pre-paint check. Mitigation: draw the tree from a small inline script right after its container, as the head scripts do.

## Stage 1 — Flag and risk probes

**Estimate: 0.05**

- Goal: add the flag and settle the two open browser questions before any recorder code.
- Dependencies: none.
- Expected changes: one flag in `FeatureFlagRegistry.php` (Experience group, off by default, changeable by the site). Probe results for `sessionStorage` copying and full-history size, recorded in the Step 4 summary.
- Verification approach: the flag registry tests; probe results noted with engine names.
- Estimate details: driver: cross-engine probe; uncertainty: low.
- Risks or open questions: if a probe changes a Step 2 decision, return to Step 2 before Stage 2.
- Canonical components/API contracts touched: `FeatureFlagRegistry.php`, mirrored on `OPEN_BESIDE_ENABLED`.

## Stage 2 — Record page loads

**Estimate: 0.15**

- Goal: each top-level page load records a visit with the right parent.
- Dependencies: Stage 1.
- Expected changes: a new `public/assets/visit_history.js`, loaded by `templates/layout.php` when the flag is on. One local-storage key holds the visits and one `sessionStorage` key the tab cursor. Back and forward, from a load or a page-cache restore, move the cursor and add nothing. Reloads add nothing. The cap drops the oldest visits. Planned page API: `window.forumVisitHistory` with `visits()`, `clear()` and `current()`.
- Verification approach: a new browser test `tests/browser/visit_history.mjs`: board, thread, back, second thread; a reload; a second tab; storage that throws.
- Estimate details: driver: back and forward detection; uncertainty: page-cache restores in WebKit.
- Risks or open questions:
  - Impact: storing the state as JSON. Early warning: review against `CONTRIBUTING.md`. Mitigation: it is internal browser state, like `forum_nav_slots`, not a canonical record.
- Canonical components/API contracts touched: `nav_slots.js` storage pattern; `usage_log.js` page-view capture shape; `BrowserRuntimeProfile.php` for the key names; `TemplateRenderer.php` for the script path.

## Stage 3 — Record address changes

**Estimate: 0.08**

- Goal: `pushState` address changes become visits; `replaceState` updates the current visit.
- Dependencies: Stage 2.
- Expected changes: the recorder watches `pushState`, `replaceState` and `popstate` in `visit_history.js`. Same-address pushes, as in `source_viewer.js`, add nothing. The paned readers stay unchanged.
- Verification approach: extend `visit_history.mjs` with the paned board reader: select two threads, press back.
- Estimate details: driver: wrapping history calls without changing them for other scripts; uncertainty: low.
- Risks or open questions: a reader script loaded before the recorder. Mitigation: wrap in the head, before page scripts run.
- Canonical components/API contracts touched: `paned_board_reader.js`, `paned_activity_reader.js`, `paned_users_reader.js`, `offline_reader.js`, `platform_docs.js`, all used unchanged.

## Stage 4 — Beside pane and other frames

**Estimate: 0.12**

- Goal: pane pages record visits under the right parent; other frames record nothing.
- Dependencies: Stage 3.
- Expected changes: a pane cursor in `sessionStorage`. `open_beside.js` marks a restore and Open alone for the recorder. The recorder skips the theme reload and frames that are not the pane, such as the tour.
- Verification approach: extend `tests/browser/open_beside.mjs`: open beside, follow a link in the pane, change the theme, load another main page, press Open alone. Also check a tour frame records nothing.
- Estimate details: driver: the restore and Open alone signal; uncertainty: timing between the pane's load and the main page's.
- Risks or open questions: see the high risk above.
- Canonical components/API contracts touched: `open_beside.js` (extended, small), the `data-open-beside-framed` mark in `layout.php`, `templates/tour/page.php` (unchanged).

## Stage 5 — Tools → History page

**Estimate: 0.12**

- Goal: a reader sees the tree, opens any visit and clears it.
- Dependencies: Stage 2.
- Expected changes: a route `/tools/history/` behind the flag, a controller method and `templates/pages/history.php`. A small inline script draws the tree before paint; a page script handles Clear. The page itself is never recorded. Without JavaScript, one line says it needs JavaScript.
- Verification approach: a PHP page test (route on and off); `visit_history.mjs` checks the tree, a click and Clear; the pre-paint check pattern from `viewer_nav_prepaint.mjs`.
- Estimate details: driver: drawing a tree before paint; uncertainty: long titles on a phone.
- Risks or open questions: see the first-paint risk above.
- Canonical components/API contracts touched: `usage_log.php`, `usage_log_page.js` and `UsageReportsController.php` as the layout and Clear precedent; `tools_nav_card.php`.

## Stage 6 — Tools listing and icons

**Estimate: 0.05**

- Goal: History is reachable from Tools in every theme.
- Dependencies: Stage 5.
- Expected changes: a History entry in the "device" group of `ToolsPageSupport.php`. A History icon in every set of `theme-nav-icons.css`, for the launcher button and Tools subnavigation.
- Verification approach: `NavIconSetsTest`; the icon checked by eye in Word97, Gloss and Bubblegum.
- Estimate details: driver: drawing three icon styles; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `ToolsPageSupport.php`, `theme-nav-icons.css`.

## Stage 7 — Acceptance and documentation

**Estimate: 0.10**

- Goal: prove the Completion Contract end to end and record the concepts.
- Dependencies: Stages 4 and 6.
- Expected changes: run the vertical slice in Chromium, Firefox and WebKit, online and offline. Add a *visit tree* concept entry; update the open beside and tools launcher entries. Update the browsing tree checklist.
- Verification approach: the slice in three engines; the smoke set plus the focused tests; a final diff review for duplication and scope.
- Estimate details: driver: WebKit runs; uncertainty: offline reader timing.
- Risks or open questions: a failure here returns to its stage before closeout.
- Canonical components/API contracts touched: `docs/concepts/`, `browsing_tree_checklist.md`.

## Concepts touched

- Proposed *visit tree*: created in Stage 7.
- [Open beside](../../concepts/open_beside.md) and [tools launcher](../../concepts/tools_launcher.md): changed.
- [Navigation slots](../../concepts/navigation_slots.md), [usage reports](../../concepts/usage_reports.md), [tour snapshot](../../concepts/tour_snapshot.md): used unchanged.

## Study Snapshots

2026-11-25T07:25Z; scope v2; starting revision `2d7805c8b6d63b0f2ff6ecdd264ec257eaece23b`; reference R1, hashes not re-verified. Claude, single exposed rater, who saw the Step 1 and 2 scores.

- Step 3 handoff: **0.70 projected total**, up from 0.65. Reason: the plan adds three-engine acceptance, a pre-paint tree and the tour frame check. The total is judged on its own, not summed from stages. Not hours or measured labor; no approval to advance.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*
