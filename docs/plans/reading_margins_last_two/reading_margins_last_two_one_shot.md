# Reading margins: the column spans the last two clicks

Cycle: `v3/reading_margins_last_two`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/reading_margins_last_two`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.10**

Status: planning; next permitted action: planning commit, then implementation.

Predecessor: [`v3/reading_margins_two_clicks`](../reading_margins_two_clicks/reading_margins_two_clicks_one_shot.md) (merged as #224).

## Original request

After #224 (two clicks set the column; the first click only marks a radio), the maintainer, 2026-11-14: "this has the problem of losing the immediate tactile response on the first click." Claude proposed: the column always runs between the last two radios clicked; each click moves an edge to that radio at once, always replacing the edge that was clicked longer ago, which carries a dashed outline so the reader sees which edge moves next.

## Authorization

Maintainer, 2026-11-14: "yes, please build this version. i've merged all the prs though". This authorizes building that behavior on a new branch from `origin/main` and opening a PR. It does not authorize merging or deployment.

## Outcome and work type

Application change, behind the existing `FORUM_READING_MARGINS_ENABLED` flag. Same six evenly spaced radios (5/23/41/59/77/95vw), any of which can be either edge.

- Every click on an unchecked radio moves the column at once: the edge clicked longer ago (outlined) is replaced by the clicked radio, and the other edge becomes the outlined one.
- From the normal column (no choice yet), the clicked radio becomes one edge immediately; the other edge is the stop nearest the normal column's opposite side (the side away from the click). That filled-in edge is the outlined one, so the next click replaces it.
- Clicking either checked radio returns to the normal column.
- Saved as "older newer" in the same storage key, so the outline survives reload; the pre-paint restore accepts any two distinct stops and sorts them for the column. A "23 95" saved by #224 reads as older 23, newer 95.
- Unchanged: the 20rem minimum column, the pages that show the ruler, phones ignore it.

Excluded: other gestures (drag, pick-up), a visible reset control.

## Approach and risks

- `reading_margins.js` keeps `order` = [older, newer]; the column uses the sorted pair. The handler resets every radio and the outline from that state after each click (radios check themselves before `click` fires).
- The partial's inline script checks both saved radios and outlines the older one before paint, so the outline is right on first paint.
- Risk: the outline on an already-checked radio must be visible in every theme; the dashed outline from #224 already showed in all 17 themes on an unchecked radio. Checked again here on checked radios.
- Risk: which edge moves follows click order, not side; accepted by the maintainer with the outline as the cue.

## Reuse decisions

Extends the existing ruler in place: `templates/partials/reading_margins.php`, `public/assets/reading_margins.js`, `public/assets/reading-margins.css` (outline selector already exists), the pre-paint restore in `templates/layout.php`, the flag description in `FeatureFlagRegistry`, `tests/browser/reading_margins.mjs`, and `tests/LocalAppSmokeTest.php` if its markup assertions change. No new components.

## Completion conditions

On a thread and the other ruler pages, each click moves the column immediately as described; the outline marks the older edge, including after reload; clicking a checked radio resets; a saved pair is in place on first paint; no sideways scroll; all permitted themes; phones unchanged.

Concepts touched: none.

## Verification and recovery plan

- `./v3 test --smoke` plus `LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnLayoutPagesWhenFlagEnabled` and `FeatureFlagEvaluatorTest`. Full suite not run.
- `node tests/browser/reading_margins.mjs` across all permitted themes, rewritten for the last-two rule; screenshots of the outlined state reviewed.
- Reader recovery: clicking a checked radio always returns to the normal column.

Starting revisions and branch: `origin/main` `b2c8bf8`; branch `feat/reading_margins_last_two`.

Planning commit: pending.

## Dated execution history

- 2026-11-14: `AGENTS.md`, the Railspike entrypoint and the one-shot workflow re-read after fetching `main`; no changes since the predecessor cycle. Plan written before any implementation.

## Study history

- Intake, 2026-11-14: **0.10**, scope v1, recorded before implementation and after re-reading only the ruler files written in the predecessor cycle (not pristine: same rater wrote them). Baseline `b2c8bf8`; R1 hashes not re-verified. Single exposed rater Claude (claude-opus-5-5).
- Execution entry, 2026-11-14: **0.10** reaffirmed.

*Claude (claude-opus-5-5), 2026-11-14 UTC*
