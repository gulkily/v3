Cycle: `v3/live_feature_tour_quiet`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/live_feature_tour_quiet` — [host ledger](../effort_estimation_trial.md#cycle-v3live_feature_tour_quiet); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.03**
Status: implemented and verified locally; next permitted action: push and PR (merge and deploy need the maintainer)

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

`3f329a1e` (confirmed with Git).

## Dated execution history

- 2026-11-15: removed the intro paragraph, the Interact hint and "live" from the heading and title; Open links and Look-mode frame clicks navigate in the same tab (no `target="_blank"`, `window.open` or ↗).
- 2026-11-15, scope addition (maintainer, same session, while implementing): "scrolling this page on the phone should be smooth… anytime I put my finger on a screenshot, it scrolls the screenshot instead of the page." Added a transparent cover (`.shield`) over each frame in Look mode; touches land on the page, not the frame. Interact mode removes the cover.
- 2026-11-15, scope addition: "the screenshots are lazy loaded and they should already be loaded in place by the time I scroll to them." Replaced `loading="lazy"` with loading every frame on page load in page order, three at a time (next starts on load or after 8 s); removed the footer note about lazy loading.
- 2026-11-15, scope addition: "They should be in a form factor that most pages fit within a screenshot, at least the parts that we want to show off." Desktop frames changed from 1024×700 to 820×1100 portrait (the site's content column is narrow, so the wide frame was mostly margins); phone frames from 390×760 to 390×844; grid columns from min 420px to 340px.
- 2026-11-15, scope addition: "that mode should be switchable throughout my scrolling page on my phone, and also the look mode does not seem to work right now. The tabs are still scrollable in look mode." The report is against merged #255, where Look mode relied on `pointer-events:none`, which iOS does not honour for iframe scrolling; the cover above addresses it. Moved the Desktop/Phone and Look/Interact toggles out of the header into a sticky bar (blurred background, safe-area top padding) so they stay reachable while scrolling.

## Study history

- Intake 2026-11-15: **0.03**. Exposed (same rater built the predecessor). Rater Claude (claude-opus-5-5).
- Execution entry 2026-11-15: 0.03 reaffirmed.
- Revision 2026-11-15: **0.06** after three in-session additions (touch cover, preloading, frame shape); sticky toggles absorbed without changing the total.
- Closeout 2026-11-15: retrospective scope assessment **0.06**. Maintainer fit/acceptance pending.

## Outcome

Delivered all five requests. Limitation: loading all 16 frames up front is heavier on first visit than lazy loading, by request.

## Verification

- `grep`: no `_blank`, `window.open`, `lazy` or intro copy left in the page.
- Headless Chrome probe at 390×844 on the dev server: `elementFromPoint` over a frame returns the cover in Look mode and the iframe in Interact mode.
- Headless Chrome dump after load without scrolling: all 16 frames have `src` set.
- Renders against production at desktop and phone frame shapes: board, threads, agent, recent, gallery, calendar, tags, users, compose and key pages show their main content within the frame.
- Headless probe at 390×844 scrolled to 3000px: toggle bar top stays at 0.
- Not checked on a real iPhone; the touch fix is verified by hit-testing, not a touch scroll.
- `./v3 test` (smoke set): 464 passed. Full suite not run.

## Final review

Diff is `public/assets/tour.html`, this record, the plans index, ledger entry and debug log. Nothing links to the page.

## Commit/merge/publication status

Planning commit `3f329a1e`; completion commit `952f5fdb` (confirmed with Git). Pushed as `feat/live_feature_tour_quiet`; follow-up commit for the sticky toggles follows; PR opened after. Not merged or deployed.

## Attribution

Claude (claude-opus-5-5), 2026-11-15 UTC.
