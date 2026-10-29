> **Feature plan:** [Step 1](unified_removal_format_step1_solution_assessment.md) · Step 2 · [Step 3](unified_removal_format_step3_development_plan.md) · [Step 4](unified_removal_format_step4_implementation_summary.md)

Debug mode: enabled.
Effort study: `railspike-effort` / `v3/unified_removal_format`; [ledger](../effort_estimation_trial.md#cycle-v3unified_removal_format), standing P2 enrollment.
Extended estimates: enabled.

**Cycle estimate: 0.70**

## Problem

Retractions and source/label cleanup retain overlapping acceptance facts in different formats. A shared readable receipt must simplify that representation without conflating deletion, label resolution, or authorization.

## User stories

- As an author, I want a signed retraction to delete my post while retaining verifiable evidence and the surrounding conversation.
- As an operator, I want deletion and label repair to use the same readable receipt vocabulary without requiring a fabricated author signature.
- As a repository reader, I want to distinguish what happened, which authority accepted it, and which exact content and labels it covers.

## Core requirements

- **One future receipt format:** a documented, versioned `Key: value` contract with common post/conversation identity, acceptance time and content bindings; repeated readable label bindings; explicit operation and authority. Preserve anonymous-source representation. Reject malformed, contradictory or unsupported records instead of treating them as accepted actions.
- **Distinct authority:** signed author/operator retractions retain exact original request/signature bytes and their bindings; local operator deletion/repair retains local authority without claiming a signature. Receipt text or imported copies alone must never grant acceptance. Preserve existing protected-target restrictions and authorization checks.
- **Distinct effects:** deletion removes the source and the dependent files covered by that action, preserving independent replies/labels and conversation structure. Label-only resolution leaves the surviving source and its valid contributions active. Same-post repair/deletion/retraction and retries must preserve accepted bindings/evidence, never downgrade deletion to resolution or silently choose between conflicting evidence.
- **Compatibility:** only the new shared format is supported. Previous F01 text receipts and F02 JSON records, including old pending recovery state, need no migration, compatibility reader or recovery workflow: the maintainer confirms there is no existing data to save. Unsupported formats must not be accepted as new evidence. No automatic old-data deletion. Other record families and backup formats are unchanged.
- **Complete lifecycle:** normal writers, readers, source/export, rebuild/import, label aggregation and cleanup agree on the receipt's meaning. Interrupted writes, failed commits and failed derived refresh have a safe documented retry/recovery path; retries must not lose evidence, repeat completed effects, or grant new authority.

## Delivery scope and completion boundary

Work type: application change, delivered through existing retraction and operator deletion/repair entry points. Include the shared format specification, affected tests/operator references, checklist, cycle artifacts and study ledger. No new dependencies, command redesign, history purge, bulk data deletion, publication guards or diagnostics ledger. Internal recovery journals may remain machine formats.

Complete when ordinary signed retraction and CLI deletion/repair produce readable receipts, preserve allowed content, survive rebuild/import and expose actionable recovery after failure. New signed and local receipts must have deterministic same-post behavior; no old/new coexistence requirement. Deployment and real-repository cleanup are outside this cycle.

## Shared component inventory

Extend/reuse the existing retraction control and `post_retraction.js`, prepare/finalize API and `LocalWriteService`; CLI `delete-record` inspection/deletion/repair and `SourceLabelRemoval`; canonical receipt/record readers and label ownership; source current/history/download routes and archive catalog/export/import; read-model thread/reply placeholders, profile/listing counts, standard/Forte Activity and source metadata; static/offline projections and sweep maintenance. No new UI surface is needed. Shared receipt interpretation must replace competing acceptance interpretations, while entry-specific signing and recovery remain appropriate to each action.

## Simple user flow

1. Author/operator retracts through the existing signed flow, or operator invokes deletion/repair through the CLI.
2. Existing authority checks accept the action; a shared-format receipt retains its evidence and intended effects.
3. Existing views and exports reflect the accepted result. If commit or refresh fails, the user follows the reported retry/recovery action and verifies the resulting receipt and content state.

## Risks and early validation

- **Authority confusion:** examine signed and local cases plus untrusted imports before implementation planning; require explicit validation profiles.
- **Lost same-post evidence:** enumerate repair-to-delete, deletion/retraction coexistence and repeated-action cases before selecting record identity/precedence in Step 3.
- **Interrupted new actions:** validate new receipt/journal ordering and retry behavior; old-format replay and recovery are out of scope.
- **Shared-reader regressions:** map direct and derived consumers before stage allocation; include actual source, rebuild, import and browser/CLI outcomes in acceptance.

## Success criteria

All three action cases pass ordinary-flow and relevant failure/retry checks; new-format fixtures cover all action cases and unsupported old formats cannot confer authority; new receipts contain no JSON or deleted prose; exact signed bytes verify unchanged; tampered/untrusted receipts cannot authorize removals; label-only repair preserves source visibility; independent replies/labels survive deletion; source/export and rebuild/import agree. Affected browser flows receive the repository-required rendered verification. Record tests, limitations and dependency delta; no full-suite claim without execution.

## Approval and study checkpoint

Step 1 Option A approved by the user's “Approved Step 1.” Scope v1/A clarified, original baseline `b8afb7711cdf6816da943755268de848b6f33781`, R1/P2 retained. 2026-10-29T00:13:16Z: Step 2 total 0.75 reaffirmed; Codex single exposed rater, all three frozen R1 hashes reverified. Prior intake/Step 1 and conversion-only screening remain preserved. No implementation, measured effort or accepted outcome. Await Approved Step 2; no Step 3 work yet.

*— Codex · 2026-10-29 UTC*

Approval update — 2026-10-29 UTC: user explicitly approved Step 2. Step 3 planning proceeds within scope v1/A.

*— Codex · 2026-10-29 UTC*

Scope revision — 2026-10-29 UTC: explicit maintainer instruction removes all previous F01/F02 receipt accommodation because no existing data needs saving. Scope v2/A, revised whole-cycle estimate 0.70, original baseline/R1/P2 retained. This directly authorized narrowing supersedes the v1 compatibility requirements; prior 0.75 checkpoint and Step 2 approval remain historical. New-action signature preservation, safe retries and trust checks remain required. Step 3 approval is still pending.

*— Codex · 2026-10-29 UTC*
