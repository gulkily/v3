# Sender-assisted private message history synchronization — Step 1

> **Feature plan:** [Step 1](./private_message_sender_history_sync_step1_solution_assessment.md) · [Step 2](./private_message_sender_history_sync_step2_feature_description.md) · [Step 3](./private_message_sender_history_sync_step3_development_plan.md) · [Step 4](./private_message_sender_history_sync_step4_implementation_summary.md)

## Original Query

“Looks good so far. Can you please assess the complexity of also allowing senders to provide additional history sync?”

“Please write Step 1.”

## Understood Intent

Extend existing same-account recovery: when Alice sent Bob a message, an available Alice device could restore access for Bob's newly approved key, even if Bob's old devices are unavailable. Sender-only help restores Bob's incoming messages; restoring his outgoing messages from Alice's copies is a broader option below.

## Problem

A recipient can lose historical access despite a sender retaining the ability to decrypt the original message.

## Option A — Automatic sender assistance

An authenticated sender device contributes recoverable messages to their original recipients' approved keys during ordinary visits, extending the existing automatic recovery flow.

- Pros: no coordination or simultaneous visits; reuses encrypted transfers, private sync storage, verified reading, and partial recovery.
- Cons: expands authorization across accounts; requires fair, bounded discovery across recipients and careful protection of recipient recovery metadata.

## Option B — Recipient-requested sender assistance

A recipient explicitly requests recovery; eligible sender devices contribute during later visits.

- Pros: makes recovery intent explicit and limits work to requested history.
- Cons: adds request state, controls, expiration and retry semantics; still needs the same cross-account authorization and cryptographic checks.

## Option C — Assistance from either original participant

Either participant's eligible devices help the other recover messages they originally exchanged, covering both incoming and outgoing history.

- Pros: better recovery when only the counterpart retains access; shares most of Option A's foundations.
- Cons: broader sharing policy than sender-only assistance; requires explicit approval of recipient-to-sender recovery and more authorization cases.

## Recommendation and viability

Choose **Option A** as a separate, moderate extension; defer Option C unless bidirectional assistance is desired. Approved policy: any currently approved sender-account key with verified access may help a currently approved key of the original recipient, without another sharing prompt. The user approved this assessment with **Approved Step 1**.

- Reuse the existing private sync infrastructure; preserve original ciphertext, sender-signature verification, message order, drafts and unread behavior. No additional database appears necessary.
- Restrict every contribution to its original sender/recipient relationship; unrelated accounts remain ineligible. Avoid exposing read receipts or detailed recipient recovery progress to donors.
- Bound work, retain transfers after confirmation, combine partial donors and retry gaps. Neither approval nor a sender visit guarantees recovery; revocation cannot retract delivered secrets.
- Deliver recipient approval → separate sender visit → recipient Messages → verified historical reading, including reload, interruption, invalid transfers and revoked keys. Authorization and recovery tests are the main complexity; provisionally four to six focused stages, subject to Step 3 validation within the eight-stage limit.
- Exclude authentication redesign, counterpart notifications, participant export and broader conversation access.

Basis: [existing sync implementation and verification](../private_message_history_sync/private_message_history_sync_step4_implementation_summary.md). Continue with [Step 2](./private_message_sender_history_sync_step2_feature_description.md). Implementation requires Approved Step 3; keep planning artifacts uncommitted until then.
