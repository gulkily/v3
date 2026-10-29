> **Feature plan:** Step 1 · [Step 2](unified_removal_format_step2_feature_description.md) · [Step 3](unified_removal_format_step3_development_plan.md) · Step 4 (pending)

Debug mode: enabled (project marker).
Effort study: `railspike-effort` / `v3/unified_removal_format`; [ledger](../effort_estimation_trial.md#cycle-v3unified_removal_format), standing P2 enrollment.
Extended estimates: enabled.

**Cycle estimate: 0.70**

## Original Query

Is there a way we can merge them into the same format while we make this change?

## Understood Intent

Combine F02 source/label removal and F01 retraction acceptance into a readable format while converting F02. This requests assessment, not implementation. Scope v2/A: the maintainer confirms there is no existing data to save. Abandon both previous F01 text receipts and F02 JSON records: no migration, compatibility readers, historical replay or old-state recovery. Preserve exact signed evidence for actions created under the new format.

## Problem

Two retained acceptance families describe overlapping post-removal facts, with different authorization and label-resolution requirements.

## Option A — Shared receipt format with explicit operation and authority

**Estimate: 0.70**

Use one versioned `Key: value` receipt specification and codec for future retractions, local operator deletions and label resolutions. Share post identity, conversation structure, acceptance time and content bindings; retain repeated label bindings. Explicit operation and authority fields select strict validation rules. Signed retractions retain their separate original request and detached signature; local operator actions do not fabricate signed evidence. Keep the current entry points, with a common interpretation of accepted receipts downstream.

- Benefits: removes duplicate format logic and gives source readers one vocabulary. Existing readers already combine `AcceptedRetraction::removals()` and `LabelRemoval::all()`.
- Obligations: label-only resolution must not delete or suppress a surviving post; anonymous source posts must remain representable. Imported receipts cannot confer local authority. Define same-post repair/deletion/retraction precedence without losing accepted label bindings or signed evidence.
- Compatibility: support only the new format; no F01/F02 legacy migration or reader. Specify paths, versions and interruption handling in subsequent planning. Do not physically delete old data as part of this assessment.
- Verification: author/operator retraction, CLI deletion/repair, commit failure/retry, source views, rebuild/import, retained signatures, independent replies/labels and ordinary cleanup. No new publication guards, diagnostics ledger or history purge.

## Option B — Separate formats with matching field conventions (historical alternative)

**Estimate: 0.45**

Convert only F02 JSON to text and align shared header names with F01, retaining separate codecs and readers. Reuse the established format conventions without changing the retraction receipt contract.

- Benefits: narrower change and less retraction regression work; preserves F01 as delivered.
- Cost: two schemas and acceptance representations remain; this does not fully satisfy the requested unification.

## Recommendation

Choose A as one staged, releasable cycle. Unify the receipt format and downstream interpretation while preserving the distinct authorization cases. Do not merge all commands, signing flows or recovery machinery merely to share a format. Estimate includes documentation/specification and new-format end-to-end verification; the chief uncertainty is same-post transitions and recovery integration.

## Study snapshots and approval

2026-10-29T00:03:48Z; baseline `b8afb7711cdf6816da943755268de848b6f33781`; scope v1/A; R1/P2; Codex single exposed rater. Intake 0.75 recorded after prior checklist/source review and this turn's receipt/consumer inspection; no pristine pre-inspection estimate exists. Step 1 handoff 0.75 reaffirmed. All three frozen R1 hashes verified against the ledger. Earlier conversational F02 screening 0.45 covered conversion alone and remains distinct; broader unification is additional scope. Scores include the full scoped cycle, not hours or an additive forecast. No implementation, tests, dependencies, measured work or accepted outcome. Related completed `v3/retraction_text_format` retains its own history. Await Approved Step 1 before Step 2.

*— Codex · 2026-10-29 UTC*

Approval update — 2026-10-29 UTC: user explicitly approved Step 1; proceed with Option A requirements. Original snapshot and alternatives retained.

*— Codex · 2026-10-29 UTC*

Scope revision — 2026-10-29 UTC: user said “Yes, let's abandon them, there's no existing data to save.” This explicitly removes F01 text compatibility as well as F02 JSON support. Scope v2/A estimate 0.70; original v1/A 0.75 and Step 3 0.85 remain historical. Option B 0.45 remains the unselected original alternative, not a new v2 proposal. Steps 1–2 approval plus this explicit narrowing authorize the revised plan; Step 3 implementation approval remains pending. No files containing old data are deleted by this documentation revision.

*— Codex · 2026-10-29 UTC*
