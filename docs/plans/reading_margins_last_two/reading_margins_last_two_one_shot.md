# Reading margins: the column spans the last two clicks

Cycle: `v3/reading_margins_last_two`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/reading_margins_last_two`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.10**

Status: implemented and verified locally; PR open; next permitted action: maintainer review and merge.

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

Planning commit: `7a0b857` (confirmed with Git before implementation).

## Dated execution history

- 2026-11-14: `AGENTS.md`, the Railspike entrypoint and the one-shot workflow re-read after fetching `main`; no changes since the predecessor cycle. Plan written before any implementation.
- 2026-11-14: Planning commit `7a0b857`; implementation followed.
- 2026-11-14: Implemented as planned. `reading_margins.js` keeps `order` = [older, newer] and resets every radio and the `data-reading-margin-next` outline from it after each click; the partial outlines the older saved radio before paint; `layout.php` accepts any two distinct stops and sorts them for `--reading-left`/`--reading-right`; the CSS outline selector moved from `data-reading-margin-marked` to `data-reading-margin-next`; flag description updated. `tests/LocalAppSmokeTest.php` needed no change (markup unchanged).
- 2026-11-14: Discovery (no scope change): "95 23" is now a valid saved pair (older 95), where #224 ignored it; the browser test covers it.
- 2026-11-14: No new requests during execution.

## Study history

- Intake, 2026-11-14: **0.10**, scope v1, recorded before implementation and after re-reading only the ruler files written in the predecessor cycle (not pristine: same rater wrote them). Baseline `b2c8bf8`; R1 hashes not re-verified. Single exposed rater Claude (claude-opus-5-5).
- Execution entry, 2026-11-14: **0.10** reaffirmed.

- Closeout, 2026-11-14: delivered scope v1 in full; no corrections or additions. Retrospective scope assessment **0.10**. Maintainer comfortable-fit and acceptance pending.

## Outcome

Every click on an unchecked radio moves the column at once; the column spans the last two radios clicked; the older one carries a dashed outline and is the edge the next click replaces, including after reload; clicking either checked radio resets. From the normal column, the first click pairs the clicked radio with the stop nearest the normal column's other side. Limitation: which edge moves follows click order, not side (accepted; the outline is the cue).

## Verification

- `./v3 test --smoke LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnLayoutPagesWhenFlagEnabled FeatureFlagEvaluatorTest`: 452 of 453 passed; `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv` fails the same way on `main` since #219. Full suite not run.
- `node tests/browser/reading_margins.mjs`: all 17 permitted ZenMemes themes at 1280px on a thread (first click from the normal column moves it and outlines the filled-in edge; 41 then 77 each replace the older edge; outline and pair restored on reload and final on first paint; 59–77 gives a centred 20rem column; 5–59; newer and outlined radios both reset; "95 23" restored with 95 outlined; invalid values ignored) and with a saved pair on 13 other pages; phone hides the ruler. Screenshots of the 41–77 state reviewed for every theme.
- Not checked: Safari, real devices, production.

## Final review

Diff limited to the ruler files under reuse decisions plus this record, its index entry, the debug log and the ledger entry. The two-click "marked" state from #224 is removed, not kept alongside. Comments and the flag description describe the new rule.

## Commit/merge/publication status

Planning commit `7a0b857`; completion commit `487bec0` (confirmed with Git); branch `feat/reading_margins_last_two` pushed; PR #236 open against `main`; not merged or deployed.

Debug log: [reading_margins_last_two_debug_log.md](./reading_margins_last_two_debug_log.md)

*Claude (claude-opus-5-5), 2026-11-14 UTC*
