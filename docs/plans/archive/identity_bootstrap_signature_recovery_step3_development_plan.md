# Identity Bootstrap Signature Recovery: Step 3 Development Plan

## Stage 1

- Goal: Recover once from the known identity-bootstrap signature-verification failure.
- Dependencies: Approved Steps 1–2; existing prepared identity bootstrap flow.
- Expected changes: Preserve the bootstrap failure cause through the browser identity publisher; extend its retry predicate to make one fresh prepare-and-finalize attempt only for that cause.
- Verification approach: Simulate first-attempt failure then success; simulate two failures and assert two attempts, no published marker, and existing recovery guidance.
- Risks or open questions:
  - The retry boundary must exclude unrelated key, write, and authentication failures.
- Canonical components/API contracts touched: `browser_signing.js`; existing `/api/prepare_identity` and `/api/create_identity` error contract.

## Stage 2

- Goal: Define one canonical readiness gate for protected actions.
- Dependencies: Stage 1.
- Expected changes: Add the shared action-readiness contract, conceptually `ensureActionIdentity(root, statusNode, options): Promise<void>`, over the existing identity-readiness flow.
- Verification approach: Unit-test that the contract resolves only after Stage 1 recovery succeeds and surfaces the final error after a second failure.
- Risks or open questions:
  - Status-node availability differs among protected-action surfaces.
- Canonical components/API contracts touched: `browser_signing.js` identity-readiness contract.

## Stage 3

- Goal: Route all protected actions through the shared readiness gate without replaying writes.
- Dependencies: Stages 1–2.
- Expected changes: Migrate direct signing actions to the shared gate and retain compose/reaction callers on it; preserve each action's existing submission and feedback behavior.
- Verification approach: Simulate recovery for compose, reaction, approval, invitation issuance, and invitation redemption; assert one intended action after readiness and no duplicate submission.
- Risks or open questions:
  - A recovery retry must not replay an already-finalized action.
- Canonical components/API contracts touched: `browser_signing.js`, `thread_reactions.js`, `pending_approvals.js`, `invite_issuance.js`, `invite_redemption.js`, and existing action endpoints.

## Stage 4

- Goal: Give operators useful, secret-safe evidence when bootstrap verification fails.
- Dependencies: Stage 1 failure classification and existing verifier result.
- Expected changes: Record a sanitized server-side diagnostic for bootstrap verification failures; retain the current safe browser-facing error boundary and avoid new persistence.
- Verification approach: Exercise a failed bootstrap fixture and assert the diagnostic excludes public-key armor, private-key material, and detached-signature payloads.
- Risks or open questions:
  - Production log retention and access must support the chosen diagnostic channel.
- Canonical components/API contracts touched: `LocalWriteService::createIdentityBootstrap`, `OpenPgpSignatureVerifier`, and the server logging boundary.

## Stage 5

- Goal: Prove recovery preserves the established setup and private-session flows.
- Dependencies: Stages 1–4.
- Expected changes: Add focused browser-flow and server-fixture regression coverage; no database schema changes.
- Verification approach: Run the affected browser-signing, private-site authentication, reaction, invitation, and write-service tests; manually verify a new-key setup and a recovered protected action in a production-like session.
- Risks or open questions:
  - Mocked browser tests cannot replace a production browser/OpenPGP compatibility check.
- Canonical components/API contracts touched: Existing test harnesses and the identity bootstrap/publication/authentication contracts.
