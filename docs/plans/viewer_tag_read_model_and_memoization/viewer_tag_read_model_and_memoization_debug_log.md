# viewer_tag_read_model_and_memoization — Railspike debug log

## 2026-10-24 UTC — Combined cycle created from B+A at Step 2 (Feature Description)

Merged the separately-sequenced `viewer_tag_read_model` (B) and `viewer_tag_lookup_memoization` (A) cycles into one combined cycle at the user's request, and drafted a combined Step 2. The two predecessors' artifacts are retained as history; this combined cycle supersedes them for execution.

### Necessary/useful instructions
- Plan-review / revision rules ("retain the Original Query / prior scope; record the revised direction separately"; "a revision does not approve the step"): drove preserving both predecessor Step 1/Step 2 docs intact and recording the merge as a new combined cycle with an explicit lineage note, rather than editing the originals. Also drove the explicit statement that the individual `Approved Step 1` marks no longer carry and a fresh `Approved Step 2` is required. Retain.
- "values above 1.0 prompt a split discussion": the combined ≈1.15 is over threshold, so the plan records it as a deliberate, user-chosen over-threshold decision and keeps the two-cycle split as the documented fallback. Retain — this is exactly the guardrail's trigger case.
- Extended-estimate rules ("every option a complete scoped estimate; do not sum/normalize alternatives; preserve recommended-cycle snapshot separately"): kept the combined ≈1.15 independent of the predecessor totals and options, and preserved the predecessor snapshots as history without re-rating. Retain.
- Shared component inventory requirement: re-tracing the five call sites against live source confirmed the `viewerHasThreadTag()` bypass (L280) and surfaced that `ViewerTagLookup` is currently static/PDO-free, so the SQL rewrite forces a signature change at every call site — now tracked as explicit touches. Retain; the inventory caught a real rewiring cost.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Per-stage stage-work bullets: begin at Step 3; noted, not applied in Step 2.
- Documentation-only file-scope rules: this is an application + schema change.

### Uncertain assessments
- Combined ≈1.15 is a single exposed, non-blind rater judgment, net of overlap between B's call-site rewiring and A's memo verification; the overlap size is the main uncertainty and could push it higher if Step 3 staging shows little sharing.
- Whether the combined scope should still be split remains genuinely open until Step 3 staging; the plan commits to raising a split then if the stages do not fit cleanly.

### Steering handled
- Mid-task steering asked to enable extended estimates. Both predecessor cycles already had extended estimates enabled, so the combined cycle inherits it; made that explicit in the Step 2 header (per-stage estimates begin at Step 3; per-option estimates retrospective; alternatives not summed) and preserved the predecessor option/recommended snapshots. No separate activation was needed.

## 2026-10-24 UTC — Step 3 (Development Plan), after Approved Step 2

Drafted a six-stage development plan grounded in live source (`ReadModelBuilder::indexPostReactions()`/`indexThreadLabels()`, `ReadModelSchema::statements()`, `ReadModelMetadata::SCHEMA_VERSION = 26`, `ViewerTagLookup`, and the `viewerHasThreadTag()` bypass at `ThreadAndPostPageController` L280).

### Necessary/useful instructions
- `read_model_hot_indexes` Step 3/Step 4 as a schema-change template: directly shaped the schema → version/spec → prior-version-rebuild-regression-through-normal-startup sequence and the "shared verification owned by one stage, not charged to every stage" rule. Reused the corrected startup-driven regression pattern (`Application::handle()` + `rebuild_reason = schema_identity_mismatch`) rather than the rejected predicate-plus-manual-rebuild approach. Retain — avoids repeating the review miss that cycle hit.
- Per-stage stage-work estimate rules (extended mode, begin at Step 3; cover only the stage's changes+verification+ordinary correction; not summed into the cycle total): produced S1–S6 annotations kept separate from the ≈1.15 reaffirmed total. Retain.
- "values above 1.0 prompt a split discussion": re-evaluated at Step 3 with concrete stages; the six stages fit one coherent branch, so no split is raised now, but the plan names the exact split boundary (S1–S2 = B, S3 = A) to use if Stages 3–4 prove larger than projected. Retain.
- Shared component inventory + reading the builder seam: confirmed the projection can piggyback on the two existing record-iteration passes (records already loaded with `authorIdentityId`), and that `ViewerTagLookup` being static/PDO-free forces a signature change at five call sites. Grounded Stages 2–3 in real seams rather than guesses. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Documentation-only file-scope rules: application + schema change.
- Branch/commit/implementation actions: gated behind `Approved Step 3`; not performed.

### Uncertain assessments
- Stage 3 ≈0.35 is the least certain: the memo's per-identity keying must match current filter logic exactly across five call sites, and the qdb per-tag loop must collapse to one query. This is the stage most likely to trigger the documented split if it runs large.
- The chosen `viewer_tags` grain (per record_family/target/identity/tag) assumes the current lookups only ever ask "does identity X have tag T on target Y"; if any caller needs counts or ordering, the grain would need revisiting. Flagged for Stage 3/4 validation.

## 2026-10-24 UTC — Implementation: Stages 1–2 (after Approved Step 3, branch feat/viewer-tag-read-model-and-memoization)

Planning commit `7a22f490`; Stage 1 `794ba967`; Stage 2 `127dd2e4`.

### Necessary/useful instructions
- Read-model schema-change checklist + the `read_model_hot_indexes` template: drove Stage 1 (schema + exact-set test sync) and Stage 2 (version bump 26→27 + spec changelog) as discrete, separately-committed steps. Verified the fingerprint changes and the metadata gate reports 27. Retain.
- "investigate before asserting" / read the real seam before coding: surfaced two correctness issues before they shipped — (1) the file-scan `ViewerTagLookup::threadTags` applies NO inactive/publication exclusion while the builder's derive loop does, so projecting from the builder's filtered set would have silently dropped viewer labels on retracted threads; (2) `dropSchema()` has a hardcoded table list that must include every new table or in-place rebuilds collide. Both caught and fixed. Retain — this is the highest-value instruction in the cycle so far.

### User decisions recorded
- Exclusions: user directed "ignore the exclusions, we're getting rid of them." Implemented as: the `viewer_tags` projection ignores `inactive`/`excludedPublicationPosts`, projecting the full loaded record set so SQL == file scan (byte-identical, including retracted/removed-source threads). Taken as the in-scope reading; the broader removal of those exclusions from the label/score/vote derivation itself was explicitly NOT done here and flagged as its own future change.
- Identity notation: user clarified `openpgp-` vs `openpgp:` are intermixed and not standardized. Implementation stores `author_identity_id` verbatim from the record (no normalization), so the projection matches whatever form the file scan compares against. The projection test uses the colon form the parser actually stores.

### Redundant/unnecessary instructions
- No supported findings.

### Uncertain assessments
- The dropSchema coupling means every future read-model table must be added there too; this is an existing fragility (hardcoded list), not introduced here, but it bit this cycle. Worth a follow-up note to derive the drop list from `statements()` rather than hand-maintain it. Out of scope for this cycle.
- Stage 2 projection correctness is verified at the data layer (projection == file-scan membership) on the parity fixture; the full cross-surface parity (board/Forte/qdb/thread rendering) is Stage 4, still pending.

### Next
- Stages 3–6 not yet started. Stage 3 (rewire `ViewerTagLookup` to SQL + request-scoped memo + fold in the `viewerHasThreadTag` bypass across five call sites) is the largest-risk stage and the documented split point if it runs large.
