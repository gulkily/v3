# Offline Snapshot Publish Step 3 Development Plan

## Stage 1
- Goal: Establish an independent, atomic local publication contract for the
  bounded offline snapshot.
- Dependencies: Approved Steps 1–2; `PublicOfflineSnapshotBuilder`.
- Expected changes: Add a publisher that builds to a temporary sibling and
  atomically replaces the independent snapshot path, returning source, target,
  and snapshot metadata without invoking static rendering.
- Verification approach: Focused tests prove a valid snapshot is published and
  a builder failure preserves a pre-existing valid snapshot.
- Risks or open questions:
  - Publication must stay on the same filesystem so replacement is atomic.
  - The builder's existing public-data and size bounds must remain unchanged.
- Canonical components/API contracts touched: New offline snapshot publisher;
  `PublicOfflineSnapshotBuilder::build()` reuse contract.

## Stage 2
- Goal: Make the public endpoint and diagnosis accurately select the fast
  publication while preserving existing releases.
- Dependencies: Stage 1; existing anonymous snapshot route.
- Expected changes: Resolve the independent valid snapshot before the active
  static-release fallback; report the selected source in offline diagnosis.
- Verification approach: Route and diagnostic tests cover fast snapshot,
  release fallback, invalid/missing fast snapshot, and anonymous-only access.
- Risks or open questions:
  - A missing or invalid fast artifact must never hide a valid release fallback.
  - Approved-members-only must continue to block both sources.
- Canonical components/API contracts touched: `FrontController` offline snapshot
  resolver; `diagnose_offline_reading.php` local inspection contract.

## Stage 3
- Goal: Expose the fast, guarded publication workflow to operators.
- Dependencies: Stages 1–2; site-profile and feature-flag configuration.
- Expected changes: Add `./v3 offline publish` with database/static-root
  options, explicit freshness guidance, concise metadata output, and refusal in
  approved-members-only mode.
- Verification approach: Command tests confirm no static-page builder is
  invoked, defaults honor the active site profile, options are validated, and
  guarded mode leaves public publication unavailable.
- Risks or open questions:
  - The command cannot make a stale read model current; output must say so.
  - Reader shell and `sql-wasm.wasm` deployment remain outside this command.
- Canonical components/API contracts touched: `v3` offline command group; new
  publish script; feature-flag evaluator; site-profile defaults.

## Stage 4
- Goal: Document and protect the operator recovery path.
- Dependencies: Stages 1–3.
- Expected changes: Update the offline-reading runbook and command help; add
  regression coverage to the project runner.
- Verification approach: Run focused publisher, command, route, and diagnostic
  tests; follow the documented publish-then-diagnose flow against a local
  release root.
- Risks or open questions:
  - Documentation must distinguish snapshot publishing from browser cache
    refresh and application-asset deployment.
- Canonical components/API contracts touched: `docs/runbooks/offline_reading.md`;
  command help; `tests/run.php` registration.
