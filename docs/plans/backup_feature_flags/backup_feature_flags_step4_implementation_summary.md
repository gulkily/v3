> **Feature plan:** [Step 1](./backup_feature_flags_step1_solution_assessment.md) · [Step 2](./backup_feature_flags_step2_feature_description.md) · [Step 3](./backup_feature_flags_step3_development_plan.md) · [Step 4](./backup_feature_flags_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/backup_feature_flags; [ledger](../effort_estimation_trial.md#cycle-v3backup_feature_flags)
Extended estimates: enabled

**Cycle estimate: 0.40**

Entry: 2026-10-27T01:21:05.483282+00:00; Step 3 approved; planning commit 6797de0f. Scope v1/Option A, original baseline 2acea7d8ca05793cef7face657a793f130a76761, R1/P2, single exposed Codex rater. Step 3 projection independently reaffirmed; no measured labor. Stage scopes/uncertainties inherited from Step 3: S1 0.15, S2 0.10, S3 0.10, S4 0.10; non-additive. Prior snapshots preserved. No new dependencies authorized or proposed.

Status: Implementation in progress; four planned stages, zero complete.

## Stage 1 — Text backup control

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes: Default-enabled FORUM_TEXT_BACKUP_ENABLED in existing site controls; category-aware repository/SQLite denial before export/cache access; Backup links and SQLite full/embedded views respect policy. Codebase download links also hidden. Existing site/environment precedence retained; schema registers environment setting with template inclusion deliberately false (site-mutable setting, no private-file override).
- Verification: BackupFeatureFlagTest, FeatureFlagEvaluatorTest, FeatureFlagsBehaviorTest, PrivateConfigSchemaTest and PrivateConfigCommandTest: 34/34 pass; direct tar/ZIP/SQLite denial, warm-cache re-enable and image independence covered. Existing config generation/refresh preserves template/custom values. Diff check passes.
- Notes: Expanded inspection found embedded query and Codebase links; treated as shared-surface wiring within approved text-control scope. FeatureFlagChangePageTest: 1/2 pass, activity-link assertion fails identically in detached baseline 6797de0f. UI/host acceptance remains Stage 4. No dependency/schema change. S1/v1 unchanged, single exposed Codex rater, R1/P2; no measured labor.

*— Codex · 2026-10-27 UTC*
