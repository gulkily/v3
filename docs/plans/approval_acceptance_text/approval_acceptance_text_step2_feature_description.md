> **Feature plan:** [Step 1](approval_acceptance_text_step1_solution_assessment.md) · [Step 2](approval_acceptance_text_step2_feature_description.md) · [Step 3](approval_acceptance_text_step3_development_plan.md) · Step 4 pending

Debug mode: enabled
Effort study: railspike-effort / v3/approval_acceptance_text — [ledger](../effort_estimation_trial.md)
Extended estimates: enabled

**Cycle estimate: 0.70**

## Problem and user stories

Approval acceptance evidence is stored as nested JSON. Making it readable must preserve existing approvals and signed review bindings, while allowing legacy file support to be retired later.

- As a member, I want readable acceptance evidence so that I can inspect what was approved and distinguish signed authorization from local acceptance.
- As an operator, I want safe conversion and restore so that existing approval evidence survives format changes.
- As a maintainer, I want an isolated legacy reader and a readiness check so that later removal is bounded and verifiable.

## Core requirements

- New receipts use named `Key: value` text fields, with a documented version, readable examples and strict malformed-input behavior. Preserve all acceptance facts, grant hashes, signed bytes, review digests, authorization checks and invitation acceptance-time semantics. Missing legacy facts remain unknown; invalid evidence never becomes missing evidence.
- Provide explicit, validated conversion of existing receipts, preserving context ordering/types needed for historical digest verification. Preview changes, reject inconsistent or conflicting evidence, and permit safe retry after interruption. A failed conversion retains recoverable originals; completed conversion leaves equivalent authoritative evidence. Identical duplicates may be reconciled only after validation; contradictory receipts block conversion/use rather than selecting one silently.
- Retain legacy repository/backup reading behind a single removable compatibility boundary. Current text reading, shared validation and historical signed-digest verification remain independent of it. New writes use text only; no automatic legacy rewrite during ordinary reads.
- Provide readable inspection/conversion results and a read-only retirement-readiness check identifying remaining legacy receipts and blockers. Document the bounded removal changes and prove text-only operation with the legacy reader unavailable. Report unexamined archives as unknown; no live-tree scan can certify all backups. Actual retirement requires a separate backup/restore decision and is outside this cycle.
- Preserve normal approval and invitation flows, history/source access controls, full/incremental rebuild, trusted repository restore, remote content-import exclusion, and commit/retry behavior. Distinguish failure before commit from acceptance committed but refresh failed; retry must not duplicate or discard accepted grants.

## Delivery scope and shared components

Work type: Application change. Concepts touched: Approval acceptance (representation changed), signed review and invitation replay (behavior preserved); add concise proposed inventory entries during implementation. Extend the existing acceptance codec/validation and writer rather than introducing a second authority model.

Reuse shared approval-history data and partials across classic profiles/accounts, Forte profiles/accounts and Tools user-access views; retain their receipt source links. Reuse activity/security review, the text API's approval evidence/receipt fields, approval audit output, and existing source/download authorization. Extend source-path/archive recognition and shared full/incremental replay. Add operator inspection/conversion/readiness entry points because no current receipt-format migration control exists; preserve explicit machine output where applicable. No UI redesign or new dependencies.

Documentation: This cycle's plans/debug log, study ledger, format audit, receipt specification, affected CLI/runbook references and touched concept inventory. No unrelated format conversion, policy change, production conversion/deletion, actual legacy retirement, deployment or history rewrite.

## Flow and completion boundary

1. A member completes an ordinary approval or invitation redemption; history opens its readable receipt with the original trust boundaries.
2. An operator previews existing-receipt conversion, resolves reported blockers, converts explicitly and verifies matching history/rebuild results; interruption permits safe retry without losing original evidence.
3. A maintainer runs the readiness check and follows documented retirement prerequisites, including a separate decision about older archives.

Complete when direct/invitation actions, source inspection, conversion/retry, rebuild and trusted restore pass through their normal entry points; conflicting/malformed evidence fails explicitly, signed grant bytes remain identical, and converted/new text receipts work without the legacy reader. Verify affected rendered history/source flows under CONTRIBUTING guidance. Release handoff includes evidence and limits; deployment and actual reader removal remain separate.

## Risks and early validation

- Digest mismatch could invalidate signed review: before conversion work, prove historical context order/type reconstruction against signed fixtures; preserve unsupported originals and block conversion with an actionable reason.
- Interrupted or duplicate conversion could lose evidence: establish conflict and commit/retry invariants before mutation work; verify failure/retry cases on disposable repositories.
- Reader removal could strand old archives: test restored legacy evidence and text-only operation independently; document archive uncertainty and keep actual retirement outside scope.

## Approval and study checkpoint

Approved Step 1 received for revised Option A, including “an easy way to later remove the legacy reader.” Incorporated in requirements above; no deferred enhancement. Scope v2/A clarified; baseline d26a90f9b9bfa8eb67e72804a7c24707947ab7c0; R1/P2 standing enrollment; Codex single exposed rater. Step 2 handoff, 2026-10-29 UTC: whole-cycle **0.70 reaffirmed** (original v1 0.65 retained in Step 1). Frozen R1 hashes reverified; additional source-link/API/shared-template inspection. Pristine intake remains missing; no measured effort, implementation, dependency delta or accepted outcome. Planning remains uncommitted; await Approved Step 2.

*— Codex · 2026-10-29 UTC*

Approval update: Approved Step 2 received; [Step 3](approval_acceptance_text_step3_development_plan.md) records the development plan. Earlier pending-approval snapshot is historical.
