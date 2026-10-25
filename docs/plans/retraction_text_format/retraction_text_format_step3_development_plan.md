> **Feature plan:** [Step 1](retraction_text_format_step1_solution_assessment.md) · [Step 2](retraction_text_format_step2_feature_description.md) · Step 3 · Step 4 pending

Debug mode: enabled.  
Effort study: `railspike-effort` / `v3/retraction_text_format`; [ledger](../effort_estimation_trial.md#cycle-v3retraction_text_format). Standing P2 enrollment.  
Extended estimates: enabled. Approval: Steps 1–3 approved; implementation authorized.

**Cycle estimate: 0.60**

## Completion Contract

Existing author/operator browser/API retraction deletes the post and retains a signed text request, detached signature and separate text receipt in one commit. Source inspection, replies, rebuild/import, deferred cleanup and retry work without the original post. No previous-retraction compatibility/migration, other-family conversion, dependency addition, deployment or history purge. Release only after Stage 6 verification; partial stages are not deployable. Preserve the approved policy/audit documentation and study bookkeeping. No schema change intended; any qualifying read-model change follows CONTRIBUTING's version/spec/startup-rebuild requirements.

## Key Risks

- **High risk:** the current transaction deletes the request path now needed for new evidence. Stage 2 must prove disjoint retained/deleted paths, failed-commit restoration and interruption recovery before writer integration.
- **High risk:** extension-based acceptance/import logic can mistake a request for accepted deletion. Stages 1/4 bind and validate all three roles; reject unfamiliar imported acceptance independently of text format.
- **High risk:** losing or changing signed evidence breaks retry and attribution. Stage 1 checks exact bytes and malformed/mismatched files; Stage 3 proves signed author/operator flows. Do not deploy older JSON-only code against new records.

*— Codex · 2026-10-25 UTC*

## Stage 1

**Estimate: 0.15** — stage work; S1/v1

- Goal: define the three-file contract without switching live writers.
- Dependencies: approved Step 3.
- Expected changes: specify request `.txt`, detached `.txt.asc`, distinct receipt family, flat headers, absent-value rules and request/signature hash bindings; extend existing acceptance codec/path helpers.
- Verification approach: AcceptedRetractionTest plus real signature fixtures; exact-byte round trip, duplicate/unknown headers, malformed values, missing/substituted evidence and target mismatch.
- Risks/open questions: receipt validation must not claim that hashes establish imported authority; resolve before Stage 2.
- Canonical contracts: AcceptedRetraction, GenericTextRecordParser, PostRetractionRecordParser; new `docs/specs/post_retraction_record_v1.md`.
- Estimate details: driver: strict text contract; uncertainty: canonical parser edge cases.

*— Codex · 2026-10-25 UTC*

## Stage 2

**Estimate: 0.20** — stage work; S2/v1

- Goal: commit deletion and the complete evidence set safely.
- Dependencies: 1.
- Expected changes: extend `RetractionDeletion::commit(string $root, array $record): string` for three retained files, scoped staging and coherent recovery; receipt becomes usable only with complete bound evidence.
- Verification approach: rejected commit, failures between file writes/deletion/staging, process interruption, retry before/after commit, unrelated staged-file preservation and no extra acceptance commit.
- Risks/open questions: prove recovery using existing prepared-write/Git facilities; stop before Stage 3 if incomplete files cannot be recovered without lost evidence. No parallel cleanup ledger.
- Canonical contracts: RetractionDeletion, existing write lock and acceptance file set.
- Estimate details: driver: interruption boundaries; uncertainty: existing recovery coverage.

*— Codex · 2026-10-25 UTC*

## Stage 3

**Estimate: 0.15** — stage work; S3/v1

- Goal: ordinary signed retraction and repeat requests use the new contract.
- Dependencies: 1–2.
- Expected changes: wire `prepareRetraction(array): array` and `finalizePreparedRetraction(array): array`; return request `record_path`, detached `signature_path` and explicit `acceptance_path`; preserve attribution and partial-refresh recovery. Remove old-retraction conversion from these paths.
- Verification approach: signed author/operator, unauthorized action, account attribution, repeat prepare/finalize and post-commit refresh/queue failure through existing API/browser fixtures.
- Risks/open questions: a partial file set must not enter legacy conversion; resolve before consumers depend on new writes.
- Canonical contracts: LocalWriteService, retraction result contract, existing browser signing; no new controls.
- Estimate details: driver: retry/result wiring; uncertainty: existing legacy fallback interactions.

*— Codex · 2026-10-25 UTC*

## Stage 4

**Estimate: 0.20** — stage work; S4/v1

- Goal: readable source and accepted deletion survive rebuild and archive import.
- Dependencies: 1–3.
- Expected changes: update repository/source/catalog/import recognition by file role; preserve current/historical access policy, structural projections and source links. Unfamiliar receipts cannot grant acceptance; locally accepted sets remain protected.
- Verification approach: actual rebuild; surviving root/reply structure; all three source files and signatures; missing/tampered evidence; forged receipts/requests, duplicate accepted imports and old-post resurrection attempts through import/recovery tests.
- Risks/open questions: reused `.txt` paths must not trigger legacy authority inference; inventory and close all readers before Stage 5.
- Canonical contracts: CanonicalRecordRepository, SourcePathValidator, RetractedPosts, ArchiveRecordCatalog, ContentImportPlanner, read-model/source projections and repository downloads.
- Estimate details: driver: shared reader/import boundaries; uncertainty: path-based dispatch beyond direct callers.

*— Codex · 2026-10-25 UTC*

## Stage 5

**Estimate: 0.10** — stage work; S5/v1

- Goal: deferred cleanup recognizes new acceptance and operations describe supported behavior.
- Dependencies: 4.
- Expected changes: sweep/worker discovery uses the receipt; remove obsolete retraction migration paths and stale JSON-trailer claims. Preserve source-removal-family behavior and any shared maintenance required by it; do not convert that family.
- Verification approach: RetractionSweepTest/RetractionMaintenanceTest and CLI preview/run/status/worker retry; new receipt timing, late artifacts/exchanges and repeated cleanup. Check remaining migrate command/help cannot misread new requests as historical input.
- Risks/open questions: migration currently also handles source removals; isolate retraction retirement without removing unrelated recovery.
- Canonical contracts: RetractionSweep, RetractionMigration, retraction CLI/help and affected operational references.
- Estimate details: driver: discovery/CLI reconciliation; uncertainty: shared migration responsibilities.

*— Codex · 2026-10-25 UTC*

## Stage 6

**Estimate: 0.15** — stage work; S6/v1

- Goal: demonstrate the complete slice and close its documentation.
- Dependencies: 1–5.
- Expected changes: reconcile affected fixtures/assertions; finish spec/API/source/recovery docs, policy/audit F01 and R04, planning index and study outcome. Other audit items stay open.
- Verification approach: consolidated affected PHP suite; actual signed browser retraction and source views on phone/desktop, keyboard and applicable themes; rebuild, archive export/import, thread/feed/search/profile/API projections, high-traffic/static/offline and cleanup regressions. Inspect resulting files and one commit's complete change set. Record failures and unverified environments explicitly.
- Risks/open questions: fixture-only passes cannot establish human readability or recovery; no deployment/production claim. Shared end-to-end verification belongs here, not in every stage estimate.
- Canonical contracts: existing consumer/test fixtures and approved docs; preserve essential recovery text under CONTRIBUTING.
- Estimate details: driver: shared acceptance evidence; uncertainty: browser fixtures assuming embedded signatures.

*— Codex · 2026-10-25 UTC*

## Execution and study checkpoint

2026-10-25 UTC: Step 2 explicitly approved, scope v3/Option B, original/current baseline `2a07313a738af9e0a0f15796e8155d301d949ed5`, R1/P2; **0.60 whole-cycle projection reaffirmed**, independently judged, not the stage sum. Exact frozen hashes verified. Codex single exposed rater; additional source inspection found request-path deletion, JSON-extension import checks and shared source-removal migration. These fit the approved recovery/integration scope. S1–S6/v1 estimates cover stage changes, focused verification/docs and correction; Stage 6 owns shared acceptance, cycle estimate includes planning. No measured labor or accepted outcome.

After Approved Step 3: create a feature branch; organize and commit only approved plans/debug log plus permitted index/link fixes; then create the implementation summary. Each completed stage gets its own commit with summary evidence before the next. Include already requested policy/audit changes in the appropriate implementation stage, not the planning commit; retain ledger updates for closeout. Split an oversized stage before coding, and replan if the complete slice exceeds the workflow bounds. No implementation or commit yet.

*— Codex · 2026-10-25 UTC*
