> **Feature plan:** [Step 1](./approval_seed_immediate_refresh_step1_solution_assessment.md) · [Step 2](./approval_seed_immediate_refresh_step2_feature_description.md) · [Step 3](./approval_seed_immediate_refresh_step3_development_plan.md) · [Step 4](./approval_seed_immediate_refresh_step4_implementation_summary.md)

## Original Query

I've been told that `./v3 approve` or `./v3 approval seed` requires a complete rebuild. We don't want that to be the case, but want it to apply without delay. Please write Step 1 of `docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md`.

## Problem Statement

Both CLI commands currently rebuild the entire read model after seeding approval, making immediate application depend on unnecessary full-rebuild work.

## Current Evidence

- Both commands route to the same seed operation in [v3](../../v3); [the seed script](../../scripts/inject_approval.php) writes the canonical seed, commits it when applicable, and unconditionally rebuilds the read model before reporting success. This is a read-model rebuild, not an application build or deployment.
- User-to-user approval already has incremental refresh and rebuild recovery in [LocalWriteService](../../src/ForumRewrite/Write/LocalWriteService.php). The [incremental updater](../../src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php) already reads seeds when deriving approval chains, attribution, activity author state, and affected scores, but its approval entry point requires a new approval reply.

## Option A: Synchronous Incremental Seed Refresh

Extend the existing approval-refresh approach to seed writes so both CLI spellings apply the full approval effect before reporting success, without rebuilding a healthy, current read model.

- **Pros:** Reuses established semantics; immediate visibility; preserves canonical seeds and root attribution; avoids unrelated rebuild work.
- **Cons:** Must cover transitive approvals, attribution-only changes, affected scores, and cached surfaces; requires explicit recovery behavior for unavailable or stale read models and partial failures.

## Option B: Consult Canonical Seeds on Reads

Make approval-sensitive reads consult seed records directly so a new seed can take effect without a write-time rebuild.

- **Pros:** Direct seed visibility immediately after persistence; little additional work in the CLI command.
- **Cons:** Spreads approval derivation across readers; risks disagreement between permissions, approval chains, attribution, activity, and scores; adds recurring read cost.

## Option C: Defer the Existing Rebuild

Persist the seed and schedule a rebuild after the CLI returns, shortening command execution while retaining the existing derivation path.

- **Pros:** Small conceptual change; retains current rebuild semantics.
- **Cons:** Still requires a complete rebuild; approval remains delayed until background work finishes; does not satisfy the requested outcome.

## Recommendation

Choose **Option A**. Define “without delay” as approval being effective on the next applicable read after command success, with no background wait or separate operator rebuild. Preserve seed semantics rather than generating a user approval reply.

The vertical slice covers either CLI command through canonical persistence, approval-chain and attribution refresh, approval-sensitive access and display, affected activity/scores, and cache freshness. Verify equivalence to a fresh rebuild, including transitive effects and seeding an already-approved identity; measure latency on representative data rather than promise zero processing time. No schema change is expected.

For a missing, stale, or incompatible read model, or failed incremental refresh, recommend explicit recovery reporting rather than a silent full rebuild or false success. If the seed was persisted, report that fact and allow safe retry after repair. Full rebuild remains an exceptional repair tool, not a prerequisite of ordinary seed approval. Keep the work scoped to these two commands and shared approval behavior needed for this outcome.
