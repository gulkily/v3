> **Feature plan:** [Step 1](./zen_strip_step1_solution_assessment.md) · [Step 2](./zen_strip_step2_feature_description.md) · [Step 3](./zen_strip_step3_development_plan.md) · [Step 4](./zen_strip_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/zen_strip`; host ledger [zen_strip.md](../effort_ledger/zen_strip.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.70**

Branch `zen-strip`, based on `origin/main` `eb0f5177`. Planning commits: Steps 1–3 were committed and pushed as they were written (hn_theme precedent), ending with `fc4d13c0` (Step 3 approval). The ledger and debug log went into those commits too, a deviation from the planning-commit rule recorded here. Base smoke set: 478 run, 478 passed.

## Study snapshots

- 2026-11-20 UTC, Step 4 entry: **0.70** projected total, reaffirmed from Step 3. Claude, single exposed rater. Scope v2. Stage originals copied from Step 3 (not new ratings): Stage 1 0.15, Stage 2 0.15, Stage 3 0.15, Stage 4 0.10, Stage 5 0.10.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 1 - Strip query, profile setting and flag

**Original estimate: 0.15**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - `ReadModel/ZenStrip::load(PDO, list<string> $tags, int $limit = 12)`: listed threads carrying every tag, each from an approved member (signed approved author's board tag, or an approved labeler's label still on the thread), newest first.
  - `SiteProfileRegistry`: `zenStripTags` (`['zen', 'meme']` on zenmemes only), `zenStripTags()` and validation.
  - `FeatureFlagRegistry::ZEN_STRIP_ENABLED` (`FORUM_ZEN_STRIP_ENABLED`), on by default, site-mutable, in Experience.
- Verification:
  - `./v3 test --smoke ZenStripTest PromptLibraryTest FeatureFlagsBehaviorTest FeatureFlagEvaluatorTest`: all passed (482 run with the smoke set). The full suite was not run.
  - `ZenStripTest` covers: approved author, approved labels, mixed sources, retired-but-was-approved author; excluded pending labeler, pending author, unsigned post, one tag only, retracted label, retracted thread, retired labeler.
  - On a local import of zenmemes.com (1,942 threads, approvals restored with `./v3 approval suggest-roots`): the query returns the six seed threads, newest first, averaging 4.48 ms over 20 runs.
- Notes:
  - Reuse: follows `PromptLibrary.php`'s approval rule and `ThreadKeysetPage::listedRootsSql()`; no new rule.
  - `FeatureFlagEvaluatorTest` lists every flag, so it gained the new one.
- Estimate details (extended mode only): original scope as Step 3 Stage 1; uncertainty low; no revisions. Retrospective lower because `PromptLibraryTest` gave the fixture shape.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*
