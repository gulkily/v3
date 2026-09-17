# Signed User-to-User Approvals — Step 1: Solution Assessment

## Problem

User-to-user approval records currently claim an approver identity but lack a detached signature proving that the approver authorized the approval.

## Option A — Reuse the signed post lifecycle for approval replies

- Pros: The browser signs the canonical approval reply with the approver's existing key; the server verifies it and stores the detached signature beside the approval record.
- Pros: Reuses the established signing, verification, and failure-handling model for threads and replies; no new trust model or database migration.
- Cons: The approval action needs a browser-signing step and clear feedback if signing cannot complete.

## Option B — Build an approval-specific signing protocol

- Pros: Can tailor request and response data solely to approval actions.
- Cons: Duplicates the existing prepare, signature-verification, and record-finalization lifecycle; increases the chance that approval semantics diverge from signed posts.

## Option C — Have the server sign approval records

- Pros: Keeps approval as a one-click server operation.
- Cons: Proves only that the server wrote the record, not that the approving user authorized it; does not meet the user-to-user accountability requirement.

## Recommendation

Choose **Option A**. Treat an approval as a signed, generated reply in the target user's bootstrap thread, using the existing browser key and detached-signature model. The signer must be an already-approved identity, and approval seeds remain unsigned trust anchors outside this feature's scope.

## Approval Gate

Reply **Approved Step 1** to proceed to the feature description.
