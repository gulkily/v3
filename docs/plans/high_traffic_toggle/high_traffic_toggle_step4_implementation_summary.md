> **Feature plan:** [Step 1](./high_traffic_toggle_step1_solution_assessment.md) · [Step 2](./high_traffic_toggle_step2_feature_description.md) · [Step 3](./high_traffic_toggle_step3_development_plan.md) · [Step 4](./high_traffic_toggle_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/high_traffic_toggle`; [ledger](../effort_estimation_trial.md#cycle-v3high_traffic_toggle). Standing P2 enrollment.
Extended estimates: enabled

**Cycle estimate: 0.20**

Branch: `feat/high-traffic-toggle`. Planning commit: `8742dc96`. Step 3 approved 2026-10-27 by explicit “Approved Step 3”.

## Step 4 entry

2026-10-27 UTC: **0.20 projected total reaffirmed** from Step 3, scope v3, baseline `6e32cd093e76da88820388159de08c95e7bd9a0d`, R1/P2, Claude Code single exposed rater. Stage-work originals copied from Step 3: S1 0.10, S2 0.07 (copies, not new ratings). No implementation yet.

## Stage 1 - Switch row under the Feature Flags heading

**Original estimate: 0.10**
**Retrospective: 0.10**

- Changes:
  - `templates/pages/feature_flags.php`: removed the separate card; one flag-style row (label, staleness line, shared `.switch`, on/off state) sits inside the Feature Flags card beneath the heading and site-wide notice, manager-only as before. No `data-feature-flag-*` attributes.
  - `public/assets/high_traffic_control.js`: updates `aria-checked` and state text instead of button text; failure message shortened to “… Reload, or CLI recovery: ./v3 high-traffic restore”.
  - `public/assets/tool-details.css`: spacing for the standalone row and zero form margin; no new switch style.
  - `tests/browser/high_traffic_control.mjs`: asserts shared switch, state text, row excluded from flag hooks, toolbar “N of N flags shown” counts only flags, search does not hide the row.
- Verification:
  - Passed: `node tests/browser/high_traffic_control.mjs` (real signing enable/disable, pending, failure and keyboard retry, unauthorized rejection, 13 themes at 375/1150px without clipping/overflow); `node tests/browser/feature_flags_toolbar.mjs`; `php tests/run.php HighTrafficTest FeatureFlagEvaluatorTest LocalAppSmokeTest` 174 passed.
  - Failed, pre-existing: `LocalAppSmokeTest::testThreadMergesSameAuthorQuickRepliesIntoContinuations` and `testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`, recorded by the runner as failing 16 runs since 2026-10-24, unrelated to these files.
  - PHP lint, `node --check`, `git diff --check` passed. Desktop Midnight enabled and phone failure screenshots inspected.
- Notes: installed the repository's declared `playwright-core@1.58.1` dev dependency locally (gitignored); no new dependency. Row placed after the site-wide notice, which applies to it too.
- Estimate details: original scope unchanged; no revisions; retrospective is a judgment, not measured effort.

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 2 - Cross-theme verification and records

**Original estimate: 0.07**
**Retrospective: 0.06**

- Changes:
  - No CSS fixes needed; no application change in this stage.
  - `docs/plans/ui_copy_audit_checklist.md`: High-traffic toggle entry.
  - `docs/plans/effort_estimation_trial.md`: cycle index row and closeout entry (accumulated planning updates included).
- Verification:
  - Passed: scratch copy of the browser test (outside the repository) captured the row in light, dark, Word97, Chicago and Midnight for disabled, enabled and failure states at 375px and 1150px; all 30 inspected; switch, state text and error readable, no clipping. The Step 3 plan named 390/1280px; the existing harness widths 375/1150 were used instead.
  - Passed: non-manager view renders no row and `/api/prepare_high_traffic` returns 403 (browser test). No page errors logged.
  - Baseline reproduced: the two `LocalAppSmokeTest` failures from Stage 1 also fail at `6e32cd09` in a temporary worktree.
  - Inapplicable: deployment, Apache, migration — out of scope; no production mode changed.
- Notes: Midnight's disabled state text is low contrast, matching existing flag rows; unchanged.
- Estimate details: original scope unchanged; no revisions.

## Closeout

- Retrospective scope assessment (not a projection or measured effort): **0.20**. Delivered scope v3 as planned in two stages; no corrective rework or new requests during Step 4. Earlier Option C detour happened in planning and is preserved in Step 1/2 history.
- Commits: planning `8742dc96`, Stage 1 `072ad8a2`, Stage 2 (this commit).
- Maintainer comfortable-fit judgment: pending. Study acceptance: pending; no accepted-count increment.

*— Claude Code · 2026-10-27 UTC · Model: claude-opus-5-5*
