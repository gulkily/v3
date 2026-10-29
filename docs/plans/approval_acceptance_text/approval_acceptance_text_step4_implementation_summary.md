> **Feature plan:** [Step 1](approval_acceptance_text_step1_solution_assessment.md) · [Step 2](approval_acceptance_text_step2_feature_description.md) · [Step 3](approval_acceptance_text_step3_development_plan.md) · [Step 4](approval_acceptance_text_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/approval_acceptance_text — [ledger](../effort_estimation_trial.md)
Extended estimates: enabled

**Cycle estimate: 0.75**

## Execution entry

Approved Step 3 received. Branch: feature/approval-acceptance-text. Planning commit: aa9a7f7b. Scope v2/A; baseline d26a90f9b9bfa8eb67e72804a7c24707947ab7c0; R1/P2; Codex single exposed rater. 2026-10-29 UTC entry projection 0.75 reaffirmed before implementation. Original stage work S1–S6: 0.15/0.15/0.10/0.20/0.10/0.15, non-additive; S6 owns shared acceptance. Pristine intake missing; no measured labor. No production data mutation or deployment authorized. Dependency delta: zero planned.

*— Codex · 2026-10-29 UTC*

## Stage 1 - Readable contract and digest preservation

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.15**

- Changes: Ordered named context headers, strict scalar/header validation, isolated legacy decoder, shared semantic validation, readable spec/example and touched concepts. Writers remain legacy until S2.
- Verification: `php tests/run.php ApprovalAcceptanceTest`: 3/3 pass, including ordered/reversed context, Unicode/escaping, digest mismatch, malformed text and duplicate legacy fields. Whitespace checked.
- Notes: No stage estimate revision; signed digest generation unchanged. Runtime browser/restore acceptance belongs to later stages.

*— Codex · 2026-10-29 UTC*

## Stage 2 - New writes and receipt resolution

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.15**

- Changes: Text-only new writes; shared validation of all existing receipts, conflict rejection, actual source paths in history/recovery, text source allowlist. Invalid legacy companions cannot be hidden by valid text.
- Verification: ApprovalAcceptance/History/Policy/InvitationIssuance 21/21; two actual Write API approval/source and signed-finalization checks pass, including rejected commit cleanup and successful retry. Updated source expectation to named fields and cleanup check to both extensions.
- Notes: S1 commit cee88cbc. Archive recognition follows in S3; no schema/dependency changes. Estimates unchanged.

*— Codex · 2026-10-29 UTC*

## Stage 3 - Replay and archive compatibility

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes: Both extensions recognized; trusted restore preflights all source/destination acceptance evidence before copying, then deduplicates only equivalent facts. Grant path lookup handles flat/dated posts. Remote import authority exclusion remains intact.
- Verification: 25/25 focused receipt/archive/content-import/API/rebuild checks. Expanded invitation restore test covers text, mixed, legacy-only and conflicting archives through the actual import command. Signed account recovery and incremental/fresh equivalence pass.
- Notes: S2 commit 1aca4c67. No replay-policy changes or schema/dependency delta; estimates unchanged.

*— Codex · 2026-10-29 UTC*

## Stage 4 - Operator conversion and recovery

**Original estimate: 0.20**
**Retrospective stage-work assessment: 0.20**

- Changes: `approval receipts inspect|convert`, preview and explicit machine output; same database-directory write lock, clean-tree preflight, scoped Git commit, private durable original/new-byte journal, resumable refresh and intervening-edit protection. CLI/runbook/spec updated.
- Verification: Four maintenance tests pass, exercising real CLI preview/apply/repeat, failures at journal/text-write/commit/refresh boundaries, pre-commit hook rejection/retry, altered evidence preservation, dirty tree, conflict and lock contention. Actual help and PHP syntax checked.
- Notes: S3 commit cdb2043a. First retry test exposed Git add of an already-staged deletion; fixed by staging the receipt directory after the owned-path status check. No failure is hidden; tests rerun 4/4. Journal is machine-only recovery state. No production conversion; estimate unchanged.

*— Codex · 2026-10-29 UTC*

## Stage 5 - Reader retirement readiness

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes: Read-only readiness command, live-tree readiness and explicit archive uncertainty, pending-journal blockers; bounded removal procedure keeps digest validation separate and avoids a new runtime setting.
- Verification: Maintenance suite 5/5. Fresh PHP processes with legacy decoder autoload deliberately blocked pass new text encode, converted text read/rebuild and actual archive restore. Legacy input fails explicitly; orphan evidence and pending journal block readiness. Inspection creates no database.
- Notes: S4 commit 83b4dba9. Actual reader retirement and archive policy remain future decisions; estimates unchanged.

*— Codex · 2026-10-29 UTC*

## Stage 6 - Acceptance and closeout

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.20**

- Changes: Added actual-browser F03 acceptance, private text-source restriction check and edge regressions. Corrected legacy string tokenization for quoted key-like values, preserved equivalent existing text bytes, rejected symlink receipt directories, and moved temporary writes into private Git staging. Intent-to-add plus scoped commit prevents failed hooks leaving receipt contents staged for unrelated writes.
- Verification: Final focused PHP run 59/59 across receipt, maintenance, history, policy/audit, archive/import, invitation, signed API, full/incremental rebuild, refresh failure, private source restrictions and source allowlist. Additional directory-symlink/equivalent-text test 1/1. Maintenance failure tests prove pre-commit hook retry, no staged contents on failure, intervening-edit preservation, lock contention and journal/text-write/committed/refresh interruptions. Syntax, shell help, whitespace and local document links checked.
- Browser: `node tests/browser/approval_receipt_text.mjs` passes actual signed new/own/cross-account approvals, failed-commit retry, source navigation, signed-evidence conversion with identical Git trees for posts/signatures/keys, rebuild and readiness. Tools account/key history rendered at 390/1280px across all 13 themes supported by the fixture; standalone Forte account/key history also checked. Expanded evidence has no page overflow; phone Light/Word97 screenshots visually inspected. `node tests/browser/invite_zero_click.mjs` passes public and members-only invitation acceptance/recovery and disabled-rename cases.
- Test limitations/corrections: Existing `account_key_approval.mjs` stops at an obsolete confirmation-dialog cancellation expectation; product now intentionally submits without that dialog. It was not rewritten or counted as a pass. Dedicated F03 browser test uses the actual interface. Its initial fixture checks were corrected for collapsed links, unthemed standalone Forte and site-specific themes unavailable to this fixture; final run passes. Full PHP suite, physical devices, other branded-site themes and other browser engines were not run.
- Notes: S5 commit 4dd7969b. No product UI copy/layout changes; existing first-paint behavior retained. Zero dependency or read-model schema delta. F03 closed in audit; other conversions untouched. S6 retrospective increase reflects acceptance fixture work, not expanded product scope.

## Study closeout and release limits

Planning aa9a7f7b; S1 cee88cbc; S2 1aca4c67; S3 cdb2043a; S4 83b4dba9; S5 4dd7969b; S6 is the commit containing this closeout. Six of six stages complete locally. Scope v2/A, original baseline, R1/P2, single exposed Codex rater retained. Entry projection **0.75** unchanged; separate **retrospective scope assessment: 0.80**. Stage originals 0.15/0.15/0.10/0.20/0.10/0.15; retrospectives 0.15/0.15/0.10/0.20/0.10/0.20, non-additive. No measured labor or independent rating; pristine intake remains missing. Maintainer comfortable-fit/acceptance pending; no accepted-count increment.

No production data was converted or deleted. Legacy reader remains installed; readiness is not permission to abandon old archives. No merge, push, deployment, dependency change or history purge. All conversions and failure injection used disposable fixtures. Runtime logs/screenshots were captured under `/tmp/f03-*`; commands and outcomes above are the durable record. Ledger and debug log updated; no `todo.txt` edits.

*— Codex · 2026-10-29 UTC*

Document verification note: The first whole-ledger link scan encountered pre-existing historical missing targets. Rechecked changed documents against baseline: newly introduced links resolve; historical broken targets are left untouched. Local anchors/remote websites were not audited.
