> **Feature plan:** [Step 1](./import_archive_hardening_step1_solution_assessment.md) · [Step 2](./import_archive_hardening_step2_feature_description.md) · [Step 3](./import_archive_hardening_step3_development_plan.md) · [Step 4](./import_archive_hardening_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/import_archive_hardening; standing P2 enrollment; [host ledger](../effort_estimation_trial.md#cycle-v3import_archive_hardening).

Extended estimates: enabled

**Cycle estimate: 1.00**

## Completion Contract

Deliver approved Option A through WSL/Linux `./v3 import-instance`, including preview, import, repeat, served read-model/offline results and source-independent resume. Enforce every [Step 2 limit and result/recovery requirement](./import_archive_hardening_step2_feature_description.md#core-requirements); timeout cleanup includes owned descendants and locks. Before writes, preserve canonical content; after writes begin, preserve recoverable journal/payload state and local edits. All F1–F4/C1 regressions and shared-consumer checks must pass before release. Use isolated initialized destinations for ZenMemes acceptance; no live repair/deployment or physical Lenovo claim. No dependency, schema or UI changes. Include help/runbook, review evidence and study records.

## Key Risks

- **High risk:** timeout during Git commit/promotion can strand state or children. S1 proves lifecycle enforcement using existing Linux/WSL facilities; S6–S7 must prove bounded cleanup and resume before acceptance. Native/blocking operations need enforceable supervision, not elapsed-time checks only.
- **High risk:** shared read/verifier limits can reject legitimate local data or alter trust decisions. S1 inventories callers and representative file sizes; S3/S5 preserve byte/trust semantics and distinguish source rejection, destination failure and verification interruption.
- Budget enforcement is a feasibility gate, not permission to weaken approved limits. If existing facilities cannot satisfy it without new dependencies or broader scope, stop dependent work and return to Step 2. The checked environment has proc_open, PCNTL/POSIX and existing setsid/timeout; availability alone is not proof of cleanup correctness.

## Stage 1 — Establish budget and compatibility contracts

**Estimate: 0.15**

- Goal: resolve platform and admission risks before wiring consumers.
- Dependencies: Step 3 approval and planning commit.
- Changes: central limit/deadline/error contracts; bounded child-lifecycle fixture; representative archive-size and complete caller inventory.
- Verification: controllable slow/noisy child and descendant termination within cleanup allowance; expired budget before work; no installs.
- Risks: unsupported enforcement must fail before mutation; gate dependent stages on demonstrated cleanup.
- Components: shared support/limit policy; existing process facilities and test harness.
- Estimate details: stage work; driver lifecycle proof, uncertainty descendant ownership.

## Stage 2 — Remove text-validation memory amplification

**Estimate: 0.15**

- Goal: resolve F1 without changing accepted text.
- Dependencies: S1.
- Changes: bounded-memory canonical text validation; preserve encoding, control-character and newline rules.
- Verification: valid 16 MiB record under 128 MiB PHP; malformed Unicode/control/header cases; canonical parser regressions.
- Risks: regex/scanning edge cases; compare valid/invalid behavior and exact bytes before migration.
- Components: GenericTextRecordParser and all canonical parser consumers.
- Estimate details: stage work; driver behavioral parity, uncertainty Unicode boundaries.

## Stage 3 — Enforce admission at every relevant read

**Estimate: 0.25**

- Goal: resolve F2/F4 through real preview/import paths.
- Dependencies: S1–S2.
- Changes: archive-wide filename validation; shared bounded reads for records, companions, keys and metadata, including indirect/fallback reads and destination indexing; retain stricter metadata limits.
- Verification: exact/over limits, both signature suffixes, bad names in records/excluded categories, fallback keys, mixed valid/rejected content; no canonical changes on fatal admission failure.
- Risks: early companion rejection must propagate correctly; local oversized content must fail visibly rather than disappear from identity/conflict checks.
- Components: RepositoryArchive, CanonicalRecordRepository, ReactionSignature, timestamp/removal readers, ArchiveRecordCatalog, planner/report boundary.
- Estimate details: stage work; driver read-path coverage, uncertainty shared-reader compatibility.

## Stage 4 — Bound dependency rejection work

**Estimate: 0.20**

- Goal: resolve F3 with stable import decisions and diagnostics.
- Dependencies: S3.
- Changes: rejection propagation using reverse dependencies and a work queue; retain companion coupling, conflicts, exclusion semantics and valid bootstrap cycles.
- Verification: adversarial ordering, long chains, cycles, diamonds, missing parents and signature conflicts; instrument bounded record/edge visits and compare report outcomes.
- Risks: multiple causes and cycles; define deterministic root-cause selection before replacing propagation.
- Components: ContentImportPlanner and planner/recovery tests.
- Estimate details: stage work; driver graph semantics, uncertainty diagnostic parity.

## Stage 5 — Bound signature verification

**Estimate: 0.25**

- Goal: close C1 across import and existing verifier callers.
- Dependencies: S1/S3.
- Changes: bounded GPG lifecycle with one 10-second verification budget and aggregate 1 MiB output cap; deadline expiry remains distinct from invalid signatures; preserve result diagnostics and signer checks.
- Verification: valid/invalid signatures, slow/noisy/descendant fixtures, cleanup; authentication and signed-write regressions, including invitation/approval/settings and Railspike approval paths.
- Risks: catch-all callers can swallow operational failure; trace and verify propagation rather than converting timeouts into rejected authorship.
- Components: OpenPgpSignatureVerifier, ReactionSignature, AuthApiController, LocalWriteService, WorkflowService, signature-audit CLI.
- Estimate details: stage work; driver shared consumers, uncertainty operational-error propagation.

## Stage 6 — Enforce the invocation deadline through publication

**Estimate: 0.30**

- Goal: make the 300-second budget effective across the entire normal command.
- Dependencies: S1–S5.
- Changes: invocation ownership/deadline supervision; finite reported `--time-limit` override; budget-aware lock waits, downloader, extraction/gzip, legacy Git, planning, runner Git and publication; retain stricter phase limits.
- Verification: short injected budgets at each phase, blocked I/O and lock contention; total deadline plus at most five seconds cleanup; no owned descendants or lingering lock.
- Risks: native work/descendants outliving PHP; require supervision evidence and fail closed on unavailable enforcement, without weakening web verifier behavior.
- Components: import_instance CLI, ExecutionLock integration, import services, publisher/candidate/offline lifecycle; existing help/error output.
- Estimate details: stage work; driver blocking-operation coverage, uncertainty process-tree termination.

## Stage 7 — Prove durable timeout recovery

**Estimate: 0.25**

- Goal: preserve recoverability across enforced termination.
- Dependencies: S6.
- Changes: close journal/commit/publication interruption gaps; report retry versus resume accurately and preserve import-owned payloads.
- Verification: terminate before writes, during copy/staging/commit, after commit before journal update, during build/promotion/offline publication; resume without source, preserve divergent edits, retain signature bytes, prevent duplicate commits; verify reader-visible state.
- Risks: stale Git locks or half-published views; recovery must identify owned state and never delete unrelated locks or rollback user changes.
- Components: ContentImportRunner, ImportedContentPublisher, candidate promoter/offline publication and recovery tests.
- Estimate details: stage work; driver fault injection, uncertainty narrow commit/promotion windows.

## Stage 8 — Complete operator acceptance and documentation

**Estimate: 0.20**

- Goal: verify the whole user outcome and own shared acceptance/closeout work.
- Dependencies: S1–S7 passed.
- Changes: CLI limits/recovery help, import runbook, retained review outcomes and implementation/study summary.
- Verification: WSL/Linux replay of F1–F4/C1 plus isolated ZenMemes preview/import/repeat/resume; served HTTP/offline data, default-budget fit, all affected parser/import/verification/auth/write checks; final dependency delta and diff review.
- Risks: fixture success can mask normal-flow failures; use normal routes and preserve observed production/physical-device limitations.
- Components: tests, CLI/reference/runbook and host planning/study records; no new UI.
- Estimate details: stage work; driver combined acceptance, uncertainty source/host differences; owns shared documentation and final regression review.

## Execution, inventory and study checkpoint

Steps 1/2 approved; no additional requests or scope changes. Shared parser inventory includes post, identity, approval, invitation, thread-label/subject, reaction/retraction/receipt, profile-name, feature/instance settings and reaction-signature indexing; their startup/rebuild, read/API, offline and write callers remain covered. S1 confirms indirect reads and complete routing before edits. Dependency inventory: zero additions/updates; no external approval needed for the proposed scope.

After **Approved Step 3**, create `fix/import-archive-hardening`, organize plans/debug under `docs/plans/import_archive_hardening/`, repair links/index and make the required planning commit. Preserve the existing adversarial review as evidence, incorporating it within the approved documentation scope without unrelated changes. Commit each verified stage with its summary before advancing; no implementation is authorized yet.

2026-10-27: scope v1 clarified / R1 / P2 / original baseline `00c84badd8832ed9e81fa0980c670283ae9fa318`; exact R1 hashes reverified. **Projected total revised from 0.90 to 1.00** after inspecting blocking child calls, publication boundaries and shared verifier consumers; requirements unchanged, earlier projections preserved. S1–S8/v1 values above are non-additive whole-stage work estimates, excluding repeated cycle planning. Codex single exposed rater; no independent rating, measured labor or accepted outcome. Eight planned/zero completed stages; scope stays within one cycle subject to S1 feasibility. Plans/ledger uncommitted. Await **Approved Step 3**.

*— Codex · 2026-10-27 UTC*
