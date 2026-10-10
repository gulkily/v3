> **Feature plan:** [Step 1](./private_message_sender_history_sync_step1_solution_assessment.md) · [Step 2](./private_message_sender_history_sync_step2_feature_description.md) · [Step 3](./private_message_sender_history_sync_step3_development_plan.md) · [Step 4](./private_message_sender_history_sync_step4_implementation_summary.md)

# Sender-assisted private message history synchronization — Step 3

## Completion Contract

- Outcome: recipient approval → ordinary sender visit → later recipient Messages visit restores verified incoming history; recipient donors absent.
- Recovery: partial donors, interruptions, reload and invalid/unavailable originals; preserve silent same-account recovery and messaging UX.
- Release: ≤1 hour/stage; resolve risks before dependents. Split overruns within eight stages/one day or rescope. Require local focused/browser checks, documented suite exceptions, guide and rollback evidence; deployment excluded.

## Key Risks

- **High risk:** cross-account disclosure/false trust: first test original relationships, membership and signature substitution; reject mismatches throughout transfer use.
- **High risk:** metadata leakage/starvation: compare donor responses before/after recipient confirmation and exercise multiple recipients; keep receipts private and scans fair within existing visit limits.
- **High risk:** compatibility/rollback loss: first exercise retained v1 transfers; use explicit cross-account bindings and additive storage, preserving old readers' safe failure.

## Stage 1
- Goal: establish safe eligibility/protocol.
- Dependencies: approved Step 3; feature branch and planning-only commit.
- Expected changes: v2 binds purpose, accounts, fingerprints and originals; retain same-account-only v1. Centralize original-participant/current-key eligibility.
- Verification approach: both OpenPGP versions; foreign/mixed recipients, substituted bindings/signatures, revocation.
- Risks or open questions: disclosure; gate contributions on negative tests.
- Canonical components/API contracts touched: crypto; service `eligibleSource(message, destinationAccount, sourceFingerprint)`; key resolver.

## Stage 2
- Goal: recover one incoming message through an authenticated sender transfer.
- Dependencies: Stage 1 authorization/protocol checks.
- Expected changes: wire upload/retrieval/confirmation/coverage into verified reading; retain recipient-scoped ciphertext. Separate donor/recipient confirmations.
- Verification approach: upload → preview/read → reload; original signature, unchanged ciphertext/unread.
- Risks or open questions: false confirmation; test bad candidates, require original verification.
- Canonical components/API contracts touched: sync upload/transfers/acknowledge, private sync store, crypto, canonical reader/list.

## Stage 3
- Goal: fair, private contribution discovery.
- Dependencies: Stage 2 retained-transfer recovery.
- Expected changes: `work(viewer, mode='account')` and `historySyncSentPage(sender, after, limit)`; bounded outgoing recipient/key rotation and gap revisits. Add sender checkpoints in existing dedicated sync database; preserve account-mode defaults/cursors.
- Verification approach: multiple recipients/keys, existing targets, stale-key sends, missing anchors; compare responses across recipient acknowledgments.
- Risks or open questions: leakage/starvation; donor-visible responses must not depend on recipient receipts, and uploads alone cannot permanently suppress retries.
- Canonical components/API contracts touched: work/checkpoint APIs, message paging, sync scan storage.

## Stage 4
- Goal: automatic recovery across separate visits.
- Dependencies: Stage 3 fairness/privacy checks.
- Expected changes: wire coordinator with shared batch/time limits, cancellation and identity checks; contribute verified access, including restored sender copies.
- Verification approach: non-Messages sender visit → close → recipient reading; partial donors, interruptions, same-account coexistence.
- Risks or open questions: foreground disruption; test shared bounds and failure isolation.
- Canonical components/API contracts touched: visit coordinator, discovery/upload/ack wiring, recovery events.

## Stage 5
- Goal: resilient recovery/upgrades.
- Dependencies: Stage 4 automatic journey.
- Expected changes: fix compatibility/recovery gaps; document additive checkpoint upgrade/rollback.
- Verification approach: retained v1, mixed clients, revocation between phases, malformed candidates, unavailable originals, donor forwarding, sync outage and paired-store restore.
- Risks or open questions: rollback access loss; test same-account continuity, document old-code limitations.
- Canonical components/API contracts touched: transfer retention/coverage, store initialization, reader fallback, rollout guide.

## Stage 6
- Goal: validate/explain release.
- Dependencies: Stages 1–5 gates passed.
- Expected changes: extend browser journey/shareable guide; record policy, limitations, operations and Step 4 evidence/stage commits.
- Verification approach: signed approvals, 31 incoming messages; regress previews/history/groups/redirects/composers/drafts/focus/position/unread, no global sync UI; relevant suites.
- Risks or open questions: false readiness; distinguish baseline failures, block new regressions.
- Canonical components/API contracts touched: history-sync/messaging browser suites, rollout guide, Step 4 summary.

Steps 1–2 approved; Step 3 awaits review. Keep planning documents uncommitted until Approved Step 3.
