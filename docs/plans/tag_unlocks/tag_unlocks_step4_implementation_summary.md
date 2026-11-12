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
