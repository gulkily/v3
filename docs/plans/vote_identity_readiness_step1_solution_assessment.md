> **Feature plan:** [Step 1](./vote_identity_readiness_step1_solution_assessment.md) · [Step 2](./vote_identity_readiness_step2_feature_description.md) · [Step 3](./vote_identity_readiness_step3_development_plan.md) · [Step 4](./vote_identity_readiness_step4_implementation_summary.md)

# Vote Identity Readiness — Step 1: Solution Assessment

## Original Query

Whenever I vote, it says "preparing identity..." first. I think that this step should already be pre-done by the time the user votes, ideally.

## Understood Intent

When a browser already stores a usable local identity (the public key and the private key required to sign), complete its voting readiness before the first vote. Do not create a new identity or prompt for one in the background; keep that existing first-action flow for browsers without local identity material.

## Problem Statement

Even when a browser already has local identity material, its fingerprint, server publication, or identity hint may not be ready until the voter clicks, delaying an otherwise-ready first vote.

## Option A — Complete readiness for existing local identities during idle time

On vote-capable pages, silently finish the existing identity's required readiness work before a click, but run no identity-generation path unless the visitor deliberately begins an action.

- Pros: matches the requested boundary for both manually created and automatically created local identities; removes avoidable first-vote latency without creating new identities; reuses the current signing and recovery flow.
- Cons: may publish or otherwise contact the server about an existing local public key during page idle time; a vote can still wait if the background task has not completed.

## Option B — Prime only local cryptographic state and defer server readiness

During idle time, load signing tools and derive the stored identity fingerprint, but continue to publish or verify the identity only when a vote is selected.

- Pros: avoids a background server-side identity association; keeps the current privacy boundary for keys that have not yet been published.
- Cons: does not make an unpublished or unverified local identity immediately vote-ready; the first vote can still display identity preparation.

## Option C — Automatically create identities on vote-capable pages

Run automatic guest-identity creation and publication during idle time for visitors without any local identity, in addition to priming existing identities.

- Pros: maximizes the chance that every first vote is immediately ready.
- Cons: exceeds the requested scope by creating identities for passive readers; changes the current consent and first-action behavior for visitors who have no local identity.

## Recommendation

Choose **Option A**. It is a viable vertical slice: vote-capable pages make an already stored identity ready in the background, while no-local-identity visitors retain the current prompt, generation, and publication flow at their intentional first action. Step 2 should define the acceptable background server contact for an existing but not-yet-published local public key and verify that no generation occurs during prewarming.
