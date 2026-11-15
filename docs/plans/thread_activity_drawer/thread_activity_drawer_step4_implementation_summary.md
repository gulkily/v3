> **Feature plan:** [Step 1](./thread_activity_drawer_step1_solution_assessment.md) · [Step 2](./thread_activity_drawer_step2_feature_description.md) · [Step 3](./thread_activity_drawer_step3_development_plan.md) · [Step 4](./thread_activity_drawer_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/thread_activity_drawer`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.55**

Branch: `feat/thread-activity-drawer`. Planning commits `0f456b8` (Steps 1–2) and `4007172` (Step 3, approved 2026-11-14T20:04:00Z, "approved step 3").

## Step 4 entry

2026-11-14T20:05:00Z, `railspike-effort` / `v3/thread_activity_drawer`, Step 4 entry. Scope v1, unchanged. Revision `4007172`. R1, P2, single exposed Claude rater. Step 3 projection reaffirmed at **0.55** before implementation. Original stage estimates are copied from Step 3, not re-rated: Stage 1 0.10, Stage 2 0.15, Stage 3 0.20, Stage 4 0.06.

Baseline: `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv` fails in the smoke set on `4007172` before any change. It is unrelated to this cycle.

## Stage 1 - Shared hashtag rule and tag-only test

**Original estimate: 0.10**
**Retrospective: 0.08**

- Changes:
  - New `Support/ThreadLabels.php`: `fromBody()` holds the label rule that `LocalWriteService::extractThreadLabelsFromBody` used, and `isTagOnly()` decides whether a reply says nothing but labels.
  - `LocalWriteService::extractThreadLabelsFromBody` now delegates to `ThreadLabels::fromBody`, so writes and the thread page share one rule.
  - New `tests/ThreadLabelsTest.php`, registered in `tests/run.php`, with the table of reply bodies from Step 3.
- Verification:
  - Passed: `./v3 test ThreadLabelsTest` (2 tests).
  - Passed: `WriteApiSmokeTest::testCreateThreadWithHashtagsWritesThreadLabelRecordAndRendersLabels` and `::testCreateReplyWithHashtagsWritesThreadLabelRecordForTargetThread`. Label writes are unchanged.
  - Smoke set: 454 of 455 passed. The one failure is the baseline failure above, reproduced on `4007172` without this change.
  - Full suite not run, per `AGENTS.md`.
- Notes:
  - Events live on the `threads` row, so a reply cannot carry one. `thread_type` covers typed roots. The plan's "event" check therefore needs no extra field.
  - `#Bug` and `#bug, #ux` count as text. The first is not a valid label; the comma is left over.
- Estimate details: original scope unchanged; no revision. Retrospective: a smaller refactor than expected, because the rule moved without changes.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 2 - Flag and tag-only leaf filter

**Original estimate: 0.15**
**Retrospective: 0.12**

- Changes:
  - New flag `FORUM_THREAD_ACTIVITY_DRAWER_ENABLED` ("Thread activity drawer"), off by default, site-mutable, appended to the `RENDERING` group per `CONTRIBUTING.md`. Added to the env-reset lists in `FeatureFlagEvaluatorTest`.
  - New `ReadModel/TagOnlyLeafFilter.php`: `split()` returns kept and moved replies. It follows `RetractedLeafFilter`: only unanswered replies move, and it repeats so a chain of tag-only replies goes whole.
  - `ThreadAndPostPageController::thread` passes `threadActivityDrawerEnabled`. `templates/pages/thread.php` splits replies after `RetractedLeafFilter` and before continuation flags; the moved replies wait in `$movedTagOnlyReplies` for Stage 3.
  - Tests: new `TagOnlyLeafFilterTest` (registered in `tests/run.php`); new `WriteApiSmokeTest::testThreadActivityDrawerFlagMovesTagOnlyLeavesOutOfTheConversation`.
- Verification:
  - Passed: `TagOnlyLeafFilterTest`, `RetractedLeafFilterTest`, `PostSignalsTemplateTest`, `FeatureFlagEvaluatorTest` (23 tests) and the new page test.
  - The page test covers: flag off shows every reply; flag on removes the unanswered tag-only reply; an answered tag-only reply and its answer stay; the reply after the moved one is still a continuation.
  - Smoke set: 452 of 453 passed; the one failure is the baseline failure.
  - Full suite not run, per `AGENTS.md`.
- Notes:
  - Between Stage 2 and Stage 3, turning the flag on hides tag-only leaves with nowhere to show them. The flag is off by default and the branch is not merged, so no reader sees this.
  - The root card's reply count now counts shown replies only, as planned.
- Estimate details: original scope unchanged; no revision.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 3 - The Activity drawer

**Original estimate: 0.20**
**Retrospective: 0.18**

- Changes:
  - New `View/ThreadActivity.php`. `build()` makes one "tagged" entry per moved reply, then one group per post with likes or flags, in thread order. The root group uses the thread likes and the root post's flags, as the root signal line does. `partition()` runs `TagOnlyLeafFilter` and `build()`, and on any failure returns every reply and no entries.
  - New partial `templates/partials/thread_activity_drawer.php`: a collapsed `<details class="card thread-activity" id="activity">` with "Activity (N)" and a list. Profile links follow `post_signals.php`; dates use the existing `$shortDate` helper.
  - `templates/pages/thread.php` calls `partition()` when the flag is on and renders the drawer after the last reply, before the reply box, only when it has entries.
  - The controller passes `postRecordsEnabled`, so groups link to `/posts/<id>#records` when Post records is on.
  - `public/assets/site.css`: two rules for the list, next to the Records styles. No theme-specific CSS was needed.
  - Tests: new `ThreadActivityTest` (registered); new `PostSignalsTemplateTest::testThreadActivityDrawerListsTagOnlyRepliesAndTheSignalLineActors`; the Stage 2 page test now matches reply cards by `<article>`, because drawer entries also carry `data-post-id`.
- Verification:
  - Passed: `ThreadActivityTest`, `PostSignalsTemplateTest`, `TagOnlyLeafFilterTest`, `ThreadLabelsTest`, `FeatureFlagEvaluatorTest`, `AssetFingerprintTest` and the Stage 2 page test (34 tests).
  - Covered: entry order and counts; both link forms; the drawer after the last reply and before the composer; no drawer when empty or with the flag off; a forced builder failure keeps every reply and shows no drawer.
  - The drawer's names match the signal lines: the page test reads the same approved actor from both. The browser check showed the same names in the drawer and on the reply card.
  - Browser check in headless Chrome with a local fixture (two approved actors, two thread likes, a like and a flag on a reply, one tag-only reply): Word97, Gloss and Bubblegum each show the drawer as a normal card, the open list reads cleanly, and the tag-only reply has no card. The scratch script and fixture stayed outside the repository; `playwright-core` 1.58.1 (the version `package.json` already pins) was installed in a scratch folder.
  - Smoke set: 452 of 453 passed; the one failure is the baseline failure.
  - Full suite not run, per `AGENTS.md`.
- Notes:
  - Likes or flags on a moved tag-only reply appear as a "Reply by e" group, so no reaction goes missing.
  - A tag-only reply by a guest reads "guest tagged …". That is the same author label the reply card would show.
  - Forte, static detail pages and the post page are unchanged, as scoped.
- Estimate details: original scope unchanged; no revision.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 4 - Concepts and final checks

**Original estimate: 0.06**
**Retrospective: 0.05**

- Changes:
  - New concept entry [thread activity](../../concepts/thread_activity.md).
  - [Post presentation](../../concepts/post_presentation.md) gains one dated "Activity" line pointing to it.
  - This summary's closeout, and the ledger row for `v3/thread_activity_drawer`.
- Verification:
  - Concept source paths resolve (checked with `test -e` for each linked file).
  - Final diff review of `4007172..HEAD`: 17 files, all within the plan. No unauthorized scope. One small duplication: the drawer partial has its own profile-link closure, matching the one in `post_signals.php`. Both are four lines; sharing them would mean a new helper for two callers, so I left it.
  - `git diff --check` passed for every stage commit.
- Notes:
  - No feedback item matches this cycle. `PF-20261104-F76` is about hashtag-only title lines, a different feature, so there is no `Feedback:` trailer.
- Estimate details: original scope unchanged; no revision.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Closeout

Status against the Step 3 Completion Contract:

| Check | Status |
| --- | --- |
| Normal entry: `/threads/<id>` with the flag on | Passed locally (page tests and headless Chrome) |
| End-to-end outcome: tag-only leaves move into the drawer; likes and flags listed by post with links | Passed locally |
| Recovery: builder failure renders every reply and no drawer | Passed (`ThreadActivityTest::testFailureKeepsEveryReplyAndNoEntries`) |
| Flag off: thread page unchanged | Passed (page tests show every reply card and no drawer) |
| Themes: Word97, Gloss, Bubblegum | Passed (screenshots, open drawer) |
| Deployment: no schema change, rebuild or new dependency | Passed by inspection |
| Release condition: deployed with the flag on and checked on a real thread | **Pending**, for the maintainer after merge |
| Full test suite | Not run, per `AGENTS.md` |
| Baseline smoke failure `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv` | Reproduced on `4007172` before any change; unrelated |

Commits: planning `0f456b8` and `4007172`; stages `0e8fd1e`, `f1bbd33`, `b5e2c1d` and the Stage 4 commit. Six commits for four stages meets the `1 + stages` minimum.

## Study closeout

2026-11-14T20:40:00Z, `railspike-effort` / `v3/thread_activity_drawer`, Step 4 closeout. **Retrospective scope assessment: 0.50** (separate from the 0.55 projection; not a measured actual). Delivered scope v1 as approved, with no corrective work or new requests. Starting revision `68724f9` (Step 1); final revision is the Stage 4 commit on `feat/thread-activity-drawer`. Planned and completed stages: 4 of 4. Verification and omissions as in the Closeout table: production check pending, full suite not run. R1, P2, single exposed Claude rater. Maintainer comfortable-fit judgment: **pending**.

## Merge and baseline test fix (2026-11-15)

- Merged `origin/main` (`bd2b0fa`) into the branch before opening the PR. Conflicts were additive, and both sides were kept: the flag constants (`PENDING_CHIP_APPROVAL_ENABLED`, `USAGE_REPORTS_ENABLED`, then `THREAD_ACTIVITY_DRAWER_ENABLED`), the plans index and the ledger's cycle sections.
- Fixed the baseline failure `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`, as the maintainer asks for pre-existing failures. Cause: the test expects a media-embed card, but `FORUM_MEDIA_EMBEDS_ENABLED` is off by default and the test never turned it on, so it passed only where the environment had the flag on. It now sets the flag itself and restores it afterwards, as `MediaEmbedBeaconTemplatesTest` does. Product code is unchanged.
- Verification after the merge: smoke set plus this cycle's tests, 475 of 475 passed. Full suite not run, per `AGENTS.md`.
