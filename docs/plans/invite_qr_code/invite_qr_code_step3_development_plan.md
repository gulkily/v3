> **Feature plan:** [Step 1](./invite_qr_code_step1_solution_assessment.md) · [Step 2](./invite_qr_code_step2_feature_description.md) · [Step 3](./invite_qr_code_step3_development_plan.md) · [Step 4](./invite_qr_code_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

- **Outcome:** Approved member enters through Invite, generates a link and automatic inline QR, and the recipient scans and completes existing redemption, with or without a destination.
- **Recovery:** Hide prior results on generation, prevent overlapping submissions, and preserve the new copyable link with distinct feedback if QR rendering fails. Preserve existing permissions and invitation rules; no schema changes.
- **Release:** Pass regressions, independent decoding, theme/mobile checks, and real-phone redemption on a reachable address; record device/browser evidence. Missing phone evidence blocks completion. Verify packaged assets through normal page loading; production deployment is excluded. Rollback restores prior assets without altering invites.

## Key Risks

- **High risk: Wrong/exposed invite.** Validate repeated attempts and failure paths early; use local QR generation, one displayed link as payload, and a single in-flight issuance. Resolve before acceptance testing.
- **High risk: Unscannable/unreachable result.** Validate representative URL lengths, narrow screens, themes, and a reachable test address at first preview; use clear margins and fixed high contrast. Real-phone evidence gates release.
- **Encoder dependency:** Before integration, verify license, pinned provenance, browser compatibility, and independent decoding of representative links. Choose a bundled encoder with no runtime network dependency; unresolved suitability blocks integration.

## Stage 1

- **Goal:** Deliver the normal inline scan flow with safe rendering fallback (about 1 hour).
- **Dependencies:** Step 3 approval; feature branch; organize and commit approved plans and debug log before implementation. Resolve encoder suitability first.
- **Expected changes:** Bundle a reviewed encoder/license; wire invitation-only loading through `LobbyController`; extend `invites.php`, `invite_issuance.js`, and `invitations.css` with a labeled, responsive QR from the displayed URL and isolated rendering failure.
- **Verification approach:** Generate through Invite; independently decode, check Copy/encoder failure and local asset loading. Run `php tests/run.php InvitationIssuanceTest LocalAppSmokeTest` with focused additions.
- **Risks or open questions:** Unreadable output blocks scanning; check first preview, adjust sizing/contrast, and resolve before dependent work.
- **Canonical components/API contracts touched:** Invite result, feedback, stylesheet, asset loader; unchanged issuance APIs. Private renderer signature follows encoder selection.

## Stage 2

- **Goal:** Make repeated generation and failures safe (about 1 hour).
- **Dependencies:** Stage 1 verification, summary update, and commit.
- **Expected changes:** Clear old link/QR at attempt start, guard concurrent submits, restore controls after every outcome, and distinguish issuance failure from QR-only failure.
- **Verification approach:** Extend `InvitationIssuanceTest`: successive successes, delayed duplicates, preparation/signing/finalization failures after success, missing/throwing encoder, and retry; assert pairing, cleared results, and fallback.
- **Risks or open questions:** Stale callbacks or stuck controls threaten correctness/retry; test delayed responses early and enforce one active attempt.
- **Canonical components/API contracts touched:** `invite_issuance.js` and existing result/feedback controls; issuance and revocation contracts unchanged.

## Stage 3

- **Goal:** Verify the complete recipient experience and release readiness (about 1 hour).
- **Dependencies:** Stage 2 verification, summary update, and commit; reachable test site and real phone available.
- **Expected changes:** Add missing end-to-end regression coverage; record acceptance evidence and fix in-scope defects.
- **Verification approach:** Scan/redeem from Invite with/without destinations; check themes, narrow viewport, keyboard/labels, long origins, expiry/revocation, and access gating. Run invitation, approval-policy, application smoke, and changed-file syntax/whitespace checks.
- **Risks or open questions:** Unreachable addresses or missing hardware prevent acceptance; establish reachability first and keep release blocked until verified.
- **Canonical components/API contracts touched:** Invite navigation, lobby redemption/identity/destination flow, and tests; existing APIs retained.

Each stage ends with applicable verification, a Step 4 summary update, and its own commit before the next stage. Split work exceeding the estimate before implementation; keep the feature within one day.
