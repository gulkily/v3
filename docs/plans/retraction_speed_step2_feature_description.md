> **Feature plan:** [Step 1](./retraction_speed_step1_solution_assessment.md) · [Step 2](./retraction_speed_step2_feature_description.md) · [Step 3](./retraction_speed_step3_development_plan.md) · [Step 4](./retraction_speed_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./retraction_speed_debug_log.md))

Effort study: `railspike-effort` / `v3/retraction_speed`; [host ledger](./effort_estimation_trial.md#cycle-v3retraction_speed). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 1 approved (Option A) on 2026-11-09; Step 2 drafted; implementation unapproved.

**Cycle estimate: 0.25** (reaffirmed)

## Problem

A signed retraction rebuilds the whole read model and deletes every cached page, although it changes one post. The person or agent retracting waits for the rebuild, and every page is re-rendered on its next visit.

## User Stories

- As an author, I want a retraction to finish in about a second so that I know quickly that my post is gone.
- As an agent retracting through the API, I want the same quick, clear result so that a cleanup of several posts does not take minutes.
- As a visitor, I want the retracted post gone from the thread right away, and the rest of the site to stay as fast as before.

## Core Requirements

- **Targeted update.** After the retraction commit, the read model is updated for that one post: the same placeholder a full rebuild would produce (retraction time, class, attribution, acceptance record as source), the thread's labels and title refreshed, and the read model's commit recorded.
- **Same result as a full rebuild.** For every case the targeted path handles, the read model afterwards equals a fresh full rebuild.
- **Targeted page clearing.** Only the pages that showed the post are cleared: its thread and post pages, its quote page, its tag pages, its author's profile, front pages and the offline snapshot. Other cached pages stay.
- **Safe fallback.** When the read model was not current before the commit, or the post is an identity or hidden post, or the targeted update fails, the retraction does what it does today: a full rebuild (build and swap) and full page clearing. The result and retry behaviour stay as they are.
- **Retracted text disappears immediately.** The update happens inside the retraction request, not later in the queue.

## Delivery Scope and Completion Boundary

Work type: application change, plus touched concept entries, the cycle checklist (profile names move to cycle 3) and this cycle's records and study-ledger bookkeeping. No new dependencies. Out of scope: profile-name changes (cycle 3), operator `delete-record` (already targeted), the retraction cleanup sweep (unchanged).

Completion boundary: on zenmemes.com, a signed retraction of a test post through the agent API (as "claude") returns in about a second; the thread shows the deleted placeholder right away; a cached page elsewhere on the site, opened before the retraction, still has its static file afterwards. On a qdb-sized copy locally, the same retraction returns in under a second and the read model equals a full rebuild. Release condition: local equality and timing tests, then the zenmemes.com check.

Known environments: retractions come from the site (an author) and from agents through the API with `frm.py`. The operator deploys zenmemes.com from an iPhone and runs qdb.us on a shared host over SSH. If the targeted update fails on a host, the retraction falls back to the full rebuild and still succeeds; the operator sees the longer time, and `./v3 status` shows no stale marker afterwards. Evidence is the API response time and page state on zenmemes.com; local runs do not satisfy the host check.

## Shared Component Inventory

- **Targeted removal** (`IncrementalReadModelUpdater::applyPostRemoval()`): reuse with the retraction's acceptance record, its path and no removed labels; extend only if a retraction field differs from an operator deletion.
- **Context check** (`SourceLabelRemoval::incrementalContext()`, private): move to one shared place so both callers decide the same way; no copy.
- **Targeted page clearing** (`StaticArtifactInvalidator::invalidateRemovedPost()`): reuse unchanged.
- **Retraction write** (`LocalWriteService::finalizePreparedRetraction()`, `invalidateRetractedPostArtifacts()`, `retractionResult()`): change the refresh and clearing steps only; commit, signature and result format stay.
- **Full rebuild** (`LocalWriteService::rebuildReadModel()`, build and swap since cycle 1): the fallback, unchanged.

## Risks

- **A retraction field differs from an operator deletion** (attribution, source path, activity row). Validate first with an equality test against a full rebuild; mitigate by extending `applyPostRemoval()` for that field.
- **Retry of a half-finished retraction.** `retractionResult()` retries a stale read model with a full rebuild; confirm the targeted path leaves no stale marker when it succeeds and sets one when it fails.
- **Pages the targeted clearing misses** (for example a board page listing the thread). The operator-deletion path has the same set; compare with a full page build in a test.

## Simple User Flow

1. An agent prepares and signs a retraction of its test post on zenmemes.com.
2. It submits it; the response comes back in about a second with `retraction_status: complete`.
3. Opening the thread shows the deleted placeholder.
4. Other pages load from cache as before.

## Success Criteria

- On a qdb-sized copy, a signed retraction returns in under 1 second (today: a full rebuild, about 9 seconds locally and minutes on qdb.us).
- The read model after a targeted retraction equals a fresh full rebuild for a root post, a reply, a labelled post and a post in a thread with other replies.
- Unrelated cached pages keep their files after a retraction.
- A forced failure of the targeted update still completes the retraction through the full rebuild.

## Concepts Touched

[Post retraction](../concepts/post_retraction.md) changed (derived-state refresh); [incremental source removal](../concepts/incremental_source_removal.md) changed (second caller, shared context check); [static publication](../concepts/static_publication.md) used; [derived read-model rebuild](../concepts/read_model_rebuild.md) used (fallback).

## Enhancement Requests

- Profile names (from the checklist): moved to cycle 3 per approved Option A; the checklist is updated in this cycle.

## Study Checkpoint

2026-11-09T23:30:18Z; scope v1/A, starting revision `f046bd8`, R1/P2; R1 hashes not re-verified. User explicitly approved Step 1 (Option A). Whole-cycle **0.25** reaffirmed. Claude, single exposed rater. No implementation, measured labor or accepted outcome. Await Approved Step 2.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
