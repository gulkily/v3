> **Feature plan:** [Step 1](./nav_tree_step1_solution_assessment.md) · [Step 2](./nav_tree_step2_feature_description.md) · [Step 3](./nav_tree_step3_development_plan.md) · [Step 4](./nav_tree_step4_implementation_summary.md)

Debug mode: enabled ([log](./nav_tree_debug_log.md))

Effort study: `railspike-effort` / `v3/nav_tree`; [host ledger](./effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.55

## Original Query

So in HTML, we basically get free unlimited screens for free. Like, as long as we don't break the cache, we have like free unlimited screens. And I feel like we're not taking advantage of that nearly enough. First of all, there's like tree bookmarks and tree history. First demonstrated in the Mosaic browser to my knowledge. And then you could have like a movie film little taskbar that has many screenshots. How many rail spikes is this?

It's for V3 and Zen memes.

Device only is fine to start with, but do make a note that we could synchronize these.

Do page change only, but remember that we mess with that somehow.

## Understood Intent

The maintainer agreed to split the idea into about three cycles: `nav_tree` (record visits as a tree), `tree_history` (history and bookmark views), and `filmstrip` (a strip of recent screens). This cycle is `nav_tree`. Decisions already made in conversation:

- History stays on the device for now. Syncing a username's history across devices is a possible later cycle.
- A visit is a page change: a page load, or an address change made by a page script. Opening a drawer or switching an in-page tab is not a visit yet.

So the cycle stays usable on its own, it includes a plain page that lists the tree. Richer views and bookmarks stay in `tree_history`.

## Problem Statement

The browser keeps history as a flat list, so a reader cannot see which page led to which or return to an earlier branch of their reading.

## Concepts touched

- All options: proposed *visit tree*; [navigation slots](../concepts/navigation_slots.md) (precedent for per-browser state, unchanged); [tools launcher](../concepts/tools_launcher.md) (gains a History tool); [usage reports](../concepts/usage_reports.md) (reviewed, kept separate).
- Option C: [outbox](../concepts/outbox.md) (service worker precedent, unchanged).

## Solution Options

### Option A: Page script with the referrer as parent

**Estimate: 0.45**

A small script on every page saves a visit with its address, title and time. The parent is the visit whose address matches `document.referrer`. Address changes from `history.pushState`, such as the paned board reader's, also count as visits. Back and forward move to an existing visit instead of adding one.

- Pros: smallest change; the same shape as the `usage_log.js` recorder; works on the first visit and over HTTP.
- Cons: the referrer is ambiguous when the same page was visited twice; two tabs on one site produce a tangled tree; a referrer can be missing.
- Driver: back and forward detection, including pages restored from the browser's page cache. Uncertainty: how often the referrer misleads.

### Option B: Per-tab cursor, with the referrer as fallback

**Estimate: 0.55**

Each tab remembers its current visit in `sessionStorage`. A new visit's parent is that tab's current visit, so two tabs grow two branches. A tab with no cursor, such as a new tab or a link from another site, falls back to Option A's referrer rule. Visits live in one local-storage key, like `forum_nav_slots`, as append-only records with an ID, time, address, title and parent. Append-only records keep later syncing possible.

- Pros: the tree matches what the reader actually did; a page visited twice keeps both branches; Option A becomes the fallback instead of the rule.
- Cons: browsers differ in whether a tab opened from a link inherits the opener's `sessionStorage`; slightly more state to test.
- Driver: tab cursor rules and back and forward handling. Uncertainty: new-tab inheritance across Safari, Chrome and Firefox.

### Option C: Service worker records navigations

**Estimate: 0.75**

`service_worker.js` already sees every page request. It would save each one with the request's referrer as the parent.

- Pros: records pages that load no scripts; one place for capture.
- Cons: no worker over HTTP or on a first visit; pages restored by back and forward are never fetched; `pushState` changes are invisible; titles need parsing from HTML; mixes history with offline caching.
- Driver: filling those gaps with a page script anyway, which turns it into Option B plus a worker.

## Recommendation

**Option B.** It records the tree as the reader experienced it, and it keeps Option A's referrer rule for tabs with no cursor. Option C misses too many page changes to stand alone.

Shared across options and decided in Step 2: a feature flag, how many visits to keep and for how long, a Clear button for shared devices, and where the History page sits in Tools. History stays separate from the opt-in usage log. The usage log strips IDs and can be uploaded; history keeps full addresses and never leaves the device.

**Vertical-slice viability:** a reader opens the Board, opens a thread, goes back, then opens a second thread. Tools → History shows the Board with two child threads, each with its title and time. A second tab opened on Recent starts its own branch. Clearing history empties the page.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*

## Enhancement Requests

- Sync history across a username's devices. Disposition: deferred to a later cycle; Option B's append-only records keep it possible. Approval: noted by the maintainer.
- Count in-page changes, such as drawers and in-page tabs, as visits. Disposition: deferred; the maintainer wants to revisit it later. Approval: noted by the maintainer.
- Tree views and tree bookmarks (`tree_history`) and a filmstrip of recent screens (`filmstrip`). Disposition: separate cycles that build on this one.

## Study Snapshots

2026-11-15T02:44:42Z; scope v1; starting revision `8bec1f7d81c5fe79ff68536a50d1a2748fb553f7`; reference R1, hashes not re-verified. Claude, single exposed rater. A pristine intake is missing: the service worker, usage log recorder, navigation slots and paned readers were read before rating.

- Intake and Step 1 handoff: **0.55 projected total** for Option B with a plain History page. Alternatives A 0.45 and C 0.75, non-additive. Not hours or measured labor; no approval to advance.

Status: Step 1 drafted; awaiting Approved Step 1. Planning records remain uncommitted.
