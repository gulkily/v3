# Browsing Tree Checklist

Working checklist for one story told in three Railspike cycles. It is not a
Railspike artifact and approves no implementation. Each item runs as its own
cycle, following AGENTS.md. Record progress, checks and limitations here.

Checklist merged in [PR #265](https://github.com/gulkily/v3/pull/265); updated 2026-11-25.

Requested by the maintainer on 2026-11-14: a ZenMemes reader gets tree
history and tree bookmarks, as in the Mosaic browser, and a filmstrip of
recent screens. Cached HTML pages cost almost nothing to show again, so v3
can offer many screens at once.

## Decisions so far

- History stays on the device. Syncing a username's history across devices
  is a possible later cycle. Visits are append-only records with an ID, so
  syncing stays possible.
- A visit is a page change: a page load, or an address change made by a page
  script, such as the paned board reader's `pushState`. Opening a drawer or
  switching an in-page tab is not a visit yet; revisit later.
- History stays separate from the opt-in usage log. The usage log strips IDs
  and can be uploaded; history keeps full addresses and never leaves the
  device.

## Reuse inventory

- `public/assets/nav_slots.js` keeps per-browser state in one local-storage
  key (`forum_nav_slots`) and applies it before first paint. It is the
  precedent for storing visits.
- `public/assets/usage_log.js` already records page views and clicks. Its
  capture shape is reusable; its records are not, because they strip IDs.
- `public/assets/paned_board_reader.js` and `public/assets/offline_reader.js`
  change the address with `history.pushState` and listen for `popstate`.
- `public/assets/browser_signing.js` already detects back and forward through
  the navigation entry type `back_forward`.
- `public/service_worker.js` fetches pages network-first. Since
  [nav cached copy](nav_cached_copy/nav_cached_copy_one_shot.md), it also
  keeps a last copy of each navbar page (cache `nav-copy`, behind its flag).
  Thread and other content pages are still not cached by the worker, which
  matters for the filmstrip. [Nav quick wins](nav_quick_wins/nav_quick_wins_one_shot.md)
  added an early fetch when a finger goes down on a navbar link. Neither the
  nav-copy answer nor the early fetch is a visit (nav_tree Step 1).
- `public/assets/open_beside.js` (the [open beside](open_beside/open_beside_step3_development_plan.md)
  mode) already shows a same-origin page in a frame beside the column and
  tracks its title and address. It is the precedent for live frames in the
  filmstrip. Its pane pages are visits with their own cursor (nav_tree).
- `templates/tour/page.php` already shows saved pages in scaled-down frames,
  a precedent for live filmstrip frames (item 3).

## Estimates

Whole-cycle judgments on the `railspike-effort` R1 scale, where 1.0 is the
largest comfortably approved single-cycle slice. R1 hashes were not
re-verified. These are single exposed-rater annotations, not ledger
snapshots, and must not be summed. Each cycle records its own snapshots
under protocol P2.

## Checklist

- [x] **1. `nav_tree`: record visits as a tree.**
  **Estimate: 0.55**
  Each tab remembers its current visit, so a new visit's parent is the page
  the reader came from in that tab. A tab with no current visit falls back
  to the referrer. Back and forward move to an existing visit instead of
  adding one. A plain Tools → History page lists the tree, with a Clear
  button, so the cycle is usable on its own.
  - _Step 1 drafted 2026-11-15 (recommends a per-tab cursor with the
    referrer as fallback); awaiting Approved Step 1. Staged plans are
    committed after Approved Step 3._
  - _Step 1 revised 2026-11-24 against main `2d7805c` (open beside, early
    fetch on tap, nav-copy cache). Still Option B, now with a second cursor
    for the beside pane; estimate 0.65. Draft kept on the unmerged planning
    branch `plan/nav_tree-step1`; awaiting Approved Step 1._
  - _Approved Step 1 on 2026-11-25. Step 2 drafted with proposed decisions
    (flag off by default, Tools device group, 2,000 visits, history kept
    across key changes, recorded offline); awaiting Approved Step 2._
  - _Approved Step 2 on 2026-11-25 with the proposals as written. Step 3
    drafted in seven stages; estimate 0.70; awaiting Approved Step 3._
  - _Approved Step 3 on 2026-11-25. Step 4 on `feat/nav-tree`; plans in
    [nav_tree/](nav_tree/nav_tree_step4_implementation_summary.md)._
  - _Step 4 done 2026-11-25 on `feat/nav-tree`, behind
    `FORUM_VISIT_HISTORY_ENABLED`: recorder (`visit_history.js`), Beside pane
    lane, Forte pages, Tools → History. Passed in Chromium, Firefox and WebKit;
    offline in Chromium. Concept: [visit tree](../concepts/visit_tree.md)._
  - _Merged in [PR #420](https://github.com/gulkily/v3/pull/420) on
    2026-11-25 and deployed; the maintainer accepted it the same day and
    judged it a comfortable fit for one cycle. The 1280×720 Tools launcher
    check that failed after the merge is fixed in
    [tools_history_reading](tools_history_reading/tools_history_reading_one_shot.md)
    (History moves to the Reading group)._
- [ ] **2. `tree_history`: history and bookmark views.**
  **Estimate: 0.50**
  A richer tree view of history, with branches that fold and a way back to
  any earlier branch. Bookmarks are pinned visits, kept in folders that form
  their own tree.
- [ ] **3. `filmstrip`: a strip of recent screens.**
  **Estimate: 0.60**
  A strip along the edge of the screen shows recent pages and their
  branches. A frame can be the real page in a scaled-down frame instead of a
  screenshot. Test memory use on an iPhone SE first; if a dozen live frames
  are too heavy, show placeholders and load a live page only on tap. That
  fallback may need its own cycle.

## Open questions

- Whether cached pages are cheap enough for live frames, given that the
  service worker does not cache most pages (item 3).
- Answered 2026-11-25 (nav_tree Stage 1): a tab opened from a link starts
  empty in Chromium, Firefox and WebKit; `window.open` copies the opener's
  `sessionStorage`, which gives the right parent (item 1).
- Later: sync across devices, and in-page changes as visits.
- Answered 2026-11-24 (nav_tree Step 1): a page opened in the open beside
  pane is a visit, under the page in the column it was opened from (item 1).
- Whether the filmstrip and the pane list in the
  [open beside design notes](open_beside_design_notes.md) are one control:
  a strip of open panes is also the reader's recent path (item 3).
