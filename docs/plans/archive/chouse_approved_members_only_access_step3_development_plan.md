# Chouse Approved-Members-Only Access — Step 3: Development Plan

## Stage 1
- Goal: Define chouse’s canonical approved/lobby access decision.
- Dependencies: Approved Step 2; existing identity and approval resolution.
- Expected changes: Register a dedicated approved-members-only feature flag with a safe off default and environment/site-record configuration; define explicit approved, lobby-only, and unauthenticated outcomes independent of site/theme selection.
- Verification approach: Unit tests cover flag precedence, enabled/disabled instances, and all viewer states.
- Risks or open questions:
  - Identity hints must not be accepted as key-ownership proof by themselves.
- Canonical components/API contracts touched: `FeatureFlagRegistry`, `FeatureFlagEvaluator`, viewer-profile resolution, shared access-policy contract.

## Stage 2
- Goal: Bind the viewer to the browser-held identity key.
- Dependencies: Stage 1; existing Account/key and browser identity flows.
- Expected changes: Add or extend authentication so the selected identity is proven before approval status controls access; preserve local development compatibility.
- Verification approach: Test valid approved/unapproved identities, missing credentials, mismatches, and tampering.
- Risks or open questions:
  - Existing identity-hint cookie behavior may need a safe transitional path.
- Canonical components/API contracts touched: Account/key page, identity authentication flow, viewer-profile contract.

## Stage 3
- Goal: Add the lobby and enforce the allowlist on dynamic routes.
- Dependencies: Stages 1–2.
- Expected changes: Add `/lobby/`; allow lobby users only Lobby, Account, and their own profile; gate all other pages, profiles, threads, posts, feeds, APIs, tools, tags, activity, instance, and backups with 404 responses.
- Verification approach: Route-matrix tests and browser flow: Lobby → Account → own profile; another profile and protected routes fail; approved users retain full access.
- Risks or open questions:
  - Route aliases must not bypass the shared gate.
- Canonical components/API contracts touched: `Application::handle`, route handlers, lobby/account/profile templates, profile ownership check, 404 response.

## Stage 4
- Goal: Close static-artifact bypasses and prepare the production handoff.
- Dependencies: Stage 3.
- Expected changes: Restrict chouse static serving, RSS, generated downloads, and backup paths; keep lobby assets available; add regression tests and update the production runbook/configuration and rollback checks.
- Verification approach: Full suite plus approved/unapproved requests for HTML, RSS, APIs, artifacts, and backups; confirm zenmemes remains unchanged.
- Risks or open questions:
  - Old placeholder content and stale artifacts must be removed from the serving path before cutover.
- Canonical components/API contracts touched: `FrontController`, artifact builder/paths, feed/download handlers, test suite, production deployment contract.
