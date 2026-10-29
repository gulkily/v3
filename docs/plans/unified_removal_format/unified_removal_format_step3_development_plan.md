> **Feature plan:** [Step 1](unified_removal_format_step1_solution_assessment.md) · [Step 2](unified_removal_format_step2_feature_description.md) · Step 3 · Step 4 (pending)

Debug mode: enabled.
Effort study: `railspike-effort` / `v3/unified_removal_format`; [ledger](../effort_estimation_trial.md#cycle-v3unified_removal_format), standing P2 enrollment.
Extended estimates: enabled.

**Cycle estimate: 0.70**

## Completion Contract

Existing signed retraction and CLI deletion/repair produce one readable receipt format, with correct source/export, rebuild/import, label effects and safe retries. Support only the new shared format and preserve its exact signed evidence; no migration or accommodation of previous F01 text receipts, F02 JSON records or old pending state. Do not delete old data as part of this change. Reuse retraction receipt/request paths; new local receipts use `records/post-removals/<id>.txt`. Distinct locations retain independent authority evidence; one codec and effective-removal resolver interpret them. No new dependencies, command/UI redesign, publication guards, history purge or deployment. Release only after all stages and ordinary-flow acceptance; intermediate commits are not releasable.

## Key Risks

- **High risk: authority/evidence loss.** Validate signed/local profiles and same-post cases before writers: union compatible label bindings; deletion dominates resolution; preserve each signed request; conflicting hashes/structure produce actionable errors, never silent precedence. Define required source bindings for new actions; do not add legacy missing-field allowances. No new signing after deletion merely to manufacture missing authority.
- **High risk: recovery/rollback.** Preserve journal/receipt write ordering and unrelated staging; validate new journal versions before mutation; no old-journal replay or recovery workflow. Older binaries cannot safely consume new receipts; document rollback through a verified matching repository/application backup, not binary downgrade alone.

## Stage 1 — Receipt contract (S1/v2)

**Estimate: 0.10**

- Goal / Dependencies: establish shared validation; none.
- Expected changes: spec/examples, explicit operation/authority profiles, readable label blocks and strict bounded codec; no old-format adapters. Proposed `RemovalReceipt::encode(array): string` / `decode(string): array`; wrappers validate bound signed evidence.
- Verification approach: round trips, unknown/duplicate/malformed fields, anonymous sources, contradictory profiles and signed-byte preservation.
- Risks or open questions: ambiguity loses trust; validate profiles before S2 and reject contradictions.
- Canonical components/API contracts touched: RetractionReceipt, GenericTextRecordParser, new shared codec/spec.
- Estimate details: driver: format validation; uncertainty: repeated-block parser reuse.

## Stage 2 — Common interpretation (S2/v2)

**Estimate: 0.15**

- Goal / Dependencies: deterministic evidence and projections; S1.
- Expected changes: shared resolver returning effective operation, unioned bindings and original evidence/source paths; replace array-union precedence. Preserve deletion through later repair; retain independent signed evidence and reject conflicts.
- Verification approach: resolve→delete, resolve→retract, delete/retraction coexistence, unchanged retries, conflicting evidence, new signed plus new local receipts; real rebuild preserves independent replies/labels.
- Risks or open questions: suppressed live sources or dropped bindings; test transition matrix before S3/S4.
- Canonical components/API contracts touched: AcceptedRetraction, LabelRemoval, RetractedPosts, LabelOwnership, ReadModelBuilder; no planned core schema/query change. Any required schema change invokes CONTRIBUTING's version/spec/startup-rebuild checks before proceeding.
- Estimate details: driver: shared consumers; uncertainty: projection coupling.

## Stage 3 — Operator deletion and repair (S3/v2)

**Estimate: 0.15**

- Goal / Dependencies: normal CLI writes shared text receipts; S1–S2.
- Expected changes: text paths, explicit new journal version, preserved acceptance ordering and scoped commit; reject unsupported journal versions before mutation; retry uses accepted structure where available and never silently reconstructs authority.
- Verification approach: actual delete/repair, anonymous posts, changed files, process interruption, rejected commit, unrelated staging, refresh failure and repeat command.
- Risks or open questions: partial deletion; inject failures before/after evidence, deletion and commit, preserving actionable recovery.
- Canonical components/API contracts touched: LabelRemoval, SourceLabelRemoval, delete-record.
- Estimate details: driver: transaction/recovery; uncertainty: remaining history-based inspection.

## Stage 4 — Signed retraction (S4/v2)

**Estimate: 0.10**

- Goal / Dependencies: new signed receipts use shared contract; S1–S2.
- Expected changes: adapt signed writer/readers; preserve new-format replay, detached evidence and API result paths; remove old receipt decoding. A prior local deletion does not itself authorize a signed retraction.
- Verification approach: author/operator/account flows, invalid signatures, changed evidence, interrupted installation/deletion and exact finalize replay.
- Risks or open questions: signed evidence overwritten; verify exact new-format evidence equality before accepting retries.
- Canonical components/API contracts touched: LocalWriteService, AcceptedRetraction, RetractionDeletion, existing prepare/finalize API.
- Estimate details: driver: signed replay; uncertainty: signed retry integration.

## Stage 5 — Source and archives (S5/v2)

**Estimate: 0.15**

- Goal / Dependencies: inspect/export/import coherent evidence; S2–S4.
- Expected changes: register new text family, expose exact source bytes and Activity metadata, use common resolver for import protection; reject previous receipt formats and untrusted new authority.
- Verification approach: current/historical source, actual archive export/import, local duplicates, incomplete/tampered signed sets, original-content reimport, same-post signed/local receipts in the new format.
- Risks or open questions: archive grants authority; require existing matching local acceptance, preserving byte comparisons.
- Canonical components/API contracts touched: SourcePathValidator, ArchiveRecordCatalog, ContentImportPlanner, source/Activity/export consumers.
- Estimate details: driver: family/path wiring; uncertainty: historical source assumptions.

## Stage 6 — Maintenance and recovery guidance (S6/v2)

**Estimate: 0.10**

- Goal / Dependencies: cleanup recognizes shared receipts; S3–S5.
- Expected changes: new-format scan/cutoff handling, remove obsolete F01/F02 format/migration support, document the clean break and new-action recovery in CLI help; retain internal machine journals.
- Verification approach: actual sweep preview/run/repeat, cleanup after local/signed deletion, resolution-only effects, no old-format acceptance/conversion and actionable new-action failures.
- Risks or open questions: treating resolution as deletion; verify operation-specific maintenance before acceptance.
- Canonical components/API contracts touched: RetractionSweep, RetractionMigration, maintenance CLI/reference.
- Estimate details: driver: operation-aware cleanup; uncertainty: old migration callers.

## Stage 7 — Acceptance and closeout (S7/v2)

**Estimate: 0.15**

- Goal / Dependencies: release-ready ordinary flows; S1–S6.
- Expected changes: shared regression/fixture reconciliation, operator/spec/checklist/summary/ledger evidence; owns cross-stage acceptance and documentation, not duplicated planning overhead.
- Verification approach: focused PHP suites, real CLI/rebuild/archive round trips, actual browser author/operator/retry/source flows at phone/desktop across affected themes, keyboard/touch and overflow; exact retained signatures and same-post matrix.
- Risks or open questions: component tests miss user recovery; replay commit/refresh failure via normal entry and record limitations before completion.
- Canonical components/API contracts touched: existing retraction browser fixtures, transaction/import/maintenance suites and shared projections; no new UI.
- Estimate details: driver: ordinary-flow verification; uncertainty: fixture corrections. No full-suite/deployment claim without execution.

## Execution and study checkpoint

Historical v1 snapshot; superseded by the scope v2 checkpoint below.

Step 2 approved by “Approved Step 2”; scope v1/A clarified, original baseline `b8afb7711cdf6816da943755268de848b6f33781`, R1/P2 retained. 2026-10-29 UTC: total 0.85 replaces preserved 0.75 projections because inspection identified same-ID array precedence and legacy pending-journal recovery across both entry points. Codex single exposed rater; three frozen R1 hashes reverified. S1–S7 estimates are stage work, not additive or measured labor. Seven bounded stages reuse existing infrastructure; split an oversized stage before implementing it. Zero dependency delta planned. Plans remain uncommitted; no application changes or runtime checks. Await Approved Step 3 before branch/organization/planning commit and implementation.

*— Codex · 2026-10-29 UTC*

## Scope v2 revision and current checkpoint

2026-10-29 UTC: user explicitly instructed abandonment of previous receipts, with no existing data to save. This narrows approved Steps 1–2; no repeated approval is required for that instruction. Remove old F01 text readers/adapters, old/new coexistence, old replay and legacy pending-state recovery; F02 JSON remains unsupported. New-format authority, same-post transitions, signatures, failure recovery and acceptance remain required. Whole-cycle 0.70 replaces historical 0.85 because compatibility and old-state recovery are removed, not because current-format verification is skipped. Same original baseline/R1/P2, single exposed Codex rater; frozen hashes reverified. No runtime changes, tests, data deletion, dependencies or accepted outcome. Await Approved Step 3.

Stage history (stage work, non-additive): S1 0.15→0.10; S2 0.20→0.15; S3 0.20→0.15; S4 0.15→0.10; S5 0.15 reaffirmed; S6 0.10 reaffirmed; S7 0.20→0.15. Earlier S1–S7/v1 included old-text adaptation/coexistence/replay and old-state recovery; v2 scopes above remove those obligations. Original cycle intake/Step 1/Step 2 0.75 and initial Step 3 0.85 are preserved. Stage count remains seven; estimates are not measured labor.

*— Revised by Codex · 2026-10-29 UTC*
