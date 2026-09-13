# Approved Members Identity Publication Step 3 Development Plan

## Stage 1
- Goal: Define a private-mode-safe identity existence and publication state flow.
- Dependencies: Approved Step 2; existing browser identity and profile contracts.
- Expected changes: Make existing-profile detection work without exposing general profile lookup to lobby users; distinguish new, existing, prepared, and finalized identity states.
- Verification approach: Exercise each state as an unapproved user and confirm no duplicate identity is attempted.
- Risks or open questions:
  - Existing browser state may contain stale publication metadata.
- Canonical components/API contracts touched: `browser_signing.js`; identity prepare/finalize APIs; private access allowlist.

## Stage 2
- Goal: Make new identity publication complete automatically after key generation or import.
- Dependencies: Stage 1 state handling.
- Expected changes: Ensure the automatic flow always performs prepare, signs the canonical bootstrap, finalizes the identity, and records a recoverable user-facing failure; preserve manual linking only as fallback.
- Verification approach: Generate a new key as an unapproved user and verify identity, public-key, bootstrap, and profile creation.
- Risks or open questions:
  - Interrupted or expired prepared records must be safely retried.
- Canonical components/API contracts touched: Account key page; `prepare_identity`; `create_identity`; browser signing status surface.

## Stage 3
- Goal: Apply approval and establish access immediately after publication.
- Dependencies: Stage 2 finalized profile.
- Expected changes: Rebuild or incrementally refresh derived profile approval state; authenticate the published key; expose the own-profile link and allow the matching profile route.
- Verification approach: Test both seeded-approved and unapproved identities through Account, Lobby, own profile, and root navigation.
- Risks or open questions:
  - Approval may be seeded before or after identity publication.
- Canonical components/API contracts touched: Approval/read-model derivation; challenge/authentication APIs; Account, Lobby, and profile rendering.

## Stage 4
- Goal: Verify the private-site boundary remains closed during identity setup.
- Dependencies: Stages 1–3.
- Expected changes: Confirm all content, feeds, backups, downloads, static artifacts, and alternate APIs remain 404 for lobby users while identity lifecycle endpoints remain usable.
- Verification approach: Route matrix for anonymous, lobby, own-profile, and approved sessions; direct static-artifact and RSS checks.
- Risks or open questions:
  - Web-server configuration must route private-mode non-assets through the application.
- Canonical components/API contracts touched: Private feature flag; FrontController; rewrite rules; route allowlist.

## Stage 5
- Goal: Add regression coverage and operator documentation for one-shot setup.
- Dependencies: Stage 4 behavior stable.
- Expected changes: Add focused browser/server tests for new and existing identities, prepared-state recovery, approval inheritance, session continuity, and own-profile access; document the enabled-instance setup and troubleshooting signal.
- Verification approach: Focused tests, syntax checks, and a manual dev-server smoke test using a newly generated keypair.
- Risks or open questions:
  - Existing full-suite failures may need separation from this feature’s focused results.
- Canonical components/API contracts touched: Feature/authentication tests; Account/Lobby UI; README and production runbook.
