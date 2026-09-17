# Stale Fingerprinted Asset Recovery Step 1 Solution Assessment

## Problem Statement

Static HTML can outlive the fingerprinted CSS or JavaScript files it references, causing private-browsing visitors to receive 404s and an unstyled page.

## Option A: Runtime stale-fingerprint recovery

Pros:
- Protects users immediately when an old static page references a missing asset.
- Handles deployment races and partially refreshed static artifacts.
- Requires no database or content-model changes.

Cons:
- Keeps serving responsibility in the request path for a deployment problem.
- Needs careful cache behavior so recovered responses do not become misleading immutable assets.

## Option B: Atomic artifact-and-asset deployment

Pros:
- Prevents HTML and fingerprinted assets from becoming inconsistent.
- Preserves the correctness of long-lived immutable asset caching.
- Addresses the underlying deployment boundary directly.

Cons:
- Requires deployment and static-build workflow changes.
- Does not protect users who already received an older artifact from a prior release unless old assets remain available.

## Option C: Remove fingerprinted asset URLs

Pros:
- Eliminates stale hash references.
- Simplifies asset lookup and deployment behavior.

Cons:
- Loses reliable cache invalidation and immutable caching benefits.
- Creates broader changes to the asset pipeline and cache headers.

## Recommendation

Recommend Option A combined with Option B.

Brief justification:
- Atomic deployment should prevent new mismatches, while runtime recovery provides a defensive guarantee for already-cached or partially deployed pages. Removing fingerprinting is a disproportionate trade-off.
