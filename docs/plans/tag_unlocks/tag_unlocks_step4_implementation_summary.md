> **Feature plan:** [Step 1](./tag_unlocks_step1_solution_assessment.md) · [Step 2](./tag_unlocks_step2_feature_description.md) · [Step 3](./tag_unlocks_step3_development_plan.md) · [Step 4](./tag_unlocks_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/tag_unlocks`; [host ledger](../effort_estimation_trial.md#cycle-v3tag_unlocks). Standing P2 enrollment.

Extended estimates: enabled (inherited)

Branch: `tag-unlocks`, from `origin/main` at `338215268` (main moved on from the Step 1 starting revision `6d0f9da1`). Planning commit `36cfc6e03`.

**Cycle estimate: 0.65** (Step 4 entry: Step 3 projection reaffirmed after the planning commit)

Stage estimates below are copied from the approved Step 3 plan; copying is not a new rating.

## Stage 1 - Tagset file and loader

**Original estimate: 0.08**

**Retrospective stage work: 0.07**

- Changes:
  - `resources/tagsets.txt`: `Key: value` blocks (Parent, Target, Unlocks, Exclusive) instead of the one-line format sketched in Step 3, following the `AGENTS.md` human-readable-format rule. Ships `meta` → resolved, closed, open, exclusive, on replies.
  - `src/ForumRewrite/Support/Tagsets.php`: `parse`, `fromFile`, `forApplication`, `unlockedFor`, `isUnlockable`, `exclusiveGroupOf`, `exclusiveTags`. Missing file, malformed block, unknown target or empty Unlocks unlock nothing.
  - Feature flag `FORUM_TAG_UNLOCKS_ENABLED` (site-mutable, default on, appended to the Experience group); added to the registry test lists.
  - `tests/TagsetsTest.php`, registered in `tests/run.php`.
- Verification:
  - `./v3 test --smoke TagsetsTest FeatureFlagEvaluatorTest`: 452 run, 452 passed.
  - `./v3 test FeatureFlagsBehaviorTest`: `testRenderedOrderIgnoresRegistryInputOrder` fails with a fatal in `tools_nav_card.php`; it fails the same way on `origin/main` without these changes (baseline failure, not caused here).
  - Full suite not run.
- Notes: the flag is defined but nothing reads it yet; Stages 2 and 5 gate on it.
- Estimate details: original scope unchanged; uncertainty low; no revisions.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 2 - Accept unlocked tags on replies, with repeat marks

**Original estimate: 0.12**

**Retrospective stage work: 0.13**

- Changes:
  - `LocalWriteService::applyPostTag` checks `unlockedPostTag()` before the ordinary tag check. An unlockable tag (with the flag on) is accepted only on a reply whose thread carries a parent tag; the refusal names the parent (`tag closed applies only to replies in threads tagged #meta.`). Thread roots are refused.
  - `threadTagsForUnlocks()` reads the thread's board tags and labels through `FastmodMetaRead::forApplication`, on its own read-model connection, so a Fastmod `meta` counts.
  - Exclusive-group tags skip the one-per-key guard, so Resolved, Open, Resolved writes three records. Replays of one offline intent (`intent_id`, or `Intent-ID` in a signed record) still write once; `hasPostTagFromIdentity` gained an optional intent match. Ordinary tags keep the one-per-key rule.
  - `Tagsets::parentsOf()` for the refusal text.
  - `tests/TagUnlocksTest.php` (registered in `tests/run.php`).
- Verification:
  - `./v3 test TagUnlocksTest TagsetsTest`: all pass (meta-only, roots refused, unknown tag refused, repeat marks, scores unchanged, intent replay, unapproved key accepted with `viewer_is_approved=no`, flag off).
  - First focused run of `WriteApiSmokeTest` found five failures from an eager signed-intent lookup that threw on records without `Intent-ID`; fixed by reading it only for state tags, without throwing. The five reran and passed, plus `ReactionOutboxTest`.
  - `./v3 test --smoke WriteApiSmokeTest`: 660 run, 660 passed. Full suite not run.
- Notes: Scores already ignore non-scored tags in both read-model builders, so no score change was needed. Offline replays are ordered by tap time in Stage 3, so a late resend cannot override a later mark.
- Estimate details: original scope unchanged plus intent-replay dedupe (new understanding, not new scope); uncertainty was medium, resolved.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 3 - Per-reply state in the read model

**Original estimate: 0.12**

**Retrospective stage work: 0.10**

- Changes:
  - Read model schema 55: `posts.resolution_state`, `resolution_set_at`, `resolution_set_by` (`ReadModelSchema`, `ReadModelMetadata`); documented in `docs/specs/read_model_schema_v1.md`.
  - New `ReadModel\PostResolution::derive(records, approvedIds)`: latest exclusive-group mark by an approved key, ordered by Action-At, then Created-At and record ID; `open` stores null.
  - `ReadModelBuilder` (full rebuild) and `IncrementalReadModelUpdater::derivePostReactionState` / `updatePostReactions` fill the columns. The incremental path covers mark writes and `refreshApprovalSensitivePostReactions`, so approval changes recount.
- Verification:
  - `./v3 test TagUnlocksTest`: 8 passed, including latest-wins across incremental writes and a rebuild, a late offline send of an earlier tap not overriding a later mark, a later Open unfolding, an unapproved mark ignored until approval, and a removed latest mark falling back.
  - `./v3 test --smoke` plus read-model classes (`ReadModelPostReactionsTest`, `ReadModelPostRetractionsTest`, `ReadModelSchemaTest`, `ReadModelMetadataTest`, `ReadModelDropSchemaTest`, `ReadModelSignalEligibilityTest`, `ReadModelViewerTagsProjectionTest`, `ReadModelViewerTagsRebuildTest`, `PostSignalsTemplateTest`, `ReadModelBuilderTimingTest`): 463 and 26 run, all passed. Full suite not run.
  - Rebuild timing on a production-sized snapshot: **pending**. No production snapshot is available in this workspace. The added work is one pass over each post's reaction records already loaded, so it should not change rebuild time much; confirm during the Stage 7 deploy.
- Notes: the schema bump forces a rebuild on the next startup after deploy; see Stage 7.
- Estimate details: original scope unchanged; uncertainty medium, reduced to the pending timing check.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*

## Stage 4 - State in the plain-text API

**Original estimate: 0.06**

**Retrospective stage work: 0.06**

- Changes:
  - `ApiTextController`: `get_post` adds `Resolution: resolved|closed` after `Body-Format` for a marked reply; `get_thread` prefixes the reply line with `(resolved) ` or `(closed) ` in the existing marker slot (retracted still wins). Both are hidden when the flag is off.
  - `posts.resolution_state` added to the post selects in `Application::fetchThreadPosts`, `Application::fetchPost` and `ThreadRepository` (also used by the thread page in Stage 5).
  - `LlmsTxtRenderer`: short "Reply resolution" section. `docs/reference/agent_api.md`: "Reply resolution (tag unlocks)" section.
- Verification:
  - `TagUnlocksTest::testTextApiReportsResolution`: header and marker appear after a mark, absent before.
  - `./v3 test --smoke LlmsTxtRendererTest TagUnlocksTest AgentApiWorkedExampleTest PlatformDocsCatalogTest`: 471 run, 471 passed. Full suite not run.
- Notes: open replies get no header, so clients that parse `get_post` headers see no change for ordinary posts.
- Estimate details: original scope unchanged; uncertainty low.

*— Claude · 2026-11-12 UTC · Model: claude-opus-5-5*
