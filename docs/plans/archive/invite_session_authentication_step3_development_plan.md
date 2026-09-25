# Invite Session Authentication: Step 3 Development Plan

## Stage 1
- Goal: Make an existing authenticated session available on ordinary public application pages without allocating sessions for anonymous readers.
- Dependencies: Approved Step 2.
- Expected changes: Extend the canonical viewer-session lifecycle to resume a presented existing session on safe public HTML requests; pass the resulting authenticated viewer state into the shared public layout and navigation.
- Verification approach: Add focused application tests for valid, expired, missing, and invalid session-cookie states; assert that anonymous public requests do not start a new session and authenticated public pages expose the approved viewer state.
- Risks or open questions:
  - An expired or invalid cookie must behave as unauthenticated without causing a replacement session for an anonymous reader.
- Canonical components/API contracts touched: `Application` session startup and authenticated-viewer resolution; `TemplateRenderer` shared navigation contract.

## Stage 2
- Goal: Restore an approved member's session automatically from a usable saved browser key on a safe public-page visit.
- Dependencies: Stage 1; existing challenge/signature authentication APIs.
- Expected changes: Extend the existing browser-key authentication and resume-state surfaces so a public page with no valid session can request proof of key possession and receive a new session; retain clear Account/recovery feedback when restoration cannot succeed.
- Verification approach: Add browser-script coverage for successful restoration, unavailable key, pending approval, failed signature, and expired-challenge retry; add application coverage for the resulting authenticated session.
- Risks or open questions:
  - Restoration must not loop or obscure a genuine browser-key error.
  - Browser-key assets must load safely on the supported public HTTP and HTTPS origins.
- Canonical components/API contracts touched: `private_site_auth.js`, `authentication_resume.php`/Account recovery surfaces, `/api/auth_challenge`, `/api/authenticate_identity`, and `/api/auth_status`.

## Stage 3
- Goal: Make Invite consistently reflect and consume the shared restored session.
- Dependencies: Stages 1–2.
- Expected changes: Make public navigation derive Invite visibility from authenticated viewer state; route Invite navigation, invitation page access, and invitation preparation through the shared recovery/session boundary while retaining existing browser signing and final server verification.
- Verification approach: Add application and browser-navigation tests for an authenticated member, a member whose session is restored before visiting Invite, direct `/invites/` access without a session, and unconfigured/pending browsers; verify no unsigned or unauthorized invitation is published.
- Risks or open questions:
  - Direct invitation navigation needs a clear recovery result without treating the identity-hint cookie as authorization.
- Canonical components/API contracts touched: `TemplateRenderer` navigation, `auth_navigation.js`, `invite_navigation.js`, `/invites/`, `/api/prepare_invitation`, `/api/create_prepared_invitation`, and `invite_issuance.js`.

## Stage 4
- Goal: Prove the complete public-session boundary and guard against regressions.
- Dependencies: Stages 1–3.
- Expected changes: Add focused regression coverage and concise operator-facing behavior documentation for public session restoration, anonymous browsing, invitation authorization, and non-replay of unsafe requests.
- Verification approach: Run targeted PHP and browser-script suites plus the full test suite; verify a public anonymous page creates no session, a returning approved member restores one, and POST/API/download requests are never automatically retried.
- Risks or open questions:
  - Coverage must distinguish a session being resumed from a session being newly created after valid key proof.
- Canonical components/API contracts touched: Existing application/browser test suites and public authentication documentation.
