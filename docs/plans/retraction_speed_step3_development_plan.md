> **Feature plan:** [Step 1](./retraction_speed_step1_solution_assessment.md) · [Step 2](./retraction_speed_step2_feature_description.md) · [Step 3](./retraction_speed_step3_development_plan.md) · [Step 4](./retraction_speed_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./retraction_speed_debug_log.md))

Effort study: `railspike-effort` / `v3/retraction_speed`; [host ledger](./effort_estimation_trial.md#cycle-v3retraction_speed). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 approved on 2026-11-09; Step 3 drafted; implementation unapproved.

**Cycle estimate: 0.25** (reaffirmed)

## Completion Contract

- Normal entry: an author retracts from the site, or an agent retracts through the API (`prepare_retraction`, `create_prepared_retraction`).
- End-to-end outcome: the retraction returns in about a second; the thread shows the deleted placeholder immediately; unrelated cached pages stay; the read model equals a fresh full rebuild.
- Required recovery: when the targeted path cannot be used or fails, the retraction completes through today's full rebuild and full page clearing; a repeated request still recovers a half-finished retraction.
- Deployment/external verification: one signed retraction of a test post by "claude" on zenmemes.com, timed, with the thread and one unrelated cached page checked.
- Release condition: local equality, fallback and timing tests, then the zenmemes.com check.

## Key Risks

- **High risk:** a retraction field differs from an operator deletion, so the targeted result drifts from a full rebuild. Validate in Stage 2 with equality tests before anything else in that stage. Mitigate: extend `applyPostRemoval()` for the field, or fall back.
- The targeted page set misses a page that showed the post. Validate in Stage 2 by comparing against the pages a full build would clear for that post. Mitigate: the same set already serves `delete-record`; add the missing page there for both.

## Stage 1 - Removal context check is shared

**Estimate: 0.04**

- Goal: one place decides whether a post can be removed with the targeted update.
- Dependencies: none.
- Expected changes: move `SourceLabelRemoval::incrementalContext()` into a small shared class (for example `RemovalUpdateContext::forPost()`) returning thread id, tags and author profile slug, or null. `SourceLabelRemoval` calls it; behaviour unchanged.
- Verification approach: `delete-record` tests (`SourceLabelRemovalTest`, incremental removal tests) and the smoke set.
- Estimate details: driver: a mechanical move; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `SourceLabelRemoval`.

## Stage 2 - Retractions update one post and its pages

**Estimate: 0.14**

- Goal: a retraction updates the read model and cached pages for that post only, with the full rebuild as fallback.
- Dependencies: Stage 1.
- Expected changes: in `finalizePreparedRetraction()`, take the context before the commit; after it, run `applyPostRemoval()` with the accepted retraction, `AcceptedRetraction::path()` and no removed labels, then `invalidateRemovedPost()`. On no context or any error, run today's refresh and full clearing. Clear the stale marker on success and set it on failure, as the full path does. Remove `invalidateRetractedPostArtifacts()` if nothing else uses it.
- Verification approach: new tests comparing the read model after a targeted retraction with a fresh full rebuild for a thread root, a reply, a post with labels and a thread with other replies; a forced failure of the targeted update completes through the full rebuild; an unrelated static page survives; repeating a half-finished retraction recovers. Existing retraction tests in `WriteApiSmokeTest` and `SingleRequestPostingTest`.
- Estimate details: driver: equality tests over all post tables; uncertainty: medium.
- Risks or open questions:
  - Impact: a mismatch would show wrong state until the next full rebuild.
  - Early warning / validation: equality tests written first.
  - Mitigation: fix the field, or route that case to the fallback.
- Canonical components/API contracts touched: `LocalWriteService::finalizePreparedRetraction()`, `IncrementalReadModelUpdater::applyPostRemoval()`, `StaticArtifactInvalidator::invalidateRemovedPost()`.

## Stage 3 - Timing, docs and the live check

**Estimate: 0.07**

- Goal: measured speed, updated docs, and the zenmemes.com check.
- Dependencies: Stage 2.
- Expected changes: concept entries [post retraction](../concepts/post_retraction.md) and [incremental source removal](../concepts/incremental_source_removal.md); the [checklist](./read_model_rebuild_checklist.md) moves profile names to cycle 3.
- Verification approach: time a signed retraction on the 30,000-quote local copy (target under 1 second, against about 9 seconds today). After deploy, "claude" retracts a test post on zenmemes.com through `frm.py`; record the response time, the thread placeholder and that an unrelated cached page still exists. Smoke set plus changed tests.
- Estimate details: driver: the host round trip; uncertainty: low.
- Risks or open questions: if the host time misses about a second, report it before closeout.
- Canonical components/API contracts touched: concept entries and checklist only.

## Concepts Touched

[Post retraction](../concepts/post_retraction.md) changed (Stage 2); [incremental source removal](../concepts/incremental_source_removal.md) changed (Stages 1–2); [static publication](../concepts/static_publication.md) used; [derived read-model rebuild](../concepts/read_model_rebuild.md) used (fallback).

## Study Checkpoint

2026-11-09T23:32:38Z; scope v1/A, starting revision `f046bd8`, R1/P2; R1 hashes not re-verified. User explicitly approved Step 2. Whole-cycle **0.25** reaffirmed. Stage work 0.04/0.14/0.07, non-additive. Claude, single exposed rater. Await Approved Step 3.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
