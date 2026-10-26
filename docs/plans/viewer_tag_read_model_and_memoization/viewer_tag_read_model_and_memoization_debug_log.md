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

## 2026-10-24 UTC — Implementation: Stages 3–6 + Step 4

Stage 3 `179d5496`; Stage 4 `a6ce8277`; Stage 5 `6ac03208`; Stage 6 `ef7c29da`. Full suite: 1521 passed, 9 failed (all 9 pre-existing on baseline `3d764c7b`).

### Necessary/useful instructions
- "verification" guideline (run build/tests after changes; baseline-difference failures): the full `./v3 test` run surfaced two gaps the focused suites missed — the incremental write path not projecting viewer_tags (S6), and nothing else. Diffing against a pristine `git worktree` of the base commit cleanly separated my 6 regressions from 9 pre-existing environmental failures, preventing both false alarms and masked regressions. Highest-value instruction of the back half. Retain.
- "investigate before asserting" + reading the real seam: led to discovering `ViewerTagLookup` is static/PDO-free (forcing the five-site signature thread) and that `QdbBoardPolicy` lacks `routeServices` (PDO injected at the Application build site instead). Retain.
- Loop-breaking / "diagnose root cause rather than patch": the query-count test failing (expected 2, got 1) was not patched away — diagnosed to `spl_object_id` reuse across freed PDOs and fixed with a WeakMap connection id. A real identity-leak/stale-result hazard caught before merge. Retain.
- "default to action; surface genuine blockers": the exclusions question (S2) and the out-of-band-file-write test (S6) were both surfaced as decisions rather than silently resolved; the user's "ignore the exclusions" directive resolved the first, and the second was a test relying on now-obsolete file-scan immediacy (fixed by rebuilding after the direct write, matching how real writes behave).

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- UI/navigation-icon and UI-copy guidance: no user-facing copy or nav changes; server-rendered viewer-tag markup unchanged (asserted byte-identical).

### Uncertain assessments
- Retrospective whole-cycle ≈1.2 vs 1.15 projection: the two unforeseen gaps (dropSchema coupling, incremental-path projection) were absorbed in-stage; whether they "should" have pushed a split is debatable, but each was small and the branch stayed coherent. Single exposed, non-blind rater.
- The WeakMap memo assumes the per-request-process model; under a hypothetical long-lived worker reusing one PDO across requests, the memo would persist across requests. `resetMemo()` exists for that case but is not wired to a request boundary (none exists in this runtime). Noted, not changed.

### Ledger / study bookkeeping
- Cycle `v3/viewer_tag_read_model_and_memoization`, scope v1 combined, P2 standing enrollment. Checkpoints: Step 1 (merged predecessors) 1.15; Step 2 1.15; Step 3 1.15 + stage-work S1 0.08 / S2 0.30 / S3 0.35 / S4 0.15 / S5 0.15 / S6 0.10; Step 4 retrospective whole-cycle ≈1.2 with retrospective stage-work S1 0.08 / S2 0.32 / S3 0.35 / S4 0.18 / S5 0.12 / S6 0.22. Predecessor snapshots preserved (B 0.9, A 0.35) and not re-rated. Single exposed, non-blind rater; no uploads; collection does not approve implementation.

## 2026-10-25 UTC — Process-instruction gap retrospective (post-merge)

User asked what was missing from the process instructions such that findings
were not fully documented (externalized) initially. The findings *were* recorded
in-cycle; the gap was the absence of a step that promotes cycle-local findings
to a durable, shared home before completion. Analysis:

1. **No "externalize cross-cutting findings" step.** Railspike Steps 1–4 are
   structured around delivering one feature; Step 4 captures findings but inside
   the feature folder, which moves to `docs/plans/archive/` at completion.
   Nothing required promoting an outlives-the-cycle finding to the backlog, so
   the default gravity sank findings into the archive with the cycle.
2. **"Findings" not distinguished by lifetime.** Three kinds get lumped
   together: (a) resolved-in-cycle (WeakMap fix), (b) decisions needing a record
   (exclusion / identity-notation calls), (c) deferred work for someone later
   (dropSchema derivation; score/vote exclusion removal). Only (c) must leave the
   folder. No trigger named that distinction.
3. **AGENTS.md todo rule points the wrong way.** "Record follow-up work in the
   relevant planning or implementation documents" steered toward the feature
   folder — exactly where follow-ups get archived — with no carve-out for
   cross-cutting items. A real tension in the written guidance.
4. **No bidirectional-link convention.** The promotion pattern (backlog note +
   README index entry + cross-link from Step 4) had to be invented; a different
   run might record the same finding with no link or in only one place.
5. **"Document findings" has no acceptance criterion.** No completion gate makes
   "findings documented AND discoverable from outside the cycle" required, so
   "done" could mean "written in the debug log" — technically true, practically
   lost.

**Proposed smallest fix (covers all five):** add one line to the Step 4 /
completion definition — "Any finding not resolved in this cycle is recorded in
`docs/plans/backlog/` with a link back to this cycle's Step 4, before the cycle
is considered complete." Turns an ask-dependent cleanup into a release gate and
resolves the AGENTS.md tension by naming the exception.

**Self-critique (not a process gap):** I could have proposed the backlog
promotion proactively at Step 4 rather than waiting to be asked — the
instructions did not forbid it. Part of this was a process gap; part was treating
"documented in the cycle" as sufficient. The process change is the more reliable
remedy of the two.
