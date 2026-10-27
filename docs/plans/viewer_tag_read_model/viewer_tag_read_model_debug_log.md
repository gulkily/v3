# viewer_tag_read_model — Railspike debug log

## 2026-10-24 UTC — Step 1 (Solution Assessment)

First cycle of a B-then-A sequence, promoted from the predecessor memoization cycle's deferred Option B. Extended estimates enabled from Step 1.

### Necessary/useful instructions
- Follow-up / distinct-cycle-identity rules (`RAILSPIKE.md#follow-up-cycles-before-merge`): drove giving this a distinct cycle ID and prefix rather than reusing the predecessor's, with an explicit predecessor link and sequence note. Retain.
- Extended-estimate "every option gets a complete scoped-cycle estimate … do not sum alternatives": produced the per-option lines (A ≈0.9, B ≈0.8, C ≈0.35) while keeping the recommended-cycle ≈0.9 independent. Retain.
- "avoid database schema changes when possible" guardrail vs. this being inherently a schema change: the tension is resolved exactly as in `read_model_hot_indexes` — the change is justified and scoped under the full checklist, not avoided. Useful framing; retain.
- "values above 1.0 prompt a split discussion": prompted the explicit note to raise a split if Step 2/3 inspection pushes ≈0.9 past 1.0. Retain.

### Redundant/unnecessary instructions
- No supported findings.

### Inapplicable instructions
- Per-stage stage-work bullets: begin at Step 3; noted, not applied here.
- Documentation-only file-scope checks: application change.

### Uncertain assessments
- ≈0.9 is a single exposed, non-blind rater judgment anchored to the completed `read_model_hot_indexes` cost (≈0.30) plus projection/rewiring; the projection correctness and rebuild cost are the main uncertainties.
- Whether the memo successor should instead be folded in here (combined cycle) was considered; kept separate per the user's B-then-A choice and the ~1.0 cycle-size guardrail.
