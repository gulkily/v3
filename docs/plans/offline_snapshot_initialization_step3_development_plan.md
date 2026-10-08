# Offline Snapshot Initialization — Step 3 Development Plan

> **Feature plan:** [Step 1](./offline_snapshot_initialization_step1_solution_assessment.md) · [Step 2](./offline_snapshot_initialization_step2_feature_description.md) · [Step 3](./offline_snapshot_initialization_step3_development_plan.md) · [Step 4](./offline_snapshot_initialization_step4_implementation_summary.md)

## Completion Contract

- Normal entry: the standard initial public read-model rebuild runs with no
  served offline snapshot.
- End-to-end outcome: after model promotion, setup atomically publishes and
  verifies the first public snapshot before reporting readiness.
- Required recovery: a failed eligible bootstrap reports failure, retains any
  valid snapshot, and can be retried with the supported offline publisher.
- Deployment/external verification: clean public deployment returns the valid
  canonical snapshot URL; approved-members-only deployment remains unpublished.
- Release condition: automated coverage and the first-time runbook document
  the readiness gate; normal update-driven task-queue publication still works.

## Key Risks

- **High risk: privacy exposure.** Early validation: exercise
  approved-members-only mode with no snapshot. Mitigation: reuse its existing
  publication guard and treat the mode as an intentional skip.
- **High risk: false readiness.** Early validation: test both the served-path
  lookup and canonical endpoint after bootstrap. Mitigation: require valid
  SQLite output before successful setup reporting.
- **High risk: publication failure after model promotion.** Early validation:
  inject a publisher failure with a prior snapshot. Mitigation: preserve atomic
  publication and state the manual retry path without rolling back the model.
- **Duplicate work.** Early validation: rebuild with an existing snapshot and
  exercise later queue publication. Mitigation: publish only when the served
  snapshot is absent; leave the queue contract unchanged.

## Stage 1

- Goal: Define the reusable initial-snapshot readiness action.
- Dependencies: Existing `OfflineSnapshotLocator`, `OfflineSnapshotPublisher`,
  feature-flag evaluator, and atomic publication contract.
- Expected changes: Add a small offline bootstrap service with a conceptual
  `ensure(string $databasePath): BootstrapResult` contract that distinguishes
  published, already-available, and intentionally unavailable states; it
  publishes only when no valid served snapshot exists.
- Verification approach: Focused tests cover a missing snapshot, an existing
  valid snapshot, publisher failure retaining the prior artifact, and
  approved-members-only skip behavior.
- Risks or open questions:
  - Impact: wrong path resolution can create an unserved artifact.
  - Early warning / validation: assert the locator resolves the result.
  - Mitigation: use the locator and publisher roots already used by serving.
- Canonical components/API contracts touched: Offline snapshot locator,
  publisher, public-only feature flag, atomic snapshot artifact contract.

## Stage 2

- Goal: Make initial public rebuild readiness invoke the bootstrap action.
- Dependencies: Stage 1; successful read-model candidate promotion.
- Expected changes: Extend the canonical rebuild/provisioning CLI path to call
  the bootstrap action after promotion, report its result, and fail initial
  public readiness clearly when a required first publication fails; do not
  alter visitor request handling or the update-driven task queue.
- Verification approach: CLI integration coverage proves a clean public rebuild
  creates a valid served snapshot, preserves the no-snapshot members-only case,
  and returns actionable failure output; regression-run task-queue tests.
- Risks or open questions:
  - Impact: a completed model could be mistaken for a launch-ready deployment.
  - Early warning / validation: assert the CLI distinguishes model promotion
    from snapshot readiness in output and exit behavior.
  - Mitigation: make initial-publication failure explicit and retain the
    documented retry command.
- Canonical components/API contracts touched: `rebuild_read_model.php`, `v3
  rebuild`, offline bootstrap service, task-queue snapshot publication.

## Stage 3

- Goal: Document and validate the production-ready first-time flow.
- Dependencies: Stages 1–2; existing offline diagnosis command and deployment
  runbooks.
- Expected changes: Update first-time setup/pre-launch and offline-reading
  guidance with automatic bootstrap, intentional private-mode behavior, failure
  recovery, and canonical endpoint verification; register focused tests.
- Verification approach: Run focused bootstrap/CLI/locator tests and the task
  queue regression suite; execute the documented clean-instance flow and
  inspect the snapshot response with offline diagnostics.
- Risks or open questions:
  - Impact: operators may retain an obsolete manual launch sequence.
  - Early warning / validation: review all first-time and publication commands
    cited by the runbooks.
  - Mitigation: designate the automatic rebuild path as canonical while keeping
    manual publication solely as recovery.
- Canonical components/API contracts touched: Production deployment runbook,
  offline-reading runbook, offline diagnosis CLI, test runner registration.
