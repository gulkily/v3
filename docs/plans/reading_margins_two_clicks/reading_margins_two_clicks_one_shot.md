# Reading margins: two clicks set the column

Cycle: `v3/reading_margins_two_clicks`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/reading_margins_two_clicks`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.10**

Status: implemented and verified locally; PR open; next permitted action: maintainer review and merge.

## Original request

The reading margins ruler (merged in #182, #214, #215, #221; history in [reading_margins.md](../reading_margins.md)) is a row of six evenly spaced radios, the left three setting the column's left edge and the right three its right edge. The maintainer, 2026-11-14: "i still would like to somehow arbitrarily choose right and left... but before it would not get which one i wanted. how do we fix?" (#214 had moved whichever edge was nearer the clicked radio, and that guess often missed.) Claude offered three approaches: (1) two clicks set the column, in either order; (2) click a checked radio to pick up an edge, then click where to put it; (3) drag between two radios.

## Authorization

Maintainer, 2026-11-14: "do 1 alone please". This authorizes building approach 1 on a new branch from `origin/main` and opening a PR. It does not authorize merging or deployment.

## Outcome and work type

Application change, behind the existing `FORUM_READING_MARGINS_ENABLED` flag.

- One row of six evenly spaced radios (5/23/41/59/77/95vw) with no left or right groups. Any radio can be either edge.
- First click marks a radio (dashed outline) and leaves the column as it is. Second click on another radio sets the column between the two, in either order. The next click starts a new pair.
- Clicking the marked radio again returns to the site's normal column (the reset gesture; previously "click a checked radio").
- Saved as "L R" in the same storage key; `layout.php` accepts any two stops in increasing order, so a "23 95" saved by the split ruler still applies.
- Unchanged: the 20rem minimum column, the pages that show the ruler, phones ignore it.

Excluded: approaches 2 and 3; keyboard-specific handling beyond what radios give; a visible reset control.

## Approach and risks

- Each radio gets its own `name` so two can be checked at once; a click handler owns all checked state (radios check themselves before `click` fires, so the handler resets every radio from its state).
- Risk: with one radio marked, the radios no longer show the current column's edges until the second click. Accepted: the dashed outline shows a pair is in progress, and the column itself is unchanged.
- Risk: a marked radio is lost on reload (not saved). Accepted; only complete pairs are saved.

## Reuse decisions

Extends the existing ruler in place: `templates/partials/reading_margins.php`, `public/assets/reading_margins.js`, `public/assets/reading-margins.css`, the pre-paint restore in `templates/layout.php`, the flag description in `FeatureFlagRegistry`, `tests/LocalAppSmokeTest.php` and `tests/browser/reading_margins.mjs`. No new components.

## Completion conditions

On a thread page and the other ruler pages, any two radios clicked in either order give a column between them; the marked state is visible; clicking the marked radio resets; a saved pair is in place on first paint; nothing scrolls sideways; all permitted themes; phones unchanged.

Concepts touched: none (no concept inventory entry for reading margins; none proposed).

## Verification and recovery plan

- `./v3 test --smoke` plus `LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnLayoutPagesWhenFlagEnabled` and `FeatureFlagEvaluatorTest`. Full suite not run (maintainer runs it).
- `node tests/browser/reading_margins.mjs` across all permitted themes, updated for pairs in both orders, the marked state, reset, and restore before paint; screenshots reviewed.
- Recovery for a reader: clicking the marked radio again, or completing any pair, always reaches a defined column; clearing site data returns to the normal column.

Starting revisions and branch: `origin/main` `68724f9` (merge of #221); branch `feat/reading_margins_two_clicks`.

Planning commit: `ac7a9ad` (confirmed with Git before implementation was restored to the branch).

## Dated execution history

- 2026-11-14: Chronology disclosure. Claude began this follow-up under the older "small changes can proceed directly" guidance and drafted the partial, script and CSS changes before re-reading `AGENTS.md`, which on current main requires a Railspike cycle for every committed change. On noticing, the drafts were set aside (git stash) so this plan is committed before any implementation enters the branch. The intake checkpoint is therefore missing; the estimate below is the execution-entry projection.
- 2026-11-14: Planning commit `ac7a9ad` made with only this record and its index entry; the drafts were then restored from the stash and finished.
- 2026-11-14: Implemented as planned. `reading_margins.js` keeps two pieces of state, the column's edges and the marked radio; its click handler resets every radio from them. The partial lists six stops in one fieldset with a radio name per stop; `layout.php` accepts any two stops in increasing order; the CSS adds a dashed outline for `data-reading-margin-marked`; the flag description now says the column goes between the two radios a reader clicks.
- 2026-11-14: Discovery (no scope change): a "23 95" saved by the split ruler is still a valid pair, so it keeps applying; "95 23" and values from older rulers are ignored. Recorded in the browser test.
- 2026-11-14: No new requests during execution.

## Study history

- Intake: missing (implementation drafted before this record; see chronology disclosure).
- Execution entry, 2026-11-14: **0.10**, scope v1. Baseline `68724f9`; R1 hashes not re-verified. Single exposed rater Claude (claude-opus-5-5), who wrote the earlier ruler versions.

- Closeout, 2026-11-14: delivered scope v1 in full; no corrections or additions. Retrospective scope assessment **0.10**. Maintainer comfortable-fit and acceptance pending.

## Outcome

Any two radios clicked in either order set the column between them; the first click marks its radio with a dashed outline and leaves the column alone; clicking the marked radio again resets; complete pairs are saved and restored before paint. The 20rem minimum, page coverage and phone behavior are unchanged. Limitations: a pair in progress is not saved across reload; while a radio is marked the radios do not show the current column's edges.

## Verification

- `./v3 test --smoke LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnLayoutPagesWhenFlagEnabled FeatureFlagEvaluatorTest`: 452 of 453 passed. The one failure, `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`, fails the same way on `main` since #219. Full suite not run.
- `node tests/browser/reading_margins.mjs`: all 17 permitted ZenMemes themes at 1280px on a thread (one row, equal gaps, no left/right groups; first click marks and leaves the column; second click right-to-left sets 23–95 within 2px; restore before paint; 59–77 gives a centred 20rem column; 5–59; a new pair keeps the column until completed; clicking the marked radio resets; invalid saved values ignored) and with a saved pair on 13 other pages; phone hides the ruler. Marked-state and pair screenshots reviewed for every theme.
- Not checked: Safari, real devices, keyboard-only use beyond native radio behavior, production.

## Final review

Diff limited to the seven ruler files listed under reuse decisions plus this record, its index entry, the debug log and the ledger entry. No duplicated components; the older left/right code paths are removed rather than kept alongside. Comments and the flag description describe the new behavior.

## Commit/merge/publication status

Planning commit `ac7a9ad`; completion commit `3f7ab45` (confirmed with Git); branch `feat/reading_margins_two_clicks` pushed; PR #224 open against `main`; not merged or deployed.

Debug log: [reading_margins_two_clicks_debug_log.md](./reading_margins_two_clicks_debug_log.md)

*Claude (claude-opus-5-5), 2026-11-14 UTC*
