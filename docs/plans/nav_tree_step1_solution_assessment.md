> **Feature plan:** [Step 1](./nav_tree_step1_solution_assessment.md) · [Step 2](./nav_tree_step2_feature_description.md) · [Step 3](./nav_tree_step3_development_plan.md) · [Step 4](./nav_tree_step4_implementation_summary.md)

Debug mode: enabled ([log](./nav_tree_debug_log.md))

Effort study: `railspike-effort` / `v3/nav_tree`; [host ledger](./effort_ledger/nav_tree.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.65

Epic: [browsing tree checklist](./browsing_tree_checklist.md), item 1 of 3.

## Original Query

So in HTML, we basically get free unlimited screens for free. Like, as long as we don't break the cache, we have like free unlimited screens. And I feel like we're not taking advantage of that nearly enough. First of all, there's like tree bookmarks and tree history. First demonstrated in the Mosaic browser to my knowledge. And then you could have like a movie film little taskbar that has many screenshots. How many rail spikes is this?

It's for V3 and Zen memes.

Device only is fine to start with, but do make a note that we could synchronize these.

Do page change only, but remember that we mess with that somehow.

## Understood Intent

The maintainer split the idea into three cycles: `nav_tree` (record visits as a tree), `tree_history` (history and bookmark views) and `filmstrip` (a strip of recent screens). This cycle is `nav_tree`. Decisions already made:

- History stays on the device. Syncing a username's history across devices may come later, so visits are append-only records with an ID.
- A visit is a page change: a page load, or an address change from `history.pushState`. Drawers and in-page tabs come later.
- History stays separate from the opt-in usage log. The usage log strips IDs and can be uploaded; history keeps full addresses and never leaves the device.
- The cycle includes a plain Tools → History page, so it is usable on its own.

## Problem Statement

The browser keeps history as a flat list, so a reader cannot see which page led to which or return to an earlier branch of their reading.

## What changed on main since the draft

The draft was written against `8bec1f7d`. Main at `2d7805c` adds three things that shape capture.

- **Open beside** (`open_beside.js`). A page in the pane is a real page in a frame named `forum-open-beside`. The frame shares the tab's `sessionStorage`, so one cursor per tab would be overwritten by the pane. Answers below.
- **Early fetch on tap** (`fast_navigation.js`). A finger down on a navbar link asks the service worker to fetch the page before the click. That fetch can happen with no visit, for example when the finger slides off.
- **Navbar pages from last copy** (`nav_cached_copy.js` and the worker's `nav-copy` cache). A navbar page can be answered from an old copy while a fresh one loads in the background. The page script still runs, so it is still a visit; its title may be the old copy's. The cache is cleared when the identity changes.

More scripts now call `pushState`: the paned board, activity and users readers, the offline reader and the platform docs. `source_viewer.js` pushes the same address, so it is no visit under the address-change rule. `url_hints.js` and others call `replaceState`; that should update the current visit's address, never add one. Tour snapshots (`templates/tour/page.php`) show real pages in other frames; those must record nothing.

### Open beside answers

- **Does a page in the pane count as a visit?** Yes, when the reader puts it there: a click that opens the pane, or a link followed inside it. These are not visits: the pane restored on the next main page, and the pane's reload when the theme changes.
- **What is its parent?** A page opened from the main page has the main page's current visit as parent. A link followed inside the pane has the previous pane visit as parent. A pane visit is marked as shown beside, for the History page.
- **Open alone** adds no visit. The tab's cursor moves to the pane's visit, because the reader has already seen that page.

## Concepts touched

- All options: proposed *visit tree*; [navigation slots](../concepts/navigation_slots.md) (precedent for per-browser state, unchanged); [tools launcher](../concepts/tools_launcher.md) (gains a History tool); [usage reports](../concepts/usage_reports.md) (reviewed, kept separate); [open beside](../concepts/open_beside.md) (pane pages are visits, script unchanged or a small hook); [tour snapshot](../concepts/tour_snapshot.md) (its frames record nothing).
- Option C: [outbox](../concepts/outbox.md) (service worker precedent, unchanged).

## Solution Options

### Option A: Page script with the referrer as parent

**Estimate: 0.50**

A small script on every top-level page saves a visit with its address, title and time. The parent is the latest visit whose address matches `document.referrer`. `pushState` address changes also count. Back and forward move to an existing visit instead of adding one.

- Pros: smallest change; the same shape as the `usage_log.js` recorder; works on a first visit and over HTTP.
- Cons: the referrer is ambiguous when a page was visited twice; two tabs tangle the tree; a pane page's referrer is the main page even after links inside the pane, so pane branches come out wrong.
- Driver: back and forward handling, including pages restored from the browser's page cache. Uncertainty: how often the referrer misleads.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
*— Revised by Claude · 2026-11-24 UTC · Model: claude-opus-5-5 (pane referrer, estimate 0.45 → 0.50)*

### Option B: Per-tab cursors, with the referrer as fallback

**Estimate: 0.65**

Each tab keeps two cursors in `sessionStorage`: the main page's current visit and the pane's current visit. A new main-page visit's parent is the main cursor. A new pane visit's parent follows the open beside answers above. A tab with no cursor, such as a new tab or a link from another site, falls back to Option A's referrer rule. Visits live in one local-storage key, like `forum_nav_slots`, as append-only records with an ID, time, address, title, parent and a beside mark. Frames other than the pane record nothing.

- Pros: the tree matches what the reader did; a page visited twice keeps both branches; the pane gets correct branches; append-only records keep syncing possible.
- Cons: browsers differ in whether a tab opened from a link copies the opener's `sessionStorage`; telling a restored pane from a reader's click needs a small signal from `open_beside.js`; more cases to test.
- Driver: cursor rules for tabs, the pane and back and forward. Uncertainty: new-tab inheritance across Safari, Chrome and Firefox.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
*— Revised by Claude · 2026-11-24 UTC · Model: claude-opus-5-5 (pane cursor, frame rule, replaceState; estimate 0.55 → 0.65)*

### Option C: Service worker records navigations

**Estimate: 0.85**

`service_worker.js` already sees every page request. It would save each one with the request's referrer as the parent.

- Pros: records pages that load no scripts; one place for capture.
- Cons: no worker over HTTP or on a first visit; pages restored by back and forward are never fetched; `pushState` changes are invisible; early fetches and background nav-copy refreshes are requests with no visit; titles need parsing from HTML; mixes history with offline caching.
- Driver: filtering the worker's own fetches and filling the gaps with a page script, which turns it into Option B plus a worker.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
*— Revised by Claude · 2026-11-24 UTC · Model: claude-opus-5-5 (early fetch and nav-copy gaps; estimate 0.75 → 0.85)*

## Recommendation

**Option B**, unchanged from the draft, now with a pane cursor. It records the tree as the reader experienced it, keeps Option A's rule for tabs with no cursor and gives the pane its own branches. Option C now misses even more, because the worker fetches pages the reader never visits.

Shared across options and decided in Step 2: a feature flag; how many visits to keep and for how long; a Clear button for shared devices; whether changing or signing out of a key also clears history, as it clears the nav-copy cache; and where History sits in Tools, with its navigation icon in every icon set.

**Vertical-slice viability:** a reader opens the Board, opens a thread, goes back, then opens a second thread. Tools → History shows the Board with two child threads, each with its title and time. With Beside on, a thread opened in the pane shows under the page it was opened from, marked as beside. A second tab opened on Recent starts its own branch. Clear empties the page.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
*— Revised by Claude · 2026-11-24 UTC · Model: claude-opus-5-5*

## Enhancement Requests

- Sync history across a username's devices. Disposition: deferred to a later cycle; append-only records keep it possible. Approval: noted by the maintainer.
- Count in-page changes, such as drawers and in-page tabs, as visits. Disposition: deferred; the maintainer wants to revisit it later. Approval: noted by the maintainer.
- Tree views and tree bookmarks (`tree_history`) and a filmstrip of recent screens (`filmstrip`). Disposition: separate cycles that build on this one.

## Study Snapshots

2026-11-15T02:44:42Z; scope v1; starting revision `8bec1f7d81c5fe79ff68536a50d1a2748fb553f7`; reference R1, hashes not re-verified. Claude, single exposed rater. A pristine intake is missing: the service worker, usage log recorder, navigation slots and paned readers were read before rating.

- Intake and Step 1 handoff: **0.55 projected total** for Option B with a plain History page. Alternatives A 0.45 and C 0.75, non-additive. Not hours or measured labor; no approval to advance.

2026-11-24T17:55Z; scope v2 (same outcome; new understanding, not changed scope); starting revision `2d7805c8b6d63b0f2ff6ecdd264ec257eaece23b`; reference R1, hashes not re-verified. Claude, single exposed rater, who saw the v1 scores.

- Step 1 handoff (revision): **0.65 projected total** for Option B. Reason: open beside needs a second cursor and a hook, more scripts call `pushState`, `replaceState` and tour frames need rules. Alternatives A 0.50 and C 0.85, non-additive. Not hours or measured labor; no approval to advance.

Status: Step 1 revised; awaiting Approved Step 1. Planning records stay uncommitted on any feature branch; this draft lives only on the planning branch `plan/nav_tree-step1`.
