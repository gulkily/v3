> **Feature plan:** [Step 1](./backup_feature_flags_step1_solution_assessment.md) · [Step 2](./backup_feature_flags_step2_feature_description.md) · [Step 3](./backup_feature_flags_step3_development_plan.md) · Step 4

Debug mode: enabled
Effort study: railspike-effort / v3/backup_feature_flags; [host ledger](../effort_estimation_trial.md#cycle-v3backup_feature_flags)
Extended estimates: enabled

**Cycle estimate: 0.40**

## Problem

Operators cannot independently disable text and image backup downloads. Removing links alone leaves direct and cached download paths available.

## User stories

- As an operator, I want separate text and image backup flags so that I can choose which categories this site distributes.
- As a visitor, I want available downloads and clear unavailable states so that I can complete a backup without following broken controls.

## Core requirements

- Provide two independent, default-enabled flags through existing operator feature controls and configuration precedence. Text covers repository tar/ZIP archives and SQLite index downloads; images covers image archives. Image authoring remains independently controlled, including backups of retained images.
- Enforce each effective flag on new download requests before archive preparation or cached delivery. Direct URLs, revision parameters and page aliases cannot bypass policy; existing access restrictions still apply. Disabled requests return a consistent unavailable response without attachment bytes.
- Show only enabled, otherwise available backup actions. Hide Backup from Tools launcher/subnavigation when both flags are disabled; bookmarked Backup aliases show an unavailable state. When text is disabled, SQLite Viewer and its database-download control show coherent unavailability without attempting a load or presenting a generic loading failure.
- Disabling preserves content and backup data; re-enabling restores ordinary eligible downloads without repair. Previously obtained copies, loaded browser data and transfers already underway cannot be recalled. Public content access, browser key backups, filesystem backups and restore/import remain outside these controls.
- Keep matching-revision and incomplete-backup consequences visible where relevant, including image-only availability. Avoid redundant UI copy; operator controls must make the SQLite consequence discoverable. Warm server caches must not bypass current policy or leave disabled actions advertised on newly loaded pages.

## Delivery and completion boundary

Work type: Application change using existing flag, download and navigation components; no new dependencies, record formats or read-model schema changes.
Documentation: Feature plans/debug log, operator backup/flag guidance, UI copy audit checklist and effort-study ledger bookkeeping.
Normal entry and recovery: Operator opens Tools → Feature Flags, changes either flag, checks Backup/SQLite Viewer, then re-enables it and retries. Existing override-source/lock feedback identifies configuration that prevents a change. No particular deployment or device was named.
Release condition: The complete operator-to-download flow and recovery pass the matrix below; document any unverified deployment limits.

## Risks and shared surfaces

- Shared repository/image export paths could couple the flags: validate independent direct requests first; preserve category-specific decisions.
- Warm archives/pages could retain stale behavior: validate toggles after warming both; enforce current policy before delivery and refresh affected visibility.
- SQLite Viewer depends on the text download, and an image archive alone is incomplete: validate disabled/re-enabled Viewer and image-only backup flows early; retain actionable state and material warnings.

Inventory: Extend existing Feature Flags page, signed update API and configuration evaluation; Backup page and /instance/, /backup/, /tools/backup/ aliases; repository tar/ZIP, image tar and SQLite download endpoints; Tools launcher/shared subnavigation; SQLite Viewer page, download link and loader; cached/static versions of affected pages and reusable archive delivery. Reuse canonical components; no new destination or parallel control system.

## Simple user flow

1. Operator disables text, images or both in Feature Flags.
2. Visitor sees only available actions; disabled direct requests receive an unavailable response.
3. Operator re-enables a category; visitor reloads and downloads it successfully.

## Success criteria and study checkpoint

Acceptance: Verify all four flag combinations, default compatibility, operator authorization/configuration precedence, direct and revision-bound requests, warm-cache toggles, retained images with authoring disabled, all aliases and re-enable recovery. Render phone/desktop Backup, Feature Flags, SQLite Viewer and Tools navigation across relevant themes; verify existing navigation icons. Record copy verification in the audit checklist.
Request disposition: Separate image/text flags incorporated; user approved Step 1's recommended Option A. No additional enhancement or master flag.
Checkpoint: 2026-10-27T00:30:46Z; Step 2 total 0.40 reaffirmed for scope v1/Option A from baseline 2acea7d8ca05793cef7face657a793f130a76761, R1/P2. Same single exposed Codex rater; frozen hashes reverified; prior intake and option scores preserved. Scope remains modest shared-surface integration with cache/Viewer recovery verification; no measured labor or independent outcome claimed.
Approval: Step 1 approved; await Approved Step 2. Planning remains uncommitted; no implementation or accepted-count increment.

*— Codex · 2026-10-27 UTC*

Approval update: 2026-10-27T01:13:18Z; user explicitly approved Step 2. Earlier pending status is historical; [Step 3](./backup_feature_flags_step3_development_plan.md) awaits its own approval.

*— Codex · 2026-10-27 UTC*
