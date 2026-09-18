# Session Reauthentication: Step 3 Development Plan

## Stage 1
- Goal: Define one safe return-target contract for protected HTML navigation.
- Dependencies: Approved Step 2.
- Expected changes: Add `safeResumeTarget(string $requestUri): string` and route classification in `Application`; retain same-site path/query targets and use `/` for invalid or unsafe input.
- Verification approach: Add focused tests for Board, deep-thread, and query-string targets plus absolute, protocol-relative, malformed, API, and write-target rejection.
- Risks or open questions:
  - URL fragments are available only to in-page navigation interception.
- Canonical components/API contracts touched: `Application` members-only access gate and redirect behavior.

## Stage 2
- Goal: Give every expired safe HTML GET a recoverable server response.
- Dependencies: Stage 1.
- Expected changes: Add the shared authentication-resume page and `renderAuthenticationResumePage(string $returnTo): string`; route unauthenticated protected HTML GETs there while retaining explicit API, download, and non-GET failures.
- Verification approach: Extend local application smoke tests for Board, thread, profile, and query-string requests; assert the resume contract and no Lobby/403 response for an approved-member recovery path.
- Risks or open questions:
  - The server cannot know whether the browser has a usable key; failure must remain actionable.
- Canonical components/API contracts touched: `Application` route gate, `message.php` recovery outcome, `lobby.php`/Account recovery links.

## Stage 3
- Goal: Reauthenticate from the shared resume surface and replace the history entry with the original destination.
- Dependencies: Stages 1–2; existing challenge/signature APIs.
- Expected changes: Extend `PrivateSiteAuth.authenticate(options)` with a validated return target and history-replacement success behavior; expose the resume target through the page's existing authentication-state contract.
- Verification approach: Extend Node-based private-site-auth tests for success, retry after expired challenge, missing keys, pending approval, and destination retention.
- Risks or open questions:
  - Authentication/network failures must retain the target without creating a reload loop.
- Canonical components/API contracts touched: `private_site_auth.js`; `/api/auth_challenge` and `/api/authenticate_identity` response contract.

## Stage 4
- Goal: Recover before navigation when an already rendered member page is still open.
- Dependencies: Stage 3.
- Expected changes: Add a read-only authentication-status contract and a shared navigation guard for same-origin protected links; authenticate before navigation only when the session is absent, preserving an in-page URL fragment where available.
- Verification approach: Add browser-script tests for valid sessions, expired sessions, unsafe/external links, failed recovery, and concurrent navigation attempts.
- Risks or open questions:
  - Coordinate simultaneous tabs or clicks so they do not create competing authentication flows.
- Canonical components/API contracts touched: new `GET /api/auth_status` contract, shared layout script loading, `PrivateSiteAuth` public API.

## Stage 5
- Goal: Make the interim PHP-session retention policy deliberate and verify the complete recovery boundary.
- Dependencies: Stages 1–4; agreed idle, absolute, and browser-restart policy.
- Expected changes: Configure and document the selected PHP cookie/retention behavior; add recovery telemetry limited to aggregate outcome and latency; explicitly defer application-owned opaque sessions to a follow-up feature.
- Verification approach: Verify effective runtime settings and run targeted authentication, local smoke, and full regression suites; confirm telemetry excludes identities, keys, signatures, and session tokens.
- Risks or open questions:
  - PHP/session-store cleanup must honor the documented policy in every deployment environment.
  - Session lifetime values require product and security agreement before implementation.
- Canonical components/API contracts touched: `startViewerSession()`, deployment PHP-session configuration, authentication observability contract.
