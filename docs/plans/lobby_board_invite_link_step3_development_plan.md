# Redeemable Board Invitations — Step 3: Development Plan

## Stage 1 - Invitation lifecycle foundation
- Goal: Define the canonical, signed invitation and redemption state that extends approval without storing a raw secret.
- Dependencies: Existing signed approval, identity, canonical-record, and write-serialization lifecycles.
- Expected changes: Add invitation issuance, revocation, and one-use redemption contracts; persist only the verification hash, issuer, validated optional internal destination, status, and audit references; expose conceptual `issueInvitation`, `revokeInvitation`, and `redeemInvitation` service operations.
- Verification approach: Service tests cover valid state transitions, issuer authorization, expiry, revocation, duplicate use, concurrent redemption attempts, hash-only audit data, and destination normalization.
- Risks or open questions:
  - Decide the canonical representation that lets the issuer's signature authorize a recipient not yet known at issuance.
  - Preserve the existing approval-derived membership model rather than adding a parallel access flag.
- Canonical components/API contracts touched: Canonical record parsers/paths, signed-write lifecycle, invitation state store, approval-state derivation.

## Stage 2 - Site-wide invitation issuance and destination selection
- Goal: Let an approved identity create and revoke an invitation from any board page, with an optional target destination.
- Dependencies: Stage 1 invitation lifecycle and current browser signing/authentication.
- Expected changes: Extend the canonical approved-member navigation/action across board pages; add the current-page or selected internal destination to issuance; sign the issuer's authorization, show the secret only at creation, and emit issued/revoked Activity events containing the verification hash.
- Verification approach: Browser/API tests confirm issuance from representative board pages, current and selected valid targets, rejection of external or invalid targets, issuer authorization, and Activity containing the verification hash but never the raw secret or share link.
- Risks or open questions:
  - Normalize destinations without creating an open redirect or preserving a stale private URL.
- Canonical components/API contracts touched: Shared navigation/action, Account/profile controls, browser signing service, invitation issue/revoke APIs, private-route gate.

## Stage 3 - Lobby identity creation and redemption
- Goal: Guide an invitee from the Lobby through a fresh disposable key, username selection, and invitation redemption.
- Dependencies: Stage 1 lifecycle, Stage 2 issuance, existing identity publication and authentication flow.
- Expected changes: Extend Lobby and Account key status with invitation context; accept redemption only after a newly generated browser key proves control; retain the validated invitation destination through the flow; add the redemption endpoint to the lobby allowlist.
- Verification approach: Browser-flow tests cover new-key issuance through successful redemption with and without a destination, username conflict, invalid/expired/revoked/reused secret, and no key-restore path.
- Risks or open questions:
  - An unredeemed identity may remain in Lobby after an interrupted flow; it must not receive access.
- Canonical components/API contracts touched: Lobby template, Account key page, browser identity publication/authentication, identity APIs, invitation redemption API, access allowlist.

## Stage 4 - Approval derivation and session transition
- Goal: Turn a valid redeemed invitation into the inviter-attributable approved state and admit only the verified recipient session.
- Dependencies: Stages 1-3 and existing approval-derived read model.
- Expected changes: Derive approval from the signed invitation and verified redemption, update the read model and Activity feed with a redeemed event containing the verification hash, then reuse challenge authentication to establish the recipient session and redirect to its validated destination or the board.
- Verification approach: Integration tests confirm the invite chain is attributable, Activity connects issued and redeemed events by verification hash, a target opens only after key authentication, and failed redemption remains lobby-only across pages, feeds, APIs, downloads, and artifacts.
- Risks or open questions:
  - Ensure the one-use transition and approval derivation cannot diverge after a write failure.
- Canonical components/API contracts touched: Approval derivation/read model, authenticated session, private access gate, profile/account status.

## Stage 5 - Security regression coverage and operator guidance
- Goal: Make invite behavior observable, supportable, and resistant to secret leakage or access regressions.
- Dependencies: Stages 1-4.
- Expected changes: Add route-matrix, Activity visibility, target-destination, and secret-handling regressions; document issuance from any page, target rules, revocation, expiry, disposable-key behavior, verification-hash visibility, and incident response in the operator runbook.
- Verification approach: Full targeted test suite plus manual issue-from-thread → Activity → redeem → selected-destination smoke test and revoked/used-secret rejection checks.
- Risks or open questions:
  - Validate browser/history and server logging behavior on the deployed HTTPS configuration.
- Canonical components/API contracts touched: Feature tests, private-site route matrix, production/operator documentation.
