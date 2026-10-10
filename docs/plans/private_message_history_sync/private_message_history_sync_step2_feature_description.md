# Private message history synchronization Step 2 feature description

> **Feature plan:** [Step 1](./private_message_history_sync_step1_solution_assessment.md) · [Step 2](./private_message_history_sync_step2_feature_description.md) · [Step 3](./private_message_history_sync_step3_development_plan.md) · Step 4 pending

[Storage and synchronization findings](./private_message_history_sync_storage_findings.md) record the infrastructure review and local lock probe.

## Problem

Newly approved keys cannot read older private messages whose envelopes exclude them. Restore recoverable sent/received history automatically from available same-account devices without exposing secrets to the server.

## User stories

- As an account holder, I want history restored on my new device so that I can continue conversations without managing message keys.
- As a returning device user, I want resumable contributions so that devices need not be online together.
- As a reader, I want recovery status so that I know what still needs another device.

## Core requirements

- Derive synchronization demand from current approved membership and history; authenticated existing-device visits contribute automatically, and Messages retrieves transfers. Cover already-approved keys too, without another sharing confirmation or a separate request queue.
- Transfer encrypted, authenticated session-key bundles outside conversations; preserve original envelopes. Bind transfers to account, source/target keys, and original message/envelope; recheck approval and reject cross-account access, tampering, and replayed work. Keep plaintext, private keys, and unwrapped session keys out of server storage/logs and public/offline artifacts. Revocation cannot retract delivered access.
- Cover sent/received history in bounded, resumable batches, including stale-recipient-list sends. Reconcile coverage, not timestamps alone; advance past failures and revisit gaps across visits. Combine partial donors and let synchronized devices help later devices. Bad uploads and duplicates must not suppress valid contributions.
- Distinguish waiting, progress, verified restoration, retryable failure, and currently unavailable history. Retain encrypted transfers after acknowledgment so reload works without the donor. Stored confirmations guide scheduling, not current reading trust; upload alone never proves recovery. Keep retry available without permanent-loss claims.
- Verify original signatures before displaying trusted content. Preserve message identity/order, previews, compact unavailable groups, reading position, focus, drafts, sending, and unread semantics; synchronization itself neither marks messages read nor creates chat messages.

## Delivery scope and completion boundary

**Application change:** approved Option B. Deliver approval → existing-device contribution → new-device Messages → verified historical reading, with interruption/retry and honest partial recovery. Release requires normal-flow browser and security/regression checks within one day/eight stages; otherwise split into usable releases. Exclude counterpart-assisted recovery, authentication redesign, private-key export, and instance-portability changes.

**Storage decision:** use a separate private database for transfers and synchronization metadata; retain messages and unread state in the existing database. Reuse shared identity/crypto/reading components. A sync-store failure must leave ordinary messaging usable. Include coordinated private backup, restore reconciliation, and rollback for both stores; do not add public export or move existing messages.

## Risks

- **Cryptographic incompatibility could block recovery or weaken trust.** First blocking validation: session-key round trips and original-signature checks with both bundled OpenPGP versions. Reject unverifiable results and revisit scope if unsupported; the complete protocol remains unvalidated.
- **Membership changes or substituted transfers could disclose history.** Earliest validation: approval/authorization review and cross-account, revoked-key, and tampered-transfer checks. Mitigation: current membership and authenticated account/key/message binding gate dependent work.
- **Partial coverage could falsely report completion or starve older history.** An abstract scan model reproduced starvation. Validate interrupted transfers, stale-key sends, and overlapping donors; use resettable checkpoints, retained transfers, and target-confirmed coverage with retryable gaps.
- **Contention or mismatched restores could delay messaging or lose access.** Local probes found measurable contention. Early validation must cover concurrent use, unavailable sync storage, and mismatched snapshots; use short bounded work, independent failure handling, stable message bindings, and backup/reconciliation checks.

## Shared component inventory

- Reuse approved membership and browser identity/authentication for discovery across all approval paths; preserve approval policy and derive demand without an approval-event ledger.
- Extend Messages list/previews, conversation initial/older history, and canonical reader/message rendering with shared recovery status/retry. Preserve Inbox/Sent redirects and retained mailbox-reader compatibility.
- Preserve conversation/list/mailbox, recipient-key, send, unread/read APIs and shared composers. Add dedicated transfer/status APIs and a separate private sync store; reuse the crypto library while addressing transfers to a specific key rather than using the whole-username chat envelope helper unchanged.

## Simple user flow

1. Approve a same-account key; open Messages on that device and see readable history or waiting/progress.
2. Visit the site on an existing authenticated device; contribute recoverable history automatically.
3. Return to Messages on the target; read verified history, retry interrupted work, or see remaining unavailable history.

## Success criteria

Restore sent/received history across three batches with devices visiting separately, including partial donors, stale-key sends, existing targets, checkpoint reset, and later-key forwarding. Verify reload without the donor and rejection of bad transfers. Preserve warnings, retry, unread/draft/order behavior, and privacy. Concurrent use, sync-store failure, mismatched restores, and rollback must preserve ordinary messaging and reopen invalid coverage without implying recovery.

Steps 1–2 approved 2026-10-10; the user's subsequent direction selects separate sync storage and authorizes these planning updates. [Step 3](./private_message_history_sync_step3_development_plan.md) still awaits approval; implementation has not started.
