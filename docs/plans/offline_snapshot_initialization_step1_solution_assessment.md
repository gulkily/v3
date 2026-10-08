# Offline Snapshot Initialization — Step 1 Solution Assessment

> **Feature plan:** [Step 1](./offline_snapshot_initialization_step1_solution_assessment.md) · [Step 2](./offline_snapshot_initialization_step2_feature_description.md) · [Step 3](./offline_snapshot_initialization_step3_development_plan.md) · [Step 4](./offline_snapshot_initialization_step4_implementation_summary.md)

## Original Query

On a new instance, I often see this 404, I guess because the offline snapshot generation is not automatic. Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md to remedy this.

## Understood Intent

Ensure a newly provisioned public instance creates a valid public offline
snapshot before its reader/service worker requests `/offline/snapshot.sqlite3`.

## Problem statement

First-time setup rebuilds the read model but does not guarantee an offline
snapshot, so the public snapshot URL can return 404 until a later publish.

## Option A — Document a manual initial publish

- Pros: No application behavior change; uses the existing publisher.
- Cons: Relies on an operator remembering an extra step; does not make
  publication automatic or prevent recurrence.

## Option B — Build the snapshot on its first public request

- Pros: No deployment-step change; creates the artifact only when needed.
- Cons: Makes a visitor request perform publication work; introduces concurrent
  request and failure behavior; the first reader still cannot rely on success.

## Option C — Bootstrap publication during initial deployment

- Pros: Extend the successful initial read-model/setup lifecycle to invoke the
  existing atomic public-snapshot publisher before the instance is ready;
  preserves the existing public-only boundary and leaves request handling
  read-only. Reuses the queue/publisher's validation and diagnostics without
  requiring a later content update to seed the artifact.
- Cons: Adds bounded setup time and requires initial-publication failure to be
  visible to the deployer; approved-members-only instances remain intentionally
  without a public snapshot.

## Recommendation

Choose **Option C**. Make initial offline-snapshot publication an explicit,
automatic completion condition of first-time provisioning after the read model
is ready, and verify the endpoint before launch. This is a small vertical slice:
a fresh public instance serves a valid snapshot without changing normal
request-time behavior or the established update-driven refresh path.
