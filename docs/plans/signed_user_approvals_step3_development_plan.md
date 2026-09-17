# Signed User-to-User Approvals — Step 3: Development Plan

## Stage 1
- Goal: Prepare a canonical approval reply that an eligible approver can sign.
- Dependencies: Approved Step 2 requirements and existing prepared-post storage.
- Expected changes:
  - Add an approval-preparation service/API contract that resolves the pending target and authenticated approver, enforces existing approval rules, and returns a short-lived prepared canonical record.
  - Preserve the approval reply’s existing target-thread, target-post, identity, and board-tag semantics.
- Verification approach: Service/API tests cover eligible approval preparation plus unapproved, self-approval, already-approved, and invalid-target rejection.
- Risks or open questions:
  - Preparation must not alter approval state or write a canonical record.
- Canonical components/API contracts touched: `Application` approval entry-point authorization; `LocalWriteService` prepared-post contract; new approval preparation endpoint.

## Stage 2
- Goal: Finalize only a valid signed approval and store its proof atomically.
- Dependencies: Stage 1 prepared approval contract.
- Expected changes:
  - Add an approval finalization contract that revalidates authorization and target state, verifies the detached signature against the prepared approver identity, and commits the approval reply with its `.asc` signature.
  - Reuse the canonical signed-post verification and prepared-record safeguards; route successful approval state synchronization and invalidation through the existing approval-specific path.
  - Reject unsigned, expired, tampered, invalid, or stale prepared approvals without writing records or changing approval state.
- Verification approach: Write/API tests prove valid signatures create one paired record/signature and approval; failure cases leave repository and target approval status unchanged.
- Risks or open questions:
  - Finalization must preserve the existing approval-specific derived-state update rather than treating the record as an ordinary reply.
- Canonical components/API contracts touched: `LocalWriteService` detached-signature verification and approval synchronization; new approval finalization endpoint.

## Stage 3
- Goal: Provide one browser signing flow for prepared approvals.
- Dependencies: Stages 1–2 endpoints.
- Expected changes:
  - Extend the existing browser-key signing utilities to prepare, sign the returned canonical approval record, and finalize it using the selected local identity.
  - Expose shared success, identity-mismatch, unavailable-key, and signature-failure handling for approval consumers.
- Verification approach: Browser signing tests cover the prepared-record signature payload, identity mismatch, and failure feedback without an approval write.
- Risks or open questions:
  - Keep the prepare → sign → finalize round trip visible only as clear progress feedback, never as an unsigned fallback.
- Canonical components/API contracts touched: `browser_signing.js` signing helpers; approval prepare/finalize API payloads.

## Stage 4
- Goal: Move both approval surfaces onto the signed browser flow.
- Dependencies: Stage 3 shared browser flow.
- Expected changes:
  - Update the profile approval action and pending-users approval control to use the shared flow, disable duplicate submission while signing, and show actionable success/error feedback.
  - Retire direct unsigned approval completion; non-JavaScript or stale clients receive a clear signature-required failure instead of creating an unsigned record.
- Verification approach: Browser and application tests confirm successful signed approval from both surfaces, no duplicate record, and retained pending-list/profile updates.
- Risks or open questions:
  - Existing profile success redirects and pending-list in-place removal need equivalent signed-flow outcomes.
- Canonical components/API contracts touched: profile approval form/handler; `pending_approvals.js`; `pending_approvals.js` feedback contract; shared approval signing flow.

## Stage 5
- Goal: Verify signed approvals remain auditable through existing read surfaces.
- Dependencies: Stage 2 signed approval records and Stage 4 completed UI flow.
- Expected changes:
  - Add regression coverage showing a new approval’s adjacent signature, signer key, source link, and activity manifest are available without new display components.
  - Confirm approval-seed behavior is unchanged.
- Verification approach: Render the approval post plus Classic and Forte Activity after a signed approval; confirm signature/key visibility and unchanged seed approval state.
- Risks or open questions: none beyond avoiding display-specific forks for data already exposed by shared source metadata and manifest components.
- Canonical components/API contracts touched: `source_metadata.php`; shared activity commit manifest; Classic and Forte activity render tests.

## Approval Gate

Reply **Approved Step 3** to begin implementation.
