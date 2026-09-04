# iOS Users Page Cache Freshness Step 3 Development Plan

## Stage 1
- Goal: Invalidate users-page static artifacts whenever approved-user directory content can change.
- Dependencies: Approved Step 2; existing `StaticArtifactInvalidator` call sites for board threads, replies, identity links, and approvals.
- Expected changes: Extend static invalidation so `/users.html` and alternate `/users/index.html` are removed after approved content count changes, identity/profile approval changes, or explicit approvals. No database changes.
- Verification approach: Add focused smoke coverage that stale users artifacts are removed from both public and alternate static roots after a representative users-directory-affecting write.
- Risks or open questions:
  - Identity linking may only affect the approved directory when seeded approval or derived approval state exists, but including it keeps the route/data invalidation conservative.
  - Existing content writes may invalidate `/users/` more often than strictly necessary, but only when directory counts could change.
- Canonical components/API contracts touched: `StaticArtifactInvalidator`; `LocalWriteService` invalidation behavior; static `/users/` artifact contract.

## Stage 2
- Goal: Preserve static users-page serving when artifacts are current.
- Dependencies: Stage 1 invalidation behavior.
- Expected changes: Keep front-controller and artifact-builder route eligibility unchanged for anonymous, queryless `/users/` requests; add regression assertions if current coverage does not fully capture the route.
- Verification approach: Run existing front-controller static artifact smoke coverage and confirm `/users/` still serves current static HTML.
- Risks or open questions: None beyond ensuring the freshness fix does not accidentally make `/users/` fully dynamic.
- Canonical components/API contracts touched: `FrontController`; `StaticArtifactBuilder`; static `/users/` route contract.

## Stage 3
- Goal: Validate client-agnostic freshness and pending-user boundaries.
- Dependencies: Stages 1 and 2.
- Expected changes: No user-agent-specific code; pending approvals route remains excluded from anonymous static serving and authorization behavior remains unchanged.
- Verification approach: Run the local smoke test suite sections covering users, pending users, static artifacts, and version metadata; manually inspect that `/users/` freshness behavior is route/data driven rather than user-agent driven.
- Risks or open questions: Browser-owned HTTP cache behavior can vary by client, but route artifact invalidation and existing version metadata should provide the shared freshness boundary.
- Canonical components/API contracts touched: `/users/`; `/users/pending/`; `/api/version` metadata surface.
