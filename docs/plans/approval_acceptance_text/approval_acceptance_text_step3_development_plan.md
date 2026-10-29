> **Feature plan:** [Step 1](approval_acceptance_text_step1_solution_assessment.md) · [Step 2](approval_acceptance_text_step2_feature_description.md) · [Step 3](approval_acceptance_text_step3_development_plan.md) · Step 4 pending

Debug mode: enabled
Effort study: railspike-effort / v3/approval_acceptance_text — [ledger](../effort_estimation_trial.md)
Extended estimates: enabled

**Cycle estimate: 0.75**

## Completion Contract

Deliver approved Option A: normal direct/invitation approval produces readable acceptance evidence; history/source/API, rebuild and trusted restore preserve existing authority. Explicit conversion previews changes, preserves signed bytes and acceptance facts, and recovers safely from interruption. Readable readiness output and a tested removal procedure isolate legacy reading from current text/digest validation. Acceptance uses disposable repositories and actual rendered flows; no production migration, deployment, history rewrite or actual reader retirement. Close F03 only with linked evidence and limitations.

Concepts touched: Approval acceptance (representation changed), signed review and invitation replay (behavior preserved). Add only these proposed concept entries during implementation. Zero dependency delta; no read-model schema change intended. If one becomes necessary, apply CONTRIBUTING's version/spec/startup-rebuild requirements before dependent work. Documentation scope remains approved Step 2.

## Key Risks

- **High risk:** Digest reconstruction can invalidate historical review. S1 must prove ordering/type preservation with signed fixtures before conversion; reject unsupported evidence without changing originals.
- **High risk:** Conflicting receipts or interrupted commits can lose evidence. S2 establishes unambiguous path selection; S4 validates transaction/retry behavior before normal-flow acceptance.
- **High risk:** Removing legacy support can strand archives. S5 proves text-only operation separately and reports archive coverage as unknown; actual retirement remains a later decision.

## Stage 1 — Readable contract and digest preservation

**Estimate: 0.15**

- Goal/dependencies: Establish the format and compatibility invariant; no implementation dependency.
- Expected changes: Specify named fields, context order/types, bounded values, duplicate/unknown-field rejection and malformed-input handling; isolate legacy decoding from shared validation and digest reproduction. Preserve the existing digest algorithm, including for new grants.
- Verification: Text/legacy equivalence, signed digest fixtures with varied ordering, Unicode and escaping; strict failures and byte-identical grant/signature checks.
- Risks: Unrepresentable legacy context blocks conversion; never normalize away signed differences.
- Canonical components/API contracts touched: ApprovalAcceptance codec, ApprovalPolicy context, receipt spec; add touched concept descriptions.
- Estimate details: Stage work; driver is exact digest compatibility, uncertainty is historical context variation.

## Stage 2 — New approval writes and receipt resolution

**Estimate: 0.15**

- Goal/dependencies: New direct and invitation actions write text with correct retry behavior; S1.
- Expected changes: Resolve actual receipt paths separately from the new-write path; validate both files when present, accept duplicates only when all evidence agrees, reject conflicts/invalid companions. Wire writers, recovery, history and source access to that resolution.
- Verification: Normal direct/invitation submission and retry; pre-commit failure cleanup, post-commit refresh failure, equivalent/conflicting duplicates, legacy-only history/source links.
- Risks: Recovery currently assumes one path; verify committed evidence at the resolved paths before reporting success.
- Canonical components/API contracts touched: LocalWriteService, ApprovalHistory, SourcePathValidator, shared history/API/source consumers.
- Estimate details: Stage work; driver is committed-action recovery, uncertainty is indirect path assumptions.

## Stage 3 — Replay and archive compatibility

**Estimate: 0.10**

- Goal/dependencies: Preserve authority through rebuild/export/restore; S2.
- Expected changes: Recognize both receipt forms in archive catalog/identity mapping; apply shared resolution to full/incremental replay and trusted restore. Preserve remote content-import exclusion and source authorization.
- Verification: Mixed-format repository archive round trip, rebuild equivalence, invitation expiry/revocation semantics, malformed/conflicting archive rejection and remote-import exclusion.
- Risks: Archive deduplication must not conceal contradictory receipts; validate before collapsing equivalent identities.
- Canonical components/API contracts touched: ArchiveRecordCatalog, repository import/export, ReadModelBuilder, IncrementalReadModelUpdater, InvitationApprovalReplay.
- Estimate details: Stage work; driver is archive/replay equivalence, uncertainty is import deduplication order.

## Stage 4 — Operator conversion and interruption recovery

**Estimate: 0.20**

- Goal/dependencies: Explicit safe conversion through CLI; S3.
- Expected changes: Add `./v3 approval receipts inspect|convert` with readable default, explicit machine output, repository selection and dry-run. Preflight selected receipts/grants under the existing write lock; refuse conflicting evidence or unrelated dirty repository state. Commit validated text replacements and removal of corresponding legacy files as one scoped transaction; preserve recoverable originals until success. Retry distinguishes uncommitted interruption from committed conversion awaiting refresh. Document help and recovery.
- Verification: Actual command preview/apply/repeat, lock contention, conflicts, injected write/commit/refresh failures and interrupted retry on disposable Git repositories; compare grants/signatures and evidence before/after.
- Risks: Recovery must not overwrite intervening edits; mismatches stop with an actionable recovery path. Define operation ownership and recovery evidence before mutation code.
- Canonical components/API contracts touched: v3 dispatch/help, new receipt command/service using existing repository lock/Git facilities, read-model refresh, CLI/runbook references.
- Estimate details: Stage work; driver is durable conversion recovery, uncertainty is reuse of transaction facilities.

## Stage 5 — Reader retirement readiness

**Estimate: 0.10**

- Goal/dependencies: Make later removal bounded and reviewable; S4.
- Expected changes: Add read-only `./v3 approval receipts readiness`, listing legacy evidence/blockers and archive coverage limits. Document exact compatibility entry points to remove and retained digest validation. Use a test seam to make legacy decoding unavailable; no new product setting required.
- Verification: Readiness before/after conversion, malformed/orphan/conflicting receipts, no writes during checks; new/converted text write/read/rebuild/restore passes with decoder unavailable. Legacy input fails explicitly in that configuration.
- Risks: Zero live legacy files is not archive clearance; never report universal retirement safety.
- Canonical components/API contracts touched: Receipt CLI, isolated legacy adapter, runbook and compatibility regression tests.
- Estimate details: Stage work; driver is retirement proof, uncertainty is hidden decoder coupling.

## Stage 6 — Acceptance and closeout

**Estimate: 0.15**

- Goal/dependencies: Verify the complete approved outcome; S1–S5.
- Expected changes: Complete affected regression/browser coverage, docs/concept verification, audit closeout and Step 4 evidence. This stage owns shared acceptance overhead.
- Verification: Focused approval/policy/history/invitation/import/source/CLI tests; normal approval and redemption through API/browser; classic/Forte/Tools history and receipt source at phone/desktop in relevant themes/states, including denied source access. Inspect actual text output, run rebuild and trusted restore, compare signed bytes, and repeat text-only retirement check. Apply CONTRIBUTING UI guidance; record failures and unchecked environments explicitly.
- Risks: Codec passes alone do not prove history/recovery; leave F03 open if normal-flow requirements fail.
- Canonical components/API contracts touched: Existing browser approval suites and shared views, spec/runbook/CLI docs, human-readable audit, concept entries and study ledger.
- Estimate details: Stage work; driver is cross-surface acceptance, uncertainty is fixture/environment setup.

## Execution and study checkpoint

Approved Step 2 received; all requested legacy-removal requirements retained, no new scope. After Approved Step 3: create feature branch, organize this cycle's plans/debug log and repair links/index, commit approved planning, then create Step 4 summary. Complete verification and commit each stage with its summary before the next. No commits or implementation at this handoff.

2026-10-29 UTC: scope v2/A, baseline d26a90f9b9bfa8eb67e72804a7c24707947ab7c0, R1/P2, extended estimates enabled; Codex single exposed rater. Whole-cycle **0.75** revises preserved Step 2 **0.70** for new understanding of path-based recovery and archive deduplication, not additional scope. S1–S6/v1 stage-work estimates above are independently judged, non-additive; no hours or measured labor. Frozen hashes reverified; pristine intake remains missing. No accepted outcome; await Approved Step 3.

*— Codex · 2026-10-29 UTC*
