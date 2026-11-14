# Reading margins: the nearer edge moves

Cycle: `v3/reading_margins_nearest_edge`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/reading_margins_nearest_edge`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.10**

Status: implemented and verified locally; PR #239 open; next permitted action: maintainer review, merge and a try in Firefox.

Predecessor: [`v3/reading_margins_last_two`](../reading_margins_last_two/reading_margins_last_two_one_shot.md) (merged as #236).

## Original request

The maintainer, 2026-11-14, after using #236: "most of the time it does what I expect it to, but sometimes it does what I didn't expect it to ... usually when the window flips to the other side." He recorded a 42-second session and asked for a click-by-click analysis without a fix.

Analysis (Claude, in session): 18 clicks; 5 clear mismatches, 1 tie. Every mismatch was a click nearer the solid (newer) edge than the outlined (older) edge, so the far edge moved: it jumped over the near edge and flipped the column (2 clicks), slid the column (2), or pulled the column toward the far side (1). In each case the reader was adjusting the edge they had just placed. "Move the edge nearer the click" matched all 17 clicks that weren't ties. #214 tried nearest-edge before and was rejected for guessing wrong; the one tie in this session was inside the column. So the proposal is nearest edge with a click-order tie-break.

## Authorization

Maintainer, 2026-11-14: "I think you're on the right track here. Please try it, period. The one other thing that I think we should try to avoid is flipping the window over the other edge. So if we're like clicking to the left of the window, what I don't expect to happen is for the right edge of the window to move over the left edge and become the left edge."

This authorizes building the nearest-edge rule with the older-edge tie-break, plus the rule that edges never cross, on a new branch from `origin/main`, and opening a PR. It does not authorize merging or deployment. Excluded: drag, pick-up, a reset control, and changes to stops, the 20rem minimum, pages or phones.

## Outcome and work type

Application change, behind the existing `FORUM_READING_MARGINS_ENABLED` flag.

- Clicking an unchecked radio moves the column edge nearer to it, measured in stops. A click outside the column always moves the edge on that side, so the column never flips. A click inside the column moves the nearer edge inward.
- Tie (the click is exactly midway between the edges, which only happens inside the column): the edge clicked longer ago moves, as in #236. Either edge moving inward can't cross the other.
- The dashed outline marks the edge a tie would move. It shows only when a tie is possible, that is, when a stop sits exactly midway between the two edges. Otherwise no edge has the outline, because a click always moves the nearer edge.
- Unchanged: the first click from the normal column (pairs with the stop nearest the normal column's opposite side), clicking a checked radio resets, storage as "older newer" in the same key, the pre-paint restore, the 20rem minimum, the pages and phones.

## Approach and risks

- `reading_margins.js`: in the click handler, compare stop indexes of the click and the two edges; replace the nearer edge, or `order[0]` on a tie. The new order is `[kept edge, clicked]`, so click order is still recorded for the next tie. `apply()` sets the outline on `order[0]` only when the two edges' stop indexes differ by an even number (a midpoint stop exists).
- `reading_margins.php` inline script: same outline condition before paint.
- Risk: the #214 rejection could come back. Mitigation: ties use click order, and the maintainer will try it with the same kind of session. Residual risk accepted by the maintainer ("try it").
- Risk: the outline appearing and disappearing could puzzle readers. It only appears when it means something. Keeping it always on would mark an edge that ordinary clicks don't move, which is the mismatch being fixed.

## Reuse decisions

Extends the existing ruler in place: `public/assets/reading_margins.js`, `templates/partials/reading_margins.php`, the comments in `public/assets/reading-margins.css`, and the flag description in `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php`. `templates/layout.php`'s pre-paint restore is unchanged (storage format unchanged). `tests/browser/reading_margins.mjs` gets the new rule plus the recorded session's mismatch sequences. No new components.

## Completion conditions

On a thread and the other ruler pages: a click outside the column moves the edge on that side; a click inside moves the nearer edge; ties move the older edge; the edges never cross; the outline shows only when a tie is possible and marks the edge a tie would move, including after reload and on first paint; clicking a checked radio resets; all permitted themes; phones unchanged.

Concepts touched: none.

## Verification and recovery plan

- `./v3 test --smoke LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnLayoutPagesWhenFlagEnabled FeatureFlagEvaluatorTest`. Full suite not run.
- `node tests/browser/reading_margins.mjs` across all permitted themes, with the recorded session's 18 clicks replayed and a no-crossing check after every click; screenshots reviewed on desktop and phone.
- Reader recovery: clicking a checked radio still returns to the normal column.
- Reported condition: the maintainer's Firefox session. Replayed in Chromium only; Firefox and production stay unverified until the maintainer tries it.

Starting revisions and branch: `origin/main` `a4fe61e`; branch `feat/reading_margins_nearest_edge`.

Planning commit: `c344c2b` (confirmed with Git before implementation).

## Dated execution history

- 2026-11-14: `AGENTS.md`, `RAILSPIKE.md`, the one-shot workflow and the shared implementation standards re-read after cloning `main`. No matching item in `docs/feedback/items/`. Plan written before any implementation.
- 2026-11-14: Planning commit `c344c2b`; plan shared in session; implementation followed.
- 2026-11-14: Implemented as planned. `reading_margins.js` compares stop indexes and replaces the nearer edge (the older on a tie), keeping `[kept, clicked]` as the new click order; `apply()` outlines `order[0]` only when the edges' stop indexes differ by an even number. The partial's pre-paint script uses the same condition. CSS comments and the flag description updated. `templates/layout.php` and `tests/LocalAppSmokeTest.php` needed no change (storage and markup unchanged).
- 2026-11-14: Browser test updated: the first-click and click-sequence steps now expect nearer-edge moves and the conditional outline; the 5 click becomes 5–77 (was 5–59); the reset step clicks the filled-in edge instead of the outlined one, which may now have no outline. Added the recording's five mismatches as before/click/after cases, its tie, and a replay of all 18 click positions checking that an outside click moves the edge on its side. Against #236's script the updated test fails at the first new expectation, as intended.
- 2026-11-14: No new requests during execution.

## Study history

- Intake, 2026-11-14: **0.10**, scope v1, recorded before implementation, after the click analysis and reading the ruler files (not pristine: same rater wrote the predecessors). Baseline `a4fe61e`; R1 hashes not re-verified. Single exposed rater Claude (claude-opus-5-5).
- Execution entry, 2026-11-14: **0.10** reaffirmed.
- Closeout, 2026-11-14: delivered scope v1 in full; no corrections or additions. Retrospective scope assessment **0.10**. Maintainer comfortable-fit and acceptance pending.

## Outcome

Clicking an unchecked radio moves the column edge nearer to it, so a click outside the column moves the edge on that side and the column never flips; a click exactly midway moves the edge clicked longer ago. The dashed outline marks that edge and shows only when a midway stop exists. Clicking either checked radio resets. Limitation: whether the click-order tie-break avoids the guesses that got #214 rejected is untested until the maintainer tries it.

## Verification

- `./v3 test --smoke LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnLayoutPagesWhenFlagEnabled FeatureFlagEvaluatorTest`: 461 of 462 passed; `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv` fails the same way on `main` since #219. Full suite not run.
- `node tests/browser/reading_margins.mjs`: passed in all 17 permitted themes at 1280px on a thread, 13 other pages, and phone, including the recorded session's cases. Screenshots of the outlined 41–77 state reviewed for every theme; the 5–77 state and phone reviewed in Bubblegum.
- Not checked: Firefox (the maintainer's browser in the recording), Safari, real devices, production.

## Final review

Diff limited to the ruler files under reuse decisions plus this record, its index entry, the debug log and the ledger entry. No new components; the outline attribute and CSS are reused. Comments and the flag description describe the new rule.

## Commit/merge/publication status

Planning commit `c344c2b`; completion commit `9246819` (confirmed with Git); branch `feat/reading_margins_nearest_edge` pushed; PR #239 open against `main`; not merged or deployed.

Debug log: [reading_margins_nearest_edge_debug_log.md](./reading_margins_nearest_edge_debug_log.md)

*Claude (claude-opus-5-5), 2026-11-14 UTC*
