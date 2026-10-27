> **Feature plan:** [Step 1](./backup_feature_flags_step1_solution_assessment.md) · [Step 2](./backup_feature_flags_step2_feature_description.md) · [Step 3](./backup_feature_flags_step3_development_plan.md) · [Step 4](./backup_feature_flags_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/backup_feature_flags; [ledger](../effort_estimation_trial.md#cycle-v3backup_feature_flags)
Extended estimates: enabled

**Cycle estimate: 0.40**

Entry: 2026-10-27T01:21:05.483282+00:00; Step 3 approved; planning commit 6797de0f. Scope v1/Option A, original baseline 2acea7d8ca05793cef7face657a793f130a76761, R1/P2, single exposed Codex rater. Step 3 projection independently reaffirmed; no measured labor. Stage scopes/uncertainties inherited from Step 3: S1 0.15, S2 0.10, S3 0.10, S4 0.10; non-additive. Prior snapshots preserved. No new dependencies authorized or proposed.

Status: Local implementation complete; four planned/four complete stages. Production deployment and maintainer acceptance pending.

## Stage 1 — Text backup control

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes: Default-enabled FORUM_TEXT_BACKUP_ENABLED in existing site controls; category-aware repository/SQLite denial before export/cache access; Backup links and SQLite full/embedded views respect policy. Codebase download links also hidden. Existing site/environment precedence retained; schema registers environment setting with template inclusion deliberately false (site-mutable setting, no private-file override).
- Verification: BackupFeatureFlagTest, FeatureFlagEvaluatorTest, FeatureFlagsBehaviorTest, PrivateConfigSchemaTest and PrivateConfigCommandTest: 34/34 pass; direct tar/ZIP/SQLite denial, warm-cache re-enable and image independence covered. Existing config generation/refresh preserves template/custom values. Diff check passes.
- Notes: Expanded inspection found embedded query and Codebase links; treated as shared-surface wiring within approved text-control scope. FeatureFlagChangePageTest: 1/2 pass, activity-link assertion fails identically in detached baseline 6797de0f. UI/host acceptance remains Stage 4. No dependency/schema change. S1/v1 unchanged, single exposed Codex rater, R1/P2; no measured labor.

*— Codex · 2026-10-27 UTC*

## Stage 2 — Image backup control

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes: Independent default-enabled FORUM_IMAGE_BACKUP_ENABLED, site/environment control and non-template schema registration. Image archive gate applies before revision checks/cache lookup; retained-image availability remains separate from image authoring. Image-only warning identifies the matching-content requirement.
- Verification: BackupFeatureFlagTest, FeatureFlagEvaluatorTest, PrivateConfigSchemaTest and PrivateConfigCommandTest: 33/33 pass. Four combinations, warm archives, revision bypass denial, re-enable and retained PNG bytes with authoring disabled covered. Initial test used an invalid image-store fixture; replaced it with a valid stored PNG and reran successfully.
- Notes: Stage 1 commit 74ff9dc0. S2/v1 unchanged, single exposed rater/R1/P2; no measured labor. UI/host acceptance remains pending. No dependencies or schema changes.

*— Codex · 2026-10-27 UTC*

## Stage 3 — Shared navigation and cache transitions

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes: Backup launcher/subnavigation hide only when both flags are off; all bookmarked aliases render unavailability. Existing flag-navigation response refreshes Tools subnavigation after a signed save. HTML carries the two effective policy bits; static and high-traffic cached pages are admitted only for matching policy. Legacy HTML without markers is compatible only when both flags remain at the legacy enabled defaults; cache-lock contention cannot return a mismatched page.
- Verification: BackupFeatureFlagTest, FeatureFlagsBehaviorTest, HighTrafficTest and HighTrafficPublicationTest: 17/17 pass. Includes aliases, launcher, navigation API, actual FrontController static hit/bypass/re-enable and locked high-traffic stale-page refusal. Fixed test setup to use the host's active-release directory. Existing signed feature writes already withdraw high-traffic publication and invalidate static artifacts; retained that mechanism.
- Notes: Stage 2 commit d40980d4. S3/v1 unchanged, single exposed rater/R1/P2; no measured labor. Browser/theme and signed operator acceptance follow in Stage 4. No new/renamed navigation destination or icon mapping; no dependency delta.

*— Codex · 2026-10-27 UTC*

## Stage 4 — Acceptance and operator handoff

**Original estimate: 0.10**
**Retrospective stage work: 0.15**

- Changes: Added real signed-operator browser acceptance, private-site denial regression, operator runbook and UI audit. Browser acceptance found and corrected the missing backup-flag navigation-refresh trigger; final review guarded the disabled embedded query dialog against an absent viewer. Existing messaging navigation behavior remains separately checked. Updated plan navigation and accumulated study ledger.
- Verification: Focused PHP batch 61/61 (backup, flags, configuration, high-traffic, archives and images), then strengthened final backup/behavior checks 8/8 and affected LocalAppSmokeTest checks 7/7. Actual Chromium/HTTP backup acceptance passes 240 states across six themes and two widths, all four flag combinations plus restored defaults, signed prepare/finalize, anonymous write denial, download headers/no bytes on 403, matching-backup warning, live Tools visibility, SQLite/database and ZIP-link recovery, expanded disabled dialog with zero database requests, no JS errors and no horizontal overflow. Existing listing_query_dialog, feature_flags_toolbar, private_message_live_navigation and archive_export_browser checks all pass, including pending/failure/retry, keyboard/phone, cache corruption and archive extraction. Backup icon computed backgrounds/dimensions checked for Word97/Chicago/Midnight in launcher and subnavigation; Midnight phone image-only/unavailable screenshots inspected.
- Evidence: /tmp/backup-final-php.log, /tmp/backup-final-corrections.log, /tmp/backup-smoke.log, /tmp/backup-browser.log, /tmp/backup-query-browser.log, /tmp/backup-toolbar-browser.log, /tmp/backup-nav-browser.log, /tmp/backup-archive-browser.log. Final browser artifacts: /tmp/backup-flags-browser-aljiSq; archive artifacts: /tmp/archive-export-browser-NFb5JF. Temporary evidence is local, not uploaded.
- Notes: Stage 3 commit 41244f96. Original S4/v1 0.10 preserved; retrospective 0.15 reflects shared browser acceptance and ordinary integration corrections, not measured labor or new scope. No packages, manifests, lockfiles, vendored assets, read-model schema or canonical formats changed. Existing key backups, restore/import, filesystem backup and ordinary/offline reading remain outside these controls. todo.txt untouched.

*— Codex · 2026-10-27 UTC*

## Study closeout and release limits

**Original cycle estimate: 0.40**
**Retrospective cycle scope: 0.45**

Study: railspike-effort / v3/backup_feature_flags; scope v1/Option A, original baseline 2acea7d8ca05793cef7face657a793f130a76761, R1/P2; exact frozen hashes reverified. Single exposed Codex rater, no independent observation or measured active labor. Original 0.40 projections and S1–S4 original stage values 0.15/0.10/0.10/0.10 retained; retrospectives 0.15/0.10/0.10/0.15 are non-additive. Additional shared-surface acceptance/correction explains the independently judged retrospective 0.45. No child-cycle/outcome-count increment; maintainer comfortable-fit judgment and acceptance pending.
Release limits: Full suite, production deployment, physical devices, other browser engines and screen-reader checks not performed. Existing FeatureFlagChangePageTest activity-link assertion fails identically on starting planning revision 6797de0f; one of its two tests passes. This baseline failure is not represented as a green suite. Local checks cover the approved feature contract; deployment should verify effective flags and permitted/denied downloads as documented. No merge or push performed.
Commits: Planning 6797de0f; Stage 1 74ff9dc0; Stage 2 d40980d4; Stage 3 41244f96; Stage 4 is the commit containing this closeout. Final commit reference is recorded in the debug handoff log after creation.

*— Codex · 2026-10-27 UTC*
