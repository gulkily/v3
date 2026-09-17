# Signed User-to-User Approvals — Step 2: Feature Description

## Problem

Approval replies identify an approver but currently have no cryptographic proof that the approver authorized the record. This leaves a break in the user-to-user trust chain even though ordinary user posts are signed.

## User Stories

- As an approved member, I want my approval action signed with my browser key so that it is cryptographically attributable to me.
- As a prospective member, I want the approval that admits me to carry a verifiable signature so that my trust path is auditable.
- As a verifier, I want approval replies to expose their detached signature and signing key through existing source and activity views so that I can inspect the proof.

## Core Requirements

- A user-to-user approval must be finalized only after its canonical approval reply has a valid detached signature from the approving identity's registered public key.
- The signed approval record and its detached signature must be committed together and participate in existing source, activity, and manifest presentation.
- Failed, unavailable, mismatched, or invalid browser signatures must leave approval state and repository records unchanged and report a clear error.
- Existing authorization rules remain: only an already-approved identity may approve, self-approval remains forbidden, and the reply must target the prospective member's bootstrap thread and post.
- Approval-seed records remain unchanged and unsigned; they are out-of-scope trust anchors rather than user-to-user approvals.

## Shared Component Inventory

- `LocalWriteService`'s prepared signed-post lifecycle — extend as the canonical record creation and detached-signature verification path; do not introduce another signature-verification model.
- `browser_signing.js` — extend the existing browser-key signing and submission behavior for the approval action.
- `/api/approve_user`, profile approval action, and `pending_approvals.js` — existing approval entry points; extend them to drive the signed flow and preserve their feedback behavior.
- `source_metadata.php` and the shared activity commit manifest — existing source/signature presentation; reuse unchanged once the approval record has an adjacent signature.

## User Flow

1. An approved member chooses to approve a pending user from an existing approval surface.
2. The site prepares the canonical approval reply for that user’s bootstrap thread.
3. The member’s browser signs the prepared record with its selected local key.
4. The site verifies the signature, writes and commits the reply plus signature, and refreshes approval state.
5. The success view links to the approval post; its source and activity context show the signature and signing key.

## Success Criteria

- Every new user-to-user approval has an adjacent, valid detached signature by its recorded approver.
- Invalid or absent signatures cannot create an approval record or change approval status.
- Both pending-user and profile approval surfaces complete the signed flow and retain useful success/error feedback.
- Existing seed approvals continue to work without signatures, while newly signed approvals render through current source and activity surfaces.

## Approval Gate

Reply **Approved Step 2** to proceed to the development plan.
