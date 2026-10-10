# Private message history synchronization Step 3 development plan

> **Feature plan:** [Step 1](./private_message_history_sync_step1_solution_assessment.md) · [Step 2](./private_message_history_sync_step2_feature_description.md) · [Step 3](./private_message_history_sync_step3_development_plan.md) · [Step 4](./private_message_history_sync_step4_implementation_summary.md)

[Storage and synchronization findings](./private_message_history_sync_storage_findings.md) record the infrastructure review and local lock probe.

## Completion Contract

- Deliver approval → authenticated donor visit → target Messages → verified sent/received history, including partial recovery/retry and all Step 2 invariants.
- Require asynchronous three-batch journeys, security/regression checks, concurrent use, sync-store failure isolation, and paired/mismatched restore plus rollback. Document deployment smoke checks; no production deployment/external dependency.
- Budget eight ≤1-hour stages; split oversized stages or rescope Step 2 beyond eight/day. After approval: branch, planning-only commit, then verified summary/commit per stage. Maintain grouped artifact links at Step 4. No merge/push.

## Storage decisions

- Per user direction, use a separate private SQLite database: default `state/private/message_history_sync.sqlite3`, configurable through `PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH`. Keep messages/unread state in `PRIVATE_MESSAGE_DATABASE_PATH`; reject paths resolving to the same file. Reuse identity, key resolution, crypto loading, and reading.
- Give the sync store its own lazy PDO/configuration; read messages through the existing store interface. Bind references to stable message IDs and envelope digests, not SQLite rowids. No attached-database joins, cross-store foreign keys, or assumptions of atomic commits across stores; sync writes never mutate original messages.
- Retain encrypted bundles with message/digest/source/target indexes. Keep resettable target-confirmed coverage referencing retained transfers or verified direct access; receipts are scheduling hints, never substitutes for browser verification.
- Derive demand/gaps/counts from current membership, messages, and coverage; no request queue, approval-event ledger, persistent counters, or permanent failure records. Keep only a resettable scan checkpoint per donor/account for fair bounded scans across visits; rotate targets/messages, revisit gaps, and refresh membership. No work leases are needed; duplicate donors are safe.

## Key Risks

- **High risk: disclosure/false trust.** First validate both bundled OpenPGP versions; authenticate version/account/source/target/message/envelope-digest bindings and current approval. Failures block dependents.
- **High risk: false completion/lost access.** First test interrupted donors/reload; retain encrypted transfers, target-verified coverage, retryable gaps, and ephemeral secrets. Upload/decryption/seen status cannot prove restoration.
- **High risk: restore loss/disclosure.** First test missing/mismatched databases and artifact exclusion; retain both private stores, reset stale checkpoints, and revalidate message/digest bindings. Orphans cannot establish coverage; lost transfers may require another donor. Delivered access is irreversible.
- **High risk: foreground delays/outages.** Separate files isolate writer locks, not shared disk load or message reads. Before enabling donors, test concurrent latency/errors and unavailable sync storage; use short transactions, explicit journal/timeout policy, and independent initialization so messaging survives sync failure.

## Stage 1

- Goal: validate crypto and delivery feasibility.
- Dependencies: Approved Step 3 and planning commit.
- Expected changes: real-key fixtures; settle bundle bindings/limits.
- Verification approach: v6/v5 round trips, substitution, unsigned/invalid originals, recovered-key forwarding.
- Risks or open questions: crypto/scope failure blocks Stage 2; rescope.
- Canonical components/API contracts touched: loader/reader; new `wrapHistoryKeys(input)` / `unwrapHistoryKeys(input)` helper.

## Stage 2

- Goal: discover work after approval.
- Dependencies: Stage 1 protocol/limits pass.
- Expected changes: separate private sync database/configuration and checkpoints; derive demand from membership with bounded message enumeration, without a request queue.
- Verification approach: approval, existing keys, account scope, concurrent initialization, distinct paths, unavailable sync database with working messages.
- Risks or open questions: restarting at page one starves gaps; advance past failures, wrap, and reconcile membership/coverage.
- Canonical components/API contracts touched: resolver/message-store reads; new sync config/store and lazy Application wiring; `work($viewer, $cursor)`; GET `/api/private_messages/history_sync/work`.

## Stage 3

- Goal: retain/retrieve private transfers.
- Dependencies: Stage 2 authorized discovery.
- Expected changes: ciphertext/index and coverage tables in the sync database; bounded/idempotent no-store APIs, same-origin writes, current authorization, short sync-only transactions.
- Verification approach: substitution/revocation/replay, foreign IDs, limits, lost responses, concurrent latency/errors, original missing or changed after enumeration.
- Risks or open questions: upload is not recovery; retain alternate candidates/ciphertext. Lost/ineligible grants reopen coverage; revalidate cross-store references.
- Canonical components/API contracts touched: sync controller/store with dedicated PDO; `upload($viewer, $input)`, `transfers($viewer, $cursor)`, `acknowledge($viewer, $input)`; messaging `/history_sync/{transfers,acknowledge}` routes and checkpoint updates.

## Stage 4

- Goal: contribute on donor visits.
- Dependencies: Stage 3 access/transfer checks pass.
- Expected changes: visit coordinator discovers work, signs/encrypts/uploads recoverable keys; coalesce progress and avoid no-op writes.
- Verification approach: approve target; non-Messages donor visit; close donor; retrieve later.
- Risks or open questions: bound bytes/count/concurrency; cancel stale identity work; contain sync failures and retry without blocking messaging.
- Canonical components/API contracts touched: TemplateRenderer/live assets, browser signing, sync APIs; exclude static/offline execution.

## Stage 5

- Goal: read restored originals.
- Dependencies: Stage 4 donor contribution.
- Expected changes: validate bundles/original signatures; acknowledge transferred or directly readable items; rehydrate and reverify after reload.
- Verification approach: previews/older history, bad signatures/bindings, reload; no unverified plaintext.
- Risks or open questions: bad bundles must not poison coverage; isolate rejection/retry.
- Canonical components/API contracts touched: reader `decryptEnvelope(input)`, list/conversation/mailbox callers, sync receipts.

## Stage 6

- Goal: converge across partial donors.
- Dependencies: Stage 5 normal reading passes.
- Expected changes: bounded reconciliation/retries, exact-attempt deduplication, resettable checkpoints, identity invalidation, recovered-key forwarding; no lease/job subsystem.
- Verification approach: three batches, partial donors, lost receipts, stale-key sends, existing targets, forwarding, crashes and mismatched restores.
- Risks or open questions: failed/poisoned/orphaned candidates cannot suppress donors; revalidate coverage and reset stale checkpoints after restore.
- Canonical components/API contracts touched: sync coordinator/service/store/receipts and identity lifecycle.

## Stage 7

- Goal: show useful recovery status.
- Dependencies: Stage 6 convergence.
- Expected changes: waiting/progress/verified/unavailable/error states and retry; refresh previews/groups without disrupting reading.
- Verification approach: desktop/375px/keyboard, warnings, history, draft/focus/anchors, concurrent send, hidden tabs/later arrivals.
- Risks or open questions: misleading counts/unread changes: distinguish verified progress; preserve seen boundaries.
- Canonical components/API contracts touched: list/conversation/reader/group events, seen/unread; preserve shared composers.

## Stage 8

- Goal: verify release and operational recovery.
- Dependencies: Stages 1–7 committed; material risks resolved.
- Expected changes: API/rollout docs and summary/checklist; cover both configured paths, coordinated consistent private backups, restore reconciliation, deployment smoke, and rollback retaining both stores.
- Verification approach: PHP/browser journey; composer/privacy/cache/log/artifact checks for both databases; paired/mismatched restores, sync outage, upgrade/rollback/commit audit.
- Risks or open questions: document write-quiescence for a common backup point and recovery from capture skew; never silently discard retained transfers. No public export or production verification claim.
- Canonical components/API contracts touched: PHP/browser harness, API reference, release isolation, FDP artifacts.

Steps 1–2 approved 2026-10-10; separate sync storage selected by the user's subsequent direction. The user explicitly approved Step 3 with **Approved Step 3**; proceed with Step 4 on the feature branch.

Review adjustment: the user subsequently requested removal of Stage 7's global history-sync notice, visit counts and Retry history button. Automatic contributions/recovery and existing per-message states/retry remain; this supersedes that presentation portion of Stage 7.
