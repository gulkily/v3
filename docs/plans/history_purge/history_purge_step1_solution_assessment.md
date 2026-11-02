> **Feature plan:** Step 1 · [Step 2](./history_purge_step2_feature_description.md) · [Step 3](./history_purge_step3_development_plan.md) · [Step 4](./history_purge_step4_implementation_summary.md)

Debug mode: enabled (project marker).
Effort study: `railspike-effort` / `v3/history_purge`; [ledger](../effort_estimation_trial.md#cycle-v3history_purge), standing P2 enrollment.
Extended estimates: enabled.

**Cycle estimate: 0.80**

## Original Query

Then, please write step 1 for your top suggestion.

## Understood Intent

The top suggestion was to make the retained retraction record sufficient after Git history is purged. Inspection shows the record itself is now defined: release 1 deletes the post and commits a signed request, detached signature and a `Key: value` acceptance receipt with no prose ([removal acceptance v1](../../specs/removal_acceptance_record_v1.md), [unified removal format](../unified_removal_format/unified_removal_format_step4_implementation_summary.md)). The open work is release 2 from the [2026-10-24 handoff](../retraction_simplification_handoff.md): purging deleted prose from Git history deliberately, while the retained receipts and the rest of the site keep working. This assessment compares ways to do that. It does not approve implementation, history rewriting or deployment.

## Problem

Deleted post prose remains recoverable from the repository's Git history on the server, and nothing defines how an operator removes it without breaking what depends on commit identity.

## What depends on history today

Found by inspection; Step 2 must confirm each.

- `commits` table and the Activity `commits` view, `activity.source_commit_sha` and `evidence_json`, and the commit-manifest cache: derived, rebuildable, but keyed by commit hash ([schema](../../specs/read_model_schema_v1.md)).
- `/source/blob/{commit}/...` and `/source/commits/{commit}` public links ([SourceFileController](../../../src/ForumRewrite/Http/SourceFileController.php)); the "old links keep working" principle applies.
- Legacy posts without `Created-At` take their date from `git log --follow` during rebuild ([CanonicalRecordRepository](../../../src/ForumRewrite/Canonical/CanonicalRecordRepository.php)); exports already bake `records/post-timestamps/` instead.
- Image backups record `repository_head` and `.git` pointer files for restore ([ImageBackup](../../../src/ForumRewrite/Images/ImageBackup.php)).
- Interrupted-import journals under `.git/instance-import/`.
- Not dependent: acceptance receipts (content hashes only), repository downloads (history-free since 2026-10-24), ordinary rebuild from the working tree.

## Concepts touched

- Post retraction and operator removal: used; no inventory entry exists, proposed.
- History purge: created, proposed entry.
- Source history links (`/source/blob`, `/source/commits`): changed under A and B, proposed entry.
- [Content snapshot export](../../concepts/content_snapshot_export.md): unchanged.
- [Instance content import](../../concepts/instance_content_import.md): unchanged; imports already refuse to resurrect accepted deletions.

## Option A — Targeted path purge with a commit map

**Estimate: 0.80**

An operator-run `./v3` command reads accepted `delete` receipts, rewrites history to drop the deleted post and signature paths from every commit using built-in Git (`filter-branch` index filter, then reflog expiry and `gc --prune`), records the old-to-new commit mapping as a `Key: value` record in the repository, and rebuilds the read model. Old `/source/*` links resolve through the map. Everything not deleted keeps per-commit provenance.

- Pros: purges exactly what was retracted; preserves Activity history and evidence for all surviving records; receipts need no change; built-in Git only, no dependency.
- Cons: `filter-branch` is slow on a large repository and must run with writes paused; every hash after the first affected commit changes, so the map is mandatory and external references (image manifests, bookmarks) still break without it; verifying the prose is really gone (packs, reflogs, other clones) needs its own check; the cost grows with history length, not with the number of retractions.
- Driver: rewrite performance and the hash-map plumbing. Uncertainty: 0.70–0.95, depending on whether a QDB-sized repository rewrites in acceptable time.

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*

## Option B — Horizon squash

**Estimate: 0.60**

Collapse all history older than a retention window (for example 90 days) into a single root commit holding the tree at that point, then replay the recent window on top. Deleted prose disappears once its deletion commit ages past the horizon and a purge runs. Recent commits keep provenance; older ones lose it. Run time is bounded by the window, not by total history.

- Pros: simple, bounded and repeatable on a schedule; no per-path surgery; matches the handoff's accepted delayed unavailability; built-in Git only.
- Cons: still rewrites every retained hash, so the map and `/source/*` handling from A are needed anyway; loses commit provenance and Activity commit history for everything older than the window, which changes what Activity shows; legacy post dates must be baked into `records/post-timestamps/` before the first run; prose remains recoverable until it ages out.
- Driver: deciding what Activity and source views say about pre-horizon history. Uncertainty: 0.50–0.75.

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*

## Option C — Fresh-start squash

**Estimate: 0.35**

Replace the whole history with one new root commit of the current tree, on demand. All deleted prose goes at once.

- Pros: smallest and fastest; trivially verifiable.
- Cons: every commit link, Activity commit entry and evidence reference breaks at once; legacy dates must be baked first; repeated use turns history into a sequence of fresh starts, which contradicts the record-in-Git principle in spirit.
- Driver: breadth of breakage, not code. Uncertainty: 0.30–0.45.

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*

## Recommendation

**Option A**, with a sizing gate before Step 3 commits to it: time a `filter-branch` rewrite on a QDB-derived copy; if it cannot finish within an operator-acceptable pause, fall back to B with the same command surface and map record. Either way the first release is one vertical slice: operator CLI under the existing repository lock, worklist from accepted delete receipts, rewrite, `Key: value` commit map record, mandatory rebuild, `/source/*` map resolution, verification that no purged path exists in any reachable or dangling object, and a runbook covering pre-purge backup and clones that still hold old history. Out of scope: automatic scheduling, purging anything a receipt does not cover, downloads (already history-free), backups and mirrors held by others, and any change to receipt formats.

Timing is batched and operator-run, never per retraction: an immediate rewrite at acceptance is the production-host mistake the [design mistakes checklist](../retraction_design_mistakes_checklist.md) records.

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*

## Enhancement Requests

None recorded. The discovery essays and mirroring proposal written in the same session are separate documents, not requests against this cycle.

## Study snapshots and approval

2026-10-30T20:30:04Z; baseline `2e17c971308a710a540c905e738048c4f51e53ca` on `main`; scope v1/A; R1/P2; Claude single exposed rater. All three frozen R1 hashes verified against the ledger. Intake: no pristine pre-inspection estimate exists; the handoff, receipt spec, removal-format closeout, exporter, import, source-route and read-model history consumers were inspected while writing the Nostr comparison and before this rating. Step 1 handoff whole-cycle **0.80** for A. Alternatives B 0.60 and C 0.35 are complete-cycle estimates, not additive. Anchors: unified removal format 0.70, retraction simplification Step 3 0.85, startup dependency recovery 1.0. No implementation, tests, dependencies, measured work or accepted outcome. Related cycles `v3/unified_removal_format` and `v3/retraction_effects` keep their own histories. Await Approved Step 1 before Step 2.

Debug: [Step 1 report](history_purge_debug_log.md#2026-10-30t203004z--step-1).

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*

Approval update — 2026-10-30 UTC: user wrote "history_purge approved step 1"; Option A proceeds to Step 2. Original snapshot and alternatives retained.

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*
