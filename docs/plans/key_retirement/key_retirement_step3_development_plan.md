> **Feature plan:** [Step 1](key_retirement_step1_solution_assessment.md) · [Step 2](key_retirement_step2_feature_description.md) · [Step 3](key_retirement_step3_development_plan.md) · [Step 4](key_retirement_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/key_retirement — [ledger](../effort_estimation_trial.md#cycle-v3key_retirement)
Extended estimates: enabled
Status: Step 2 approved; Step 3 proposed; implementation unapproved

**Cycle estimate: 1.00**

## Completion Contract

- Entry: Account → account keys → target, or Users/Access → account → target. Reuse existing destinations and shared key detail/review controls.
- Outcome: Another active approved same-account key or active root-approved operator signs a permanent retirement. The retired fingerprint loses new authentication and account privileges; active devices retain historical approvals/signatures and recoverable message access. Neither path can retire the last active approved key. Ordinary members gain no cross-account retirement authority from the approval flag.
- Recovery: Approve and verify a replacement before retiring a lost final key. Preserve drafts, signed bytes and uncertain-request status; repeated requests do not repeat effects. Rebuild and coordinated canonical/private-store restore preserve accepted retirement and retained transfers. No fingerprint reactivation in this slice.
- Release: All stages and their gates pass, including real-key browser/API, race, replay and restore acceptance. Local completion is distinct from merge/push/deployment; production replay and physical-device checks remain unclaimed unless explicitly performed. Do not deploy a partially integrated retirement writer.

## Key Risks

- **High risk: Historical access loss.** Current `PrivateMessageHistorySyncService` uses approved-key lookup for active destinations, original signatures and donor eligibility. Separate those meanings before accepting retirement; prove historical donor/signer cases with real keys. The private transfer store has no acceptance timestamp: preserve existing trusted retained transfers without inventing dates. New transfer acceptance must serialize against retirement and recheck eligibility. If legacy evidence cannot satisfy Step 2, return for a scope decision before dependent implementation.
- **High risk: Unauthorized actions or lockout.** Authentication, direct signed writes and operator seed checks are separate paths; stale preparation, cached read models and concurrent retirements can bypass a UI check. Inventory those paths in Stage 1, revalidate under existing acceptance serialization and prove last-key races before exposing controls.
- **High risk: Replay/rollback reactivation.** Keep historical approval grants separate from effective active eligibility. Retirement authority uses preserved accepted context and signed evidence, not a supplied date or today's operator state. Older application versions can ignore retirement: rolling back code while serving the retired account is not a supported recovery. Recover with a retirement-aware version and matched data; never delete retirement to make old code work.
- Interface inconsistency: Retired is distinct from pending and browser-local Remove. Inventory all status consumers, then check shared/static surfaces and first paint before release. No new retraction guard or sweep subsystem; reuse ordinary serialization only, per the retraction handoff.

## Design and contract boundaries

- Canonical record: New versioned `Key: value` signed retirement record binds actor/target fingerprints, reviewed account/authority context and unique action identity. Exact field names, canonical path and byte limits are settled in Stage 1's specification; preserve signed bytes. Acceptance evidence is separately identified as repository-owned, following existing approval trust boundaries, not misrepresented as another member signature.
- Proposed interfaces: `KeyRetirementPolicy::evaluate(string $actorId, string $targetId): array`; `LocalWriteService::prepareKeyRetirement(array $input): array`; `LocalWriteService::createPreparedKeyRetirement(array $input): array`; signed prepare/submit API counterparts plus retained status through existing profile/access APIs. Reuse existing browser signing and prepared-action contracts; successful duplicate submissions resolve the original accepted action.
- Derived state: Retain historical approval facts; derive effective active/retired status and bounded active-key lookup independently. A schema change includes a version increment, schema documentation, prior-version normal-startup test and isolated `./v3 rebuild` plus the normal API/UI flow in the same stage. Do not silently rewrite canonical keys or approvals.
- Private state: Existing retained transfers/receipts remain private. No plaintext, extracted session keys, private keys or message identifiers enter public retirement records. Restore documentation distinguishes full Git backup from the additional private messaging stores needed for recovery.
- Scope and dependencies: No new external components, package/lock changes, key-cap implementation, bulk retirement, operator account-disable override, cryptographic revocation or resurrection endpoint. Dependency delta planned: zero. The future cap must handle replacement-first recovery separately.

## Stage 1 — Prove authority and historical eligibility boundaries

**Estimate: 0.15**

- Goal: Resolve the data-loss/authority gates with small fixture cases before adding a writable retirement path.
- Dependencies: Approved Step 3 and the planning commit; current Step 2 v2/A.
- Expected changes: Specify record/acceptance and active-versus-historical contracts; inventory every protected write/authentication/operator path and status surface. Define deterministic replay, legacy grant/transfer handling, rename/accepted-account binding, and shared serialization for transfer acceptance versus retirement. Use current code and isolated fixtures; no new public mutation route.
- Verification approach: Demonstrate old signer/donor access, operator-then-retired replay, preserved beneficiary approval, unknown legacy timing and backdated-action rejection requirements; record evidence available and missing. Validate existing locking/backup components can meet the contracts without an unbounded history scan.
- Estimate details: Stage work; driver: trust-boundary analysis and targeted fixtures; uncertainty: legacy acceptance evidence. Shared contract/specification work is owned here.
- Risks or open questions: Gate failure means return to Step 2, not invent authority or continue dependent stages. Confirm bounded stage size before implementation; split/rescope if the contract requires a new provenance subsystem.
- Canonical components/API contracts touched: `ApprovalPolicy`, `ApprovalAcceptance`, approval seed/invitation replay, `SignedPostTransaction`, `ExecutionLock`, `PublicationLock`, history service/store; new retirement spec.

## Stage 2 — Retain and replay signed retirement state

**Estimate: 0.16**

- Goal: A fixture retirement rebuilds to the same effective status while preserving historical approvals and signatures.
- Dependencies: Stage 1 gates resolved.
- Expected changes: Add bounded retirement record parsing/verification and accepted-context handling; integrate canonical discovery and full/incremental derived state. Add active eligibility lookups without converting historical approval grants into deletions. Include schema version/specification/startup migration if schema changes.
- Verification approach: Round-trip exact signed records; reject mismatched actor/target/context and conflicting evidence; test legacy repositories, replay ordering, retired operator history, no reactivation and preserved downstream approval. Run rebuild and prior-version startup before the new query.
- Estimate details: Stage work; driver: existing record/rebuild integration; uncertainty: incremental/full parity.
- Risks or open questions: Divergent replay can restore privileges; require matching state after rebuild and incremental update before Stage 3.
- Canonical components/API contracts touched: Canonical repository/path/parser, read-model builder/incremental updater/metadata/schema, profile status queries and retirement specification.

## Stage 3 — Enforce active eligibility across existing access paths

**Estimate: 0.17**

- Goal: A retired fixture key cannot gain account privileges through any existing entry point.
- Dependencies: Stage 2.
- Expected changes: Integrate shared active eligibility into sign-in, existing/forwarded sessions, root-operator checks, approval/invitation and protected signed-write paths; prevent bootstrap/re-registration/reapproval from reactivating the fingerprint. Preserve valid historical signatures and ordinary public participation. No retirement mutation UI yet.
- Verification approach: Exercise the Stage 1 endpoint matrix with active, pending and retired identities; test both approval-flag values, forwarded sessions, operator seed retention and direct signed-post requests. Preserve failed-action input and actionable errors.
- Estimate details: Stage work; driver: reusing a shared decision across entry points; uncertainty: inconsistent existing privilege checks.
- Risks or open questions: Missing one privileged route defeats retirement; inventory closure is a prerequisite to Stage 5. Do not equate all signature verification with membership authorization.
- Canonical components/API contracts touched: `Application`, auth/status controllers, `SessionRotation`, approval/operator policy, invitation acceptance, direct/prepared write services and profile API.

## Stage 4 — Preserve message recovery while excluding retired actors

**Estimate: 0.20**

- Goal: Active devices recover retained history signed or donated by retired keys; retired keys receive no new access.
- Dependencies: Stages 1–3.
- Expected changes: Separate historical verification/donor lookup from active sender/recipient/destination eligibility. Retain trusted existing transfers and receipts; serialize and recheck new upload/send acceptance against retirement. Invalidate scheduled destinations and verification continuations using membership revisions; preserve bounded modern and supported legacy protocols.
- Verification approach: Real-key originals with retired submitting key, distinct original signer and retired donor; already-confirmed and not-yet-read retained transfer; rejected new retired upload/destination, stale checkpoint and acceptance race. Repeat 115-key bounded/quiet recovery checks and verify recipient activity remains private.
- Estimate details: Stage work; driver: shared history-service checks and fixture reuse; uncertainty: legacy continuation and cross-store race handling.
- Risks or open questions: Stored transfers have no acceptance timestamp; use only the Stage 1 validated trusted-retention boundary. Never infer access from an upload or disclose private recovery metadata in public records.
- Canonical components/API contracts touched: Private message send/read controllers, history service/store/revision, browser crypto/coordinator and retained receipt contracts.

## Stage 5 — Complete signed retirement through the API

**Estimate: 0.15**

- Goal: Both authorized roles complete a retirement with safe duplicate, stale-review and failure recovery.
- Dependencies: Stages 2–4; enforcement and historical-access gates pass.
- Expected changes: Wire the dedicated prepare/review/sign/submit action into existing API routing and write serialization. Validate current actor authority, target account and remaining active count at acceptance; bind signed context and preserve accepted evidence atomically. Expose status for uncertain outcomes and keep accepted retirement visible despite a derived refresh failure.
- Verification approach: Same-account and cross-account operator success with both flag values; unrelated-member denial; self-target/final-key denial; parallel reciprocal retirements, actor retirement after preparation, repeated signatures, interrupted write/refresh and retry. Verify original record survives retries once only.
- Estimate details: Stage work; driver: reuse of prepared signing/transaction machinery; uncertainty: failure boundaries across canonical and derived state.
- Risks or open questions: Never publish acceptance before required evidence is durable; a failed refresh must not leave an accepted retired key authorized. Resolve these cases before UI wiring.
- Canonical components/API contracts touched: Proposed retirement policy/write signatures, prepared action signing, API catalog/controller, canonical acceptance transaction and normal refresh.

## Stage 6 — Deliver shared member and operator retirement journeys

**Estimate: 0.16**

- Goal: Reach and finish both flows through existing Account and Users/Access navigation.
- Dependencies: Stage 5.
- Expected changes: Reuse shared key-detail/status and approval-review controls for exact target, consequence, signing, retired evidence, pending/error/retry and replacement-first guidance. Update standard/Forte/paned profiles and users, Access, pending approvals and identity status consumers from the inventory. Preserve browser-local Remove/Undo and static viewer-neutral markup; no new navigation destination.
- Verification approach: Phone/desktop, keyboard, long fingerprints, empty/active/retired states, failure and uncertain response; verify same-account and operator replacement-first flows, relevant themes and first paint with shared/static pages. Check no-JavaScript reading/basic posting and copies of old exported public pages do not confer server authority.
- Estimate details: Stage work; driver: shared component reuse; uncertainty: scattered status labels. This stage owns UI copy/verification records.
- Risks or open questions: Avoid displaying retired keys as pending approval or implying that browser removal retires them; complete the shared-surface inventory before acceptance.
- Canonical components/API contracts touched: Account state/menu, username/profile/Forte/paned components, user-access account/profile/history, approval review/signing and auth/toolbar state.

## Stage 7 — Verify rebuild, backup/restore and interrupted recovery

**Estimate: 0.15**

- Goal: Retirement remains authoritative after the application's ordinary recovery operations.
- Dependencies: Stages 2–6.
- Expected changes: Extend existing integration fixtures for canonical export/import, fresh rebuild, incremental parity and coordinated private messaging restore. Document trusted administrative import boundaries and retirement-aware rollback; retain durable evidence outside disposable fixture roots.
- Verification approach: Restore into an isolated clean instance; confirm retired authentication fails, previous beneficiaries remain approved and active devices read history after reload. Include operator retired after accepting another retirement, malformed/missing acceptance evidence, read-model deletion, missing private transfers and interrupted submission. Missing private data gets an honest recovery path, never fabricated access.
- Estimate details: Stage work; driver: reuse of backup/history lifecycle harnesses; uncertainty: exact restore ordering.
- Risks or open questions: Older code may ignore retirement. Verify the documented recovery path uses compatible code and retained records; do not claim arbitrary downgrade safety or restoration of omitted private data.
- Canonical components/API contracts touched: Canonical import/export/rebuild, signed transaction recovery, private history lifecycle/backup contracts and operator documentation.

## Stage 8 — Release acceptance and documentation closeout

**Estimate: 0.12**

- Goal: Demonstrate the complete approved outcome and hand off precise evidence and limitations.
- Dependencies: Stages 1–7.
- Expected changes: Run the combined acceptance matrix; finish canonical/API/schema references, touched concept entries, UI verification and this cycle's implementation/debug/study closeout. Record final dependency delta and stage commits. No deployment implied.
- Verification approach: Relevant existing approval/auth/session/signed-post/message/schema/UI suites plus new end-to-end and race cases. Run full suite where supported; distinguish inherited failures and omissions from passes. Reproduce ordinary member/operator entry, replacement-first recovery and post-restore history with real browser keys. Verify links, static first paint and no unintended dependency changes.
- Estimate details: Stage work; driver: integration evidence and documentation; uncertainty: existing suite/environment failures. Shared end-to-end evidence and study closeout belong here, not repeated in every stage.
- Risks or open questions: No completion claim until all required gates pass; production and physical-device acceptance stay explicitly pending when untested.
- Canonical components/API contracts touched: All scoped surfaces; `docs/specs/`, agent/API/operator references, `docs/concepts/`, UI audit and cycle artifacts.

## Execution and study checkpoint

- Authorization: User said “approved step 2”; scope v2/A includes permanent retirement, same-account and operator paths, final-key protection and historical preservation. No subsequent requirement expansion. Step 3 approval is pending.
- Staging: Eight bounded stages target about one hour each using existing infrastructure, not a measured forecast. Before each stage, check the actual patch boundary; split oversized work before coding, and return to Step 2 if safe delivery requires more than the bounded cycle. Stage 1 is a genuine feasibility gate, not approval to proceed despite unresolved data/authority risks.
- Organization/commits: Planning remains uncommitted. After Approved Step 3, follow the Step 4 organization and planning-file gate on a feature branch; commit permitted planning files first, then completed stages separately with evidence. Preserve sibling active-key-limit/disk-space drafts and all existing ledger history; do not edit `todo.txt`.
- Concepts: Add proposed retirement/active-key eligibility entries only with implementation. Update existing approval-acceptance and private-message-history concepts for delivered behavior; reuse profile delivery and record only actual affected presentation changes. Stage 1 owns contract/specification groundwork, Stage 6 UI evidence, Stage 8 concept/operations/closeout consolidation.
- Snapshot: 2026-11-01 UTC; railspike-effort / v3/key_retirement; v2/A; original baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4; current planning revision f82bdc3e9; R1/P2; Codex single exposed rater. Whole-cycle 1.00 reaffirmed independently of stages. Original stage-work estimates S1–S8: 0.15, 0.16, 0.17, 0.20, 0.15, 0.16, 0.15, 0.12; not additive, hours or percentages. Preserve v1/A 0.95 and v2/A 1.00 history. Frozen R1 hashes verified in this session; no measured labor or accepted outcome.
- Planning verification: Current source inspection plus document structure, links and whitespace only; no runtime tests or implementation. No dependency changes. Await Approved Step 3.

*— Codex · 2026-11-01 UTC*
