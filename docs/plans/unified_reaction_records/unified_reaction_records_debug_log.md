# unified_reaction_records Railspike debug log

## 2026-10-30T01:40:00Z — Step 1

### Necessary/useful instructions

- `RAILSPIKE.md` Experimental Effort Estimation, intake checkpoint ("Preserve initial scoped estimate ... before detailed implementation inspection"): prompted recording intake 0.90 before reading source; the later 0.75 differs for a stated reason. Retain.
- `step1_solution_assessment.md` Original Query rule: the pasted todo item was preserved verbatim in a code block. Retain.
- `docs/concepts.md` Planning: required naming a proposed concept rather than linking a nonexistent file. Retain.

### Redundant/unnecessary instructions

- `RAILSPIKE.md` Agent attribution: per-option signatures on a single-pass Step 1 by one agent add three identical lines; Step 3 already permits one footer for single-pass plans. Suggest extending the single-footer allowance to Step 1.

### Inapplicable instructions

- Follow-up Cycles Before Merge: predecessor is merged; no branch reuse.

### Uncertain assessments

- Whether a ledger index entry at Step 1 is required or only permitted under P2 ("append a ledger index row"); followed prior cycles and added it uncommitted.

## 2026-10-30T01:58:00Z — Step 2

### Necessary/useful instructions

- `step2_feature_description.md` Shared component inventory: forced a reader inventory, which surfaced the raw-header readers that bypass the parsers; now an explicit risk. Retain.
- `step2_feature_description.md` Guardrails "avoid implementation details": kept exact header names out of Step 2 and deferred them to Step 3. Retain.

### Redundant/unnecessary instructions

- No supported findings.

### Inapplicable instructions

- Known user or environment: the request names none.
- Documentation-only scope fields: this is an application change.

### Uncertain assessments

- Whether "older code rejects new records" is acceptable under "don't break what already works"; stated as a documented limitation for the user to confirm.

## 2026-10-30 UTC — Step 3

### Necessary/useful instructions

- `step3_development_plan_do.md` "Resolve each material risk before dependent work begins": led to ordering readers (Stage 2) before writers (Stage 3) so every commit leaves new records readable. Retain.
- `step2`/Step 3 early-validation requirement: inspecting the signed-input check surfaced the cached-client layout issue before planning. Retain.

### Redundant/unnecessary instructions

- `step3_development_plan_after.md` effort section repeats the presentation checks already in `step3_development_plan_do.md` and `effort-estimation.md#estimate-presentation`. Suggest consolidating into one reference.

### Inapplicable instructions

- Known user or environment replay stage: Step 2 names none.

### Uncertain assessments

- "Stages ≤ 1 hour or ≤ 50 lines": Stage 4 (browser matrix) may exceed an hour of wall time though its diff is small.

## 2026-10-30 UTC — Step 4

### Necessary/useful instructions

- `RAILSPIKE.md` Enhancement Requests: the mid-Stage-2 deprecation request was recorded before acting and classified only after the user chose an end state; this kept a principle conflict (removing support for signed legacy records) out of the cycle. Retain.
- `step4_implementation_do.md` Stage Boundary Protocol: per-stage verification exposed the clock-skew layout break in Stage 3 before the browser change. Retain.
- `step4_implementation_after.md` item 7 (identify reproduced baseline failures): led to recording a baseline in a separate worktree and rerunning the one non-baseline failure on both trees. Retain.

### Redundant/unnecessary instructions

- `step4_implementation_before.md` asks for the entry projection "only after the planning commit and creation of the summary, before implementation". When the summary is created alongside Stage 1, this ordering is easy to miss without adding value if the Step 3 value is simply reaffirmed. Suggest allowing reaffirmation to be recorded with the Stage 1 commit.

### Inapplicable instructions

- Follow-up Cycles Before Merge; one-shot transition; documentation-only checks; known-user replay.

### Uncertain assessments

- Whether a full-suite baseline should be required at Step 4 entry. It was essential here (intermittent failures in both runs), but Railspike doesn't state it.
