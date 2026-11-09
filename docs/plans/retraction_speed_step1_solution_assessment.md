> **Feature plan:** [Step 1](./retraction_speed_step1_solution_assessment.md) · [Step 2](./retraction_speed_step2_feature_description.md) · [Step 3](./retraction_speed_step3_development_plan.md) · [Step 4](./retraction_speed_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./retraction_speed_debug_log.md))

Effort study: `railspike-effort` / `v3/retraction_speed`; [host ledger](./effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 1 approved (Option A) on 2026-11-09; Step 2 drafted.

Cycle estimate: 0.25

## Original Query

Why does a retraction cause the site to do a reindex of some sort (does it?) I just did a retraction and it's taking a while to respond, so I'm assuming that's what it is. How do we fix this? Also, what other common activities can cause this, and how do we stop it?

[After cycle 1 merged:] I merged it, thank you. What is next? — Yes to both please. [Both: a small `deploy --status` fix, and Step 1 of `retraction_speed`.]

## Understood Intent

This is cycle 2 of the [read-model rebuild checklist](./read_model_rebuild_checklist.md). Since cycle 1, a full rebuild no longer stalls readers, but the person retracting still waits for one (minutes on qdb.us), and every cached page is deleted. A retraction should update the read model for that one post and clear only the pages that showed it. The checklist also put profile-name changes in this cycle.

## Problem Statement

A signed retraction triggers a full read-model rebuild and deletes every generated page, although it changes one post.

## What exists (from source)

- `LocalWriteService::finalizePreparedRetraction()` commits with `RetractionDeletion::commit()`, then calls `refreshDerivedStateAfterCommit()` (a full rebuild, now build-and-swap) and `invalidateRetractedPostArtifacts()` (`invalidateAllGeneratedPresentation()`).
- In a full rebuild, an accepted retraction is read through `AcceptedRemoval::all()` as a removal with no labels, and becomes the same placeholder an operator deletion produces (`retracted_at`, class, attribution; source path is the acceptance record).
- The `delete_record_speed` cycle built exactly that update for operator deletions: `IncrementalReadModelUpdater::applyPostRemoval()` (verified equal to a full rebuild across nine scenarios), `StaticArtifactInvalidator::invalidateRemovedPost()` (thread, post, quote, tag and author-profile pages, front pages and the offline snapshot), and a context check in `SourceLabelRemoval` that falls back to a full rebuild when the read model is not current or the post is an identity or hidden post. The retraction path uses none of it.
- Profile names are copied into many rows: `profiles`, `username_routes` (the first identity to claim a name keeps it), every post's author fields, approval labels and activity. A rename shows on every page where that identity posted.
- Timing reference: on a 30,000-quote copy (61 MB, qdb-sized), a full rebuild with swap took 8.8s locally; `delete_record_speed` measured the targeted removal at about 0.43s on a 310,000-file repository. Not yet measured on the hosts.

## Concepts touched

- All options: [post retraction](../concepts/post_retraction.md) (changed: derived-state refresh), [incremental source removal](../concepts/incremental_source_removal.md) (used, gains a second caller), [static publication](../concepts/static_publication.md) (used: targeted invalidation), [derived read-model rebuild](../concepts/read_model_rebuild.md) (fallback only).
- Option B also changes [profile delivery](../concepts/profile_delivery.md).

## Solution Options

### Option A: Retractions reuse the targeted removal; profile names move to cycle 3

**Estimate: 0.25**

After the retraction commit, run `applyPostRemoval()` with the acceptance record and no removed labels, then `invalidateRemovedPost()`. Fall back to the current full rebuild and full invalidation whenever the context check fails or the update throws. Prove equality with a full rebuild for root, reply, labelled and already-voted posts. Profile-name changes keep their full rebuild here and get cycle 3's background treatment.

- Pros: small, because every piece exists and is tested; the retracted text disappears at once (it does not wait for a queue); the cached site stays warm.
- Cons: profile-name changes stay slow for the person renaming until cycle 3.
- Driver: equality tests against a full rebuild for retraction-specific fields (class, attribution, source path, the retry and cleanup paths). Uncertainty: low.

### Option B: Option A plus incremental profile names

**Estimate: 0.65**

Also update a rename in place: the identity's profile row, username routes, the author fields of all its posts, approval labels and activity, then clear the pages of every thread it posted in, its profiles and the user list.

- Pros: renames become fast too.
- Cons: the first-claim rule for usernames across identities (several keys share one name) makes a correct partial update hard; any doubt falls back to the full rebuild. Renames are rare compared with retractions.
- Driver: username claim rules and the page set. Uncertainty: high.

### Option C: Queue the retraction's rebuild instead

**Estimate: 0.30**

Commit the retraction, mark the read model stale and queue a full rebuild (cycle 3's approach, applied early).

- Pros: the retractor returns at once; reuses cycle 1's queue.
- Cons: the retracted text stays visible until the rebuild finishes, minutes on qdb.us. For a retraction that is the wrong way round: something posted by mistake, such as a secret, must disappear immediately.
- Driver: stale-but-served behaviour for retractions. Uncertainty: low.

## Recommendation

**Option A.** It reuses the targeted removal already proven for operator deletions, so a retraction becomes about as fast as `delete-record` and its text disappears right away. Profile names are rare and hard to update in place; moving them to cycle 3, where a rename simply appears once the queued rebuild swaps in, costs little. That needs one line in the checklist.

**Vertical-slice viability:** on a qdb-sized copy, a signed retraction returns in about a second, the thread shows the "deleted" placeholder immediately, other cached pages stay, and the read model equals a fresh full rebuild.

## Enhancement Requests

- Profile names (from the checklist): Option A moves them to cycle 3; Option B keeps them here. Disposition: depends on the approved option.

## Study Checkpoint

2026-11-09T23:27:06Z; scope v1; starting revision `f046bd8` (`main` with cycle 1 merged); reference R1, hashes not re-verified. Claude, single exposed rater; the retraction, removal and profile-name code was read before rating, so no pristine intake is claimed.

- Intake after inspection and Step 1 handoff: **0.25** projected total for Option A.
- Alternatives: B 0.65, C 0.30, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
