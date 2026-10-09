> **Feature plan:** [Step 1](./feature_flag_change_attribution_step1_solution_assessment.md) · [Step 2](./feature_flag_change_attribution_step2_feature_description.md) · [Step 3](./feature_flag_change_attribution_step3_development_plan.md) · [Step 4](./feature_flag_change_attribution_step4_implementation_summary.md)

# Step 1: Solution Assessment — Signed Feature-Flag Changes

## Original Query

We need to record who changed a feature flag and sign the request.

## Understood Intent

Make each site-level feature-flag change attributable to the root-approved identity that authorized it, with a signature that can be verified independently of the server session.

## Problem

Feature-flag changes are authorized by a session hint but persist only a mutable snapshot and an unattributed activity entry, so the actor and their authorization are not durably verifiable.

## Options

### Option A: Add actor and signature fields to the mutable feature-flags record

The browser signs the updated feature-flags snapshot, which stores the latest actor and detached signature alongside its values.

- Pros: keeps one record and one visible source of current configuration.
- Cons: every later change overwrites prior attribution and signature evidence; cannot audit each individual change.

### Option B: Commit a signed immutable action record with each snapshot update

The browser signs a server-prepared feature-flag change record naming the flag, new value, actor, and request identity; the server verifies and commits it with the updated snapshot.

- Pros: preserves verifiable attribution for every change; reuses the established browser-signing and detached-signature trust model; keeps the existing snapshot as the fast runtime configuration source.
- Cons: adds a prepared/sign/finalize interaction and a new canonical action-record family to read-model and activity handling.

### Option C: Attribute the existing server request or git commit

The server records the session identity or signs the resulting commit when it writes the feature-flags snapshot.

- Pros: smallest browser and API change.
- Cons: proves only server-side authorization or repository provenance, not that the named identity signed the requested change; does not meet the requested user-held signature property.

## Recommendation

Choose **Option B** as one vertical slice: a root-approved operator changes one mutable flag through a prepared, browser-signed request; the verified immutable action record and detached signature are committed with the existing feature-flags snapshot and appear as attributed activity. This gives an auditable, end-to-end outcome without changing flag evaluation or environment-override behavior.

## Approval Gate

Reply **Approved Step 1** to proceed to the feature description.
