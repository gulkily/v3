> **Feature plan:** [Step 1](./theme_directive_step1_solution_assessment.md) · [Step 2](./theme_directive_step2_feature_description.md) · [Step 3](./theme_directive_step3_development_plan.md) · [Step 4](./theme_directive_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/theme_directive`; [host ledger](../effort_estimation_trial.md#cycle-v3theme_directive). Standing P2 enrollment. Builds on `v3/engagify_theme`.

Extended estimates: enabled (inherited)

Cycle estimate: 0.75

Branch `feat/theme_directive`, from `feat/engagify_theme` (unmerged; this cycle depends on its page themes). Planning commit `bcf05898`. Earlier Step 1–3 drafts stay on `plan/theme_directive`.

## Step 4 entry

2026-11-07T15:20:40Z, Step 3 explicitly approved ("approved step 3"). Step 3 projection **0.75** reaffirmed at entry; scope v2 unchanged. Stage identities and original stage-work estimates copied from the Step 3 plan, not re-rated: Stage 1 0.15, Stage 2 0.07, Stage 3 0.15, Stage 4 0.10, Stage 5 0.12, Stage 6 0.06. R1/P2, single exposed Claude rater.

## Stage 1 - Visible `theme:` line sets the thread's theme

**Original estimate: 0.15**
**Retrospective: 0.12**

- Changes:
  - New `Support/ThemeDirective`: reads a whole unquoted `theme: <name>` line; `none` clears.
  - New `View/ThreadTheme::forThread()`: latest valid line from an unretracted, verified post by the thread's verified author or a current operator; names the site does not permit are skipped, so the earlier setting stays.
  - `ThreadAndPostPageController` passes `threadTheme`; `TemplateRenderer` uses it ahead of the site profile's page theme list, with the existing permitted-theme check.
  - New `tests/ThreadThemeTest.php`, registered in `tests/run.php`.
- Verification:
  - `php tests/run.php ThreadThemeTest`: 3 passed (directive parsing; author, operator, other user, `none`, unknown name, retraction, unverified; precedence over the site list and a foreign site's theme).
  - `php tests/run.php --smoke ThreadThemeTest EventStatusTest SiteProfileRegistryTest --jobs=4`: 451 run, 450 passed. The one failure, `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, also fails on the base branch with this stage stashed; recorded by the runner as long-standing.
  - Full suite not run, per project practice.
- Notes:
  - Authority is judged on read, like `event:`, so a theme set by someone who stops being an operator stops applying.
  - The visible line stays in the post as written.
- Estimate details: original scope as planned; uncertainty low; no revisions. Retrospective: smaller than planned because the renderer already had the page-theme path; measured labor unknown.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Stage 2 - Baked thread pages follow theme changes

**Original estimate: 0.07**
**Retrospective: 0.04**

- Changes:
  - No application change was needed. Every reply path already drops its thread's baked page (`invalidateReply()` in `LocalWriteService` and the signed-post path), and retraction drops all generated pages (`invalidateRetractedPostArtifacts()`).
  - `ThreadThemeTest::testBakedThreadPageFollowsAThemeReply`: bakes a thread, posts a theme reply, checks the baked file is gone, rebuilds and reads `data-page-theme`.
- Verification:
  - `php tests/run.php ThreadThemeTest`: 4 passed.
  - Retraction invalidation confirmed by reading the code, not by a new test; signing a retraction in a test needs the prepare/finalize flow, which existing retraction tests already cover.
- Notes:
  - As with `event:`, a baked page does not change when someone stops being an operator; it updates on the thread's next write or rebuild.
- Estimate details: original scope as planned; uncertainty low; no revisions. Retrospective: tracing only, no code change; measured labor unknown.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
