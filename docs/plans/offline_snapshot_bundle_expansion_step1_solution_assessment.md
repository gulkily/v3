# Offline Snapshot Bundle Expansion — Step 1 Solution Assessment

> **Feature plan:** [Step 1](./offline_snapshot_bundle_expansion_step1_solution_assessment.md) · [Step 2](./offline_snapshot_bundle_expansion_step2_feature_description.md) · [Step 3](./offline_snapshot_bundle_expansion_step3_development_plan.md) · [Step 4](./offline_snapshot_bundle_expansion_step4_implementation_summary.md)

## Original Query

I want to expand the size of the offline content bundle, and at the same time ensure that it contains the latest content as much as possible. Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md

## Understood Intent

Increase the bounded public offline snapshot's useful reading coverage while
minimizing both publication delay and the delay before a reconnecting browser
can read newly published public content.

## Problem statement

The current 25 MiB, 50-recent-thread snapshot may omit useful recent public
discussion, and a larger replacement bundle can increase the delay before new
content is published and downloaded.

## Option A — Raise the existing fixed limits

Increase the snapshot byte and recent-thread limits while retaining the current
pinned-first, recent-activity selection and event-driven atomic publication.

- Pros: Smallest change; preserves the public-only boundary, whole-thread
  behavior, and existing browser reader contract.
- Cons: A larger download may still exclude recent threads behind large pinned
  content and increases refresh latency; direct read-model changes can still
  miss the publication request.

## Option B — Expand the base bundle with a low-latency recent-content update

Adopt a larger bounded base snapshot, then publish a compact, atomic
recent-content update ahead of its coalesced replacement so connected readers
can receive changed public threads without first downloading the larger base.

- Pros: Increases coverage without copying the full read model; makes new
  content available with a smaller transfer; keeps normal writes responsive,
  retains the last valid base, and supports recovery of missed triggers.
- Cons: Requires a versioned reader/cache contract for the base and recent
  update, plus a download/storage budget; disconnected browsers still cannot
  receive content until they reconnect.

## Option C — Rebuild and replace the complete bundle after every update

Publish the complete enlarged snapshot synchronously after each eligible public
write and have connected browsers replace their saved copy immediately.

- Pros: Simplest freshness semantics; no second content artifact to combine.
- Cons: Adds build and large-download latency to frequent updates, repeats
  work during bursts, and risks coupling write success to publication failure.

## Recommendation

Choose **Option B**. It is a viable vertical slice: enlarge the bounded public
base, continue selecting the newest eligible content after required pinned
threads, and deliver a small latest-content update before the coalesced full
replacement. This addresses publication and transfer latency without making
public writes wait for a full rebuild; it does not promise content to a browser
that has remained offline.
