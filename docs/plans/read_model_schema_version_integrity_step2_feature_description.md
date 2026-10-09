> **Feature plan:** [Step 1](./read_model_schema_version_integrity_step1_solution_assessment.md) · [Step 2](./read_model_schema_version_integrity_step2_feature_description.md) · [Step 3](./read_model_schema_version_integrity_step3_development_plan.md) · [Step 4](./read_model_schema_version_integrity_step4_implementation_summary.md)

## Problem

Read-model schema freshness relies on a manually incremented version number. If a schema change ships without that increment, an existing database is treated as current and can fail on a normal request.

## User Stories

- As a site operator, I want a deployment with changed read-model DDL to rebuild an old local read model automatically so that visitors do not encounter stale-schema failures.
- As a developer, I want the readable schema generation to remain visible so that I can communicate and diagnose releases without interpreting a hash.
- As an operator, I want diagnostics to show both the readable generation and the integrity state so that I can distinguish a healthy model from one awaiting recovery.

## Core Requirements

- Keep a human-readable, intentionally increasing read-model schema generation.
- Add an automatically derived integrity identifier for the read-model DDL and require both identifiers to match before a database is considered current.
- Treat missing or mismatched integrity metadata as stale and use the existing exclusive-lock rebuild recovery path.
- Expose the stored and expected schema identity clearly through the existing operator diagnostics surface.
- Preserve current behavior for repository-root and canonical-content-revision freshness checks.

## Delivery Scope

- Work type: application change; internal maintenance vertical slice.

## Completion Boundary

Normal entry is a deployment whose read-model DDL differs from the database already on disk. The next request detects that the model is stale and rebuilds it once under the existing lock; subsequent requests use the rebuilt model. Recovery is the existing rebuild failure/status path. The slice is releasable when a changed schema is detected even if the readable generation was not incremented, and an existing database without integrity metadata is safely refreshed.

## Risks

- **Incomplete identity comparison:** a writer or candidate-validation path could omit the integrity identifier, causing inconsistent freshness decisions. Validate all existing metadata producers and consumers before Step 3; define one shared identity contract.
- **Unstable integrity identifier:** incidental source or formatting edits could force unnecessary rebuilds. Validate the identifier against the canonical DDL representation; make that representation the shared source for schema creation and identity.
- **Coverage gap for non-DDL rebuild needs:** changed derivation semantics may require a rebuild without changing the DDL. Confirm the boundary before Step 3 and retain an explicit readable generation bump for intentional non-DDL rebuilds when needed.

## Shared Component Inventory

- **Read-model metadata contract:** extend the canonical metadata producer and all existing freshness consumers; do not create a parallel status mechanism.
- **Operator codebase/read-model status output:** extend the existing diagnostics surface to display stored and expected schema identity; no new public UI is needed.
- **Automatic rebuild path:** reuse the existing per-request stale detection and exclusive-lock recovery flow; no new recovery route is needed.

## Simple User Flow

1. A deployment contains changed read-model DDL while an earlier local database remains on disk.
2. The next request compares the database identity with the running application's expected identity.
3. The mismatch marks the model stale and invokes the existing serialized rebuild.
4. The request proceeds with a current read model; operators can confirm the readable generation and integrity state in diagnostics.

## Success Criteria

- A DDL change causes existing databases to rebuild even when the readable generation is unchanged.
- Databases built before integrity metadata existed are rebuilt safely once.
- A readable schema generation remains present in stored metadata and operator diagnostics.
- All existing read-model freshness decisions agree on whether the database is current.

Waiting for "Approved Step 2" before drafting Step 3.
