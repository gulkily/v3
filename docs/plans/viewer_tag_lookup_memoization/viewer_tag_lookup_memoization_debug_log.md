# viewer_tag_lookup_memoization — Railspike debug log

## 2026-10-24 UTC — Step 1 (Solution Assessment)

### Necessary/useful instructions
- `step1_solution_assessment.md` option structure (standalone approach paragraph + pros/cons bullets): kept three genuinely distinct approaches (request memo / read-model migration / qdb-only) comparable at a glance and surfaced that Option A subsumes Option C. Retain.
- `RAILSPIKE.md` "vertical-slice viability" requirement in Recommendation: forced framing the win as an end-to-end logged-in-viewer outcome rather than a component tweak. Retain.
- Effort study R1 anchors (toolbar identity 0.35, composer decluttering 0.60): gave a defensible interpolated ≈0.45 for a multi-call-site correctness surface. Useful; retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Extended estimates: not enabled; no per-option estimate line required.
- Documentation-only scope rules: this cycle is application work, not doc-only.

### Uncertain assessments
- Whether one remaining per-request directory scan is acceptable on very large instances is unmeasured; this is why Option B (read-model migration) is retained as a profiling-gated follow-up rather than chosen now.
- ≈0.45 projected total is a single exposed, non-blind rater judgment; the correctness surface across board/thread/Forte/qdb is the main uncertainty driver.

## 2026-10-24 UTC — Step 2 (Feature Description)

### Necessary/useful instructions
- Shared component inventory requirement: forced tracing every call site, which surfaced that `ThreadAndPostPageController::viewerHasThreadTag()` globs `thread-labels` directly instead of going through `ViewerTagLookup` — a bypass that would otherwise leave a scan behind. Retain; the inventory discipline caught a real gap.
- "A feature is a normal-flow vertical slice, not a component/cache" guardrail: this cycle is literally a cache, so the guardrail pushed the framing to logged-in-viewer outcomes (identical state, fewer scans) rather than "add a memo." Retain — kept the completion boundary user-facing.
- Risks "state impact / early validation / mitigation" structure with `**High risk:**`: put the identity-leak/results-drift risk at the top where it belongs for a caching change. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Known-user/environment recovery: none named; recorded explicit omission.
- Documentation-only file-scope rules: application change.
- Extended-estimate per-option annotations: extended mode is off for this cycle.

### Uncertain assessments
- The "stale memo within a request" risk is low but depends on confirming these render paths never write reactions mid-request; flagged for Step 3 validation rather than asserted.
- ≈0.45 reaffirmed is a single exposed, non-blind rater judgment; the multi-call-site correctness surface remains the main uncertainty.

## 2026-10-24 UTC — Revision: extended estimates enabled (late activation)

User requested enabling extended estimates for this cycle and updating Step 1 with them. Recorded the mode in Step 1 and Step 2 headers; added current-dated retrospective per-option estimates to Step 1 (A ≈0.45, B ≈0.9, C ≈0.25). Does not advance any step.

### Necessary/useful instructions
- Late-activation rule ("records current dated estimates with exposure disclosed and earlier originals missing, not reconstructed") + "do not backfill missing historical estimates": prevented presenting the new per-option numbers as original Step 1 judgments; they are explicitly labeled current-dated retrospective. Retain — this is the exact case the rule governs.
- "do not sum/normalize alternatives into fractions" + "preserve the recommended-cycle snapshot separately": kept the ≈0.45 recommended-cycle total distinct from the three option estimates. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Per-stage stage-work bullets: not applicable until Step 3; noted that they begin there.

### Uncertain assessments
- Option B's ≈0.9 is the least certain (schema change + builder projection + rebuild cost); it is an order-of-magnitude judgment to justify keeping B as a follow-up, not a committed scope.
- All option estimates are single exposed, non-blind rater judgments against R1; annotations, not measured effort.

## 2026-10-24 UTC — Revision: B-then-A sequencing (scope v2)

User reordered Path 1 to B-then-A. Appended a scope-v2 revision to this cycle's Step 1: it becomes the second cycle, wrapping the SQL path from the new `viewer_tag_read_model` (B) cycle instead of memoizing file scans. Does not advance any step.

### Necessary/useful instructions
- Plan-review / revision rules ("retain the Original Query; record the revised direction separately"; "a revision does not approve the step"): drove preserving v1 intact and recording v2 as an appended revision, and explicitly noting the prior `Approved Step 1` no longer carries after a scope change. Retain — prevents silently inheriting a stale approval.
- Effort "append revisions, retain original scope/scores" rule: kept v1 ≈0.45 as history while recording v2 ≈0.35 with the reason (memoizing indexed SQL is simpler than memoizing glob+parse). Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Combined-cycle framing: user chose two cycles; the "both together" option was discussed and declined, so no combined artifact is written.

### Uncertain assessments
- v2 ≈0.35 assumes B lands first and exposes a clean SQL seam; if B's seam differs, this estimate and the Step 2 rewrite would shift. Single exposed, non-blind rater.
