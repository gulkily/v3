> **Feature plan:** [Step 1](./viewer_tag_read_model_step1_solution_assessment.md) · [Step 2](./viewer_tag_read_model_step2_feature_description.md) · [Step 3](./viewer_tag_read_model_step3_development_plan.md) · [Step 4](./viewer_tag_read_model_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/viewer_tag_read_model`; [ledger](../effort_estimation_trial.md), standing enrollment P2.

Extended estimates: enabled (2026-10-24). Per-option estimates below; per-stage stage-work estimates begin at Step 3. Option estimates are not summed or normalized into the recommended-cycle total.

Sequence: **first of two** cycles for viewer-tag lookups (B then A). This cycle moves lookups to the read model; the successor [`v3/viewer_tag_lookup_memoization`](../viewer_tag_lookup_memoization/viewer_tag_lookup_memoization_step1_solution_assessment.md) then wraps the resulting SQL path in a request-scoped memo. Distinct cycle identity; each gets its own branch after its own `Approved Step 3`.

## Original Query

[Predecessor Option B, promoted to the first cycle in a B-then-A sequence.] We can do A on top of B for more efficient lookups per page, right? Let's do Path 1 ... Wait, I think it will be more efficient to do both together, no? Or B then A?

## Understood Intent

Deliver the read-model-backed viewer-tag lookups **first**, per the agreed B-then-A ordering. Project reaction/label records into the SQLite read model and answer viewer-tag questions with indexed per-identity queries, replacing the full-directory file scans. The successor memo cycle then sits in front of this SQL path, so the end state is both fewest file reads and fewest queries — and no work is discarded (B-then-A avoids the throwaway that A-then-B would incur).

## Problem

Viewer-tag questions (like/flag on posts; like/upvote/downvote/labels on threads) are answered by globbing and parsing the entire `records/post-reactions/` and `records/thread-labels/` directories, so per-request cost scales with the whole site's reaction/label corpus instead of with the read model.

## Option A — Project reactions/labels into dedicated read-model table(s), query by identity

Extend `ReadModelBuilder` to write reaction/label records into new read-model table(s) keyed/indexed by `author_identity_id` (+ target id, tag), then change `ViewerTagLookup` to answer via an indexed `WHERE author_identity_id = ? AND tag = ?` query. Fold the independent `viewerHasThreadTag()` scan into the same path.

- Pros: removes filesystem scanning entirely; scales with the read model; correct grain for per-(identity, target) state; reuses the proven schema-change + rebuild machinery; leaves a clean SQL seam for the successor memo to wrap.
- Cons: read-model schema change (version bump, spec, prior-version rebuild regression test, `./v3 rebuild`); builder projection/indexing work; rewires every `ViewerTagLookup` call site to SQL.
- Estimate: **≈0.9**; driver: builder projection + new indexed table(s) + call-site rewiring on top of the full schema-change checklist; uncertainty: medium–high (projection correctness and rebuild cost).

## Option B — Denormalize viewer-tag state onto existing `posts`/`threads` rows

Fold per-identity tag state into the existing post/thread projections (e.g. JSON columns the board query already hydrates) rather than dedicated rows.

- Pros: no new tables; fits the existing hydrate path.
- Cons: viewer-tag state is per-(identity, target) and does not belong on a single shared post/thread row; bloats every row, still needs per-identity filtering, and indexes by identity poorly.
- Estimate: **≈0.8**; driver: reworking the hydrate path and still solving per-identity filtering; uncertainty: high (wrong grain invites later rework).

## Option C — In-memory index built once per process (not per request)

Parse the records once per PHP process/worker and hold the identity index across requests.

- Pros: avoids both the per-request scan and a schema change.
- Cons: the runtime serves largely per-request PHP processes, so a cross-request cache has weak lifetime guarantees and real staleness/invalidation risk on reaction writes; fragile for little gain.
- Estimate: **≈0.35**; driver: small cache layer; uncertainty: high on correctness, and it does not reach the durable end state.

## Recommendation

**Option A.** It is the durable first half of the B-then-A plan: reactions/labels live in the read model as indexed per-identity rows, viewer-tag questions become indexed lookups, and the resulting SQL path is exactly what the successor memo cycle wraps. Treat it as a read-model schema change under the full `CONTRIBUTING.md` checklist, mirroring the completed `read_model_hot_indexes` cycle. Reject Option B (wrong grain for per-identity state) and Option C (cross-request staleness risk; not durable).

Verify: reaction/label records projected correctly on rebuild; viewer flag/like/vote/label state byte-identical to the file-scan path across classic board, Forte, qdb, and single-thread; a prior-version read model rebuilds before the new query runs; `./v3 rebuild` succeeds.

## Follow ups and scope

Ships **before** the memo cycle. In scope: projecting reaction/label records into new indexed read-model table(s), and rewiring `ViewerTagLookup` (plus the folded-in `viewerHasThreadTag`) to query them. Out of scope: record formats, reaction write paths, UI, and the request-scoped memo itself (that is the successor cycle, which wraps this SQL path). No per-page query coalescing here; the successor memo absorbs the remaining per-request query multiplication.

## Effort snapshot and approval

- Cycle `v3/viewer_tag_read_model`, scope v1; anticipated starting revision: current `main` at this cycle's branch time; reference R1 / protocol P2; single exposed, non-blind rater; UTC 2026-10-24.
- Intake: recorded after the predecessor's call-site trace and the completed `read_model_hot_indexes` schema-change cycle as a cost reference; prior exposure disclosed. No pristine pre-inspection estimate claimed.
- Step 1 handoff projected total (Option A, against recovered R1): **≈0.9** — read-model schema change + builder projection/indexing + call-site rewiring + the full schema-change checklist; near the upper R1 range, well above `read_model_hot_indexes` (≈0.30) because it adds projection and rewiring rather than only indexes. Independently judged, not the sum of the option estimates above. If Step 2/3 inspection pushes this toward or past 1.0, raise a split discussion before advancing.
- Documentation boundary: this cycle's plans/debug log and ledger bookkeeping. Planning writes remain uncommitted.
- Await `Approved Step 1` before Step 2; this is the first cycle of the sequence but still begins no work until its own approvals. Recommendation may be revised without advancing.
