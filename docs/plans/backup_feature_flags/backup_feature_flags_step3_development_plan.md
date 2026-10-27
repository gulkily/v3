> **Feature plan:** [Step 1](./backup_feature_flags_step1_solution_assessment.md) · [Step 2](./backup_feature_flags_step2_feature_description.md) · [Step 3](./backup_feature_flags_step3_development_plan.md) · [Step 4](./backup_feature_flags_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/backup_feature_flags; [host ledger](../effort_estimation_trial.md#cycle-v3backup_feature_flags)
Extended estimates: enabled

**Cycle estimate: 0.40**

## Completion Contract

Entry/outcome: Operator uses Tools → Feature Flags to independently control text (repository tar/ZIP and SQLite) and image downloads, default enabled. Backup, SQLite Viewer, direct requests and fresh page loads reflect effective policy, including warm caches and existing access restrictions.
Recovery: Re-enable and reload to restore eligible downloads without repair; preserve content, retained images and backup data. Already obtained copies and active transfers are not recalled.
Release: Pass all four combinations through real HTTP/browser flows, configuration precedence, disabled/re-enabled recovery and phone/desktop theme checks. Record deployment/physical-device limits; do not claim production verification from local evidence. No new dependencies, formats, schema changes or destination names.

## Key Risks

- **High risk:** Shared exporter could block the wrong category or serve cached disabled bytes. Validate direct requests early; gate by requested category before preparing or serving archives.
- **High risk:** SQLite Viewer or cached navigation could advertise unusable actions. Validate disabled Viewer and warm-page transitions before acceptance; reuse shared availability and cache invalidation.
- **High risk:** Image-only downloads can imply complete recovery. Preserve matching-revision/incomplete-backup warnings; verify retained-image export with authoring disabled.

## Stage 1 — Text backup control

**Estimate: 0.15**

- Goal: Deliver operator-controlled text downloads and usable SQLite unavailability/recovery.
- Dependencies: Step 3 approval and planning commit.
- Expected changes: Register default-enabled text flag; wire repository/SQLite enforcement, Backup filtering and Viewer state/loader suppression. Register configuration schema and decide template inclusion explicitly.
- Verification approach: Existing flag/behavior/schema/config-command tests plus disabled direct requests, tar/ZIP/SQLite default behavior, authorization, precedence and re-enable recovery; preserve custom configuration values.
- Estimate details: Stage work S1/v1; driver: existing controls with Viewer coupling; uncertainty: shared exporter routing.
- Risks or open questions: Check category dispatch before gating; image export must remain usable.
- Canonical components/API contracts touched: FeatureFlagRegistry/Evaluator, PrivateConfigSchema/template writer, existing signed flag API, InstancePageController, ToolsPageController, Backup/SQLite templates and loader. Preserve public signatures.

## Stage 2 — Image backup control

**Estimate: 0.10**

- Goal: Add independent image backup selection and direct-request enforcement.
- Dependencies: Stage 1 verified and committed.
- Expected changes: Register default-enabled image-backup flag and configuration; gate image downloads/links independently of authoring; retain relevant restore warnings.
- Verification approach: All four download combinations, revision parameters, warm archive hits, retained images with authoring disabled, denied preparation/delivery and re-enable recovery.
- Estimate details: Stage work S2/v1; driver: reuse text-control pattern; uncertainty: existing image availability conditions.
- Risks or open questions: Validate retained-image export early; disabling must preserve sources and existing archives.
- Canonical components/API contracts touched: Flag/configuration registry, shared backup controller/template and ImageBackup/ArchiveCache delivery boundary; existing archive formats unchanged.

## Stage 3 — Shared navigation and cache transitions

**Estimate: 0.10**

- Goal: Newly loaded pages and bookmarked aliases consistently reflect both flags.
- Dependencies: Stage 2 verified and committed.
- Expected changes: Hide Backup launcher/subnavigation only when both flags are disabled; provide consistent alias unavailability; integrate existing flag invalidation/static-page handling where needed.
- Verification approach: Warm pages before toggling via normal operator controls; check all Backup aliases, Tools/shared navigation and Viewer through actual host routing, including private-site restrictions.
- Estimate details: Stage work S3/v1; driver: shared navigation/cache wiring; uncertainty: cached-page coverage.
- Risks or open questions: Resolve any stale-page bypass before acceptance; do not expand into general cache redesign.
- Canonical components/API contracts touched: ToolsPageSupport/tools navigation, feature-change invalidation, Application/FrontController and StaticArtifactBuilder where affected; existing destination keys/icons retained.

## Stage 4 — Acceptance and operator handoff

**Estimate: 0.10**

- Goal: Establish the complete normal-flow/recovery contract and document limits.
- Dependencies: Stage 3 verified and committed.
- Expected changes: Own shared acceptance fixtures, focused corrections, operator backup/flag guidance, UI copy audit, implementation summary and study closeout.
- Verification approach: Browser operator toggles and HTTP matrix; phone/desktop Backup, Viewer, Feature Flags and Tools across themes, including Word97/Chicago/Midnight icons, overflow and empty/failure/recovery states. Run affected existing tests and verify no dependency delta.
- Estimate details: Stage work S4/v1; driver: combined acceptance/documentation; uncertainty: rendered theme/cached-state regressions. Shared acceptance belongs here, not repeated per stage.
- Risks or open questions: Record omissions honestly; unresolved contract failures prevent completion. No deployment authorization inferred.
- Canonical components/API contracts touched: Existing browser/PHP harnesses, affected operator docs, UI audit and cycle artifacts/ledger; no new runtime contract.

## Execution and study checkpoint

Approval: Step 2 approved; separate image/text request incorporated with no outstanding scope change. Await Approved Step 3; no implementation.
Execution: After approval, create/reuse an eligible feature branch, organize plans/debug log under docs/plans/backup_feature_flags/, repair links/index, and commit approved planning files before implementation. Create Step 4 summary afterward; verify and commit each stage with its summary before advancing. Keep substantive ledger updates for authorized stage/closeout commits.
Checkpoint: 2026-10-27T01:12:47Z; scope v1/Option A, baseline 2acea7d8ca05793cef7face657a793f130a76761, R1/P2; whole-cycle 0.40 independently reaffirmed. S1–S4/v1 first stage-work values 0.15, 0.10, 0.10, 0.10 cover each stage's changes/checks/docs/correction, not cycle planning; never sum them. Codex single exposed rater; frozen hashes reverified, prior scores/exposure retained. Four planned/zero completed stages; no measured labor or accepted outcome. Documents remain uncommitted.

*— Codex · 2026-10-27 UTC*
