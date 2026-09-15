# Site Profile and Instance State Decoupling: Step 3 Development Plan

## Stage 1
- Goal: Make the default canonical repository and read-model database instance-scoped rather than site-profile-scoped.
- Dependencies: Approved Step 2 requirements and the existing local-state path helpers.
- Expected changes: Remove the site-profile input from default repository/database resolution while retaining explicit path overrides; keep presentation-cache resolution separate and profile-safe.
- Verification approach: Add path-contract tests proving default and Chouse profiles resolve the same repository/database and distinct presentation caches where needed.
- Risks or open questions:
  - Existing callers may still pass a site-profile argument and must be updated together.
  - Explicit repository/database environment overrides must retain highest precedence.
- Canonical components/API contracts touched: Local repository bootstrap path contract, front-controller bootstrap configuration, site-profile registry consumption.

## Stage 2
- Goal: Align operator commands and documentation with the single-state-per-instance contract.
- Dependencies: Stage 1 path contract.
- Expected changes: Make approval tooling and other affected local entry points use instance defaults independently of presentation; document explicit paths as the only way to select another instance.
- Verification approach: Exercise approval commands under both default and Chouse profile selection against isolated fixtures; verify both target the same instance unless explicit overrides differ.
- Risks or open questions:
  - Documentation must distinguish an instance boundary from a site profile and from a disposable static cache.
  - Existing production deployments with explicit paths must remain unchanged.
- Canonical components/API contracts touched: Approval CLI, local startup guidance, production deployment guidance, repository/database override contract.

## Stage 3
- Goal: Transition local development safely and verify the complete Chouse private-site flow against the authoritative instance state.
- Dependencies: Stages 1–2 complete and the current Chouse sandbox inventory confirmed.
- Expected changes: Preserve the old Chouse repository/database as a recoverable backup without merging duplicate bootstrap records; rebuild derived state if needed; remove temporary session-ID diagnostics after validation.
- Verification approach: Start Chouse with approved-members-only enabled, confirm D0EE remains approved and reaches the Board, confirm profile switching creates no repository/database, check profile-safe static rendering, and run the complete test suite.
- Risks or open questions:
  - The existing Chouse sandbox contains duplicate identity/bootstrap history that must not enter the authoritative repository.
  - Any local process still using explicit Chouse paths must be identified before archival.
- Canonical components/API contracts touched: Local canonical repository, derived read model, presentation cache, private-session authentication boundary, FDP implementation summary.
