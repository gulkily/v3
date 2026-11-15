Cycle: `v3/live_feature_tour_quiet`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/live_feature_tour_quiet` — [host ledger](../effort_estimation_trial.md#cycle-v3live_feature_tour_quiet); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.03**
Status: planned; next permitted action: implementation

Follow-up to [`v3/live_feature_tour`](../live_feature_tour/live_feature_tour_one_shot.md) (PR #255, merged).

## Original request

2026-11-15, in chat, after #255 merged: "Remove the intro that tells the user their screenshots. I mean, their live site. Let them discover it by themselves. And also, do not open new tabs from the site, from anything that will not obviously open a new tab. Avoid opening new tabs from this page."

## Authorization

That message, read as authority for a one-shot change to `public/assets/tour.html`, a new branch and a new PR. Merge and deploy stay with the maintainer. Still excluded: linking the page from anywhere.

## Outcome and work type

Application change to one static asset. Normal entry: `/assets/tour.html`.

Outcome:
- No intro text saying the frames are the live site: remove the intro paragraph, the "Interact lets you scroll…" hint, and "live" from the heading.
- Nothing on the page opens a new tab: Open links and frame clicks (Look mode) navigate in the same tab; drop `target="_blank"`, `window.open` and the ↗ new-tab glyph.

## Approach and risks

Edit the page in place. Risk: links inside framed pages in Interact mode navigate inside the frame; that is the framed site's own behaviour and not opened by this page, so out of scope.

## Reuse decisions

Same asset and serving route as the predecessor; no new components.

## Concepts touched

None.

## Verification and recovery plan

- `grep` the page for `_blank`, `window.open` and the removed copy.
- Render in headless Chrome against the dev server.
- Smoke set (`./v3 test`); full suite not run.

## Starting revisions and branch

Host `main` at `e9720e4fad2dafccdb85e8c56deda20e880b5683`; branch `feat/live_feature_tour_quiet`.

## Planning commit

Pending.

## Study history

- Intake 2026-11-15: **0.03**. Exposed (same rater built the predecessor). Rater Claude (claude-opus-5-5).
- Execution entry 2026-11-15: 0.03 reaffirmed.
