> **Feature plan:** [Step 1](./import_archive_hardening_step1_solution_assessment.md) · [Step 2](./import_archive_hardening_step2_feature_description.md) · [Step 3](./import_archive_hardening_step3_development_plan.md) · [Step 4](./import_archive_hardening_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/import_archive_hardening; standing P2 enrollment; [host ledger](../effort_estimation_trial.md#cycle-v3import_archive_hardening).

Extended estimates: enabled

**Cycle estimate: 0.90**

## Problem

The [adversarial review](../import_archive_adversarial_review.txt) demonstrated failures below archive limits; processing and reporting need resource boundaries as well as early admission checks. Step 1 Option A is approved.

## User stories

- As an operator, I want hostile archives rejected predictably so importing cannot monopolize resources or damage local content.
- As an operator, I want valid content and signed bytes preserved, with precise retry/resume instructions, so partial imports and interruptions remain recoverable.

## Core requirements

- **Admission:** retain 256 MiB compressed, 1 GiB expanded and 100,000 archive-entry limits; require valid UTF-8 filenames throughout the archive without renaming/normalizing signed paths. Reject unsafe containers before extraction. Enforce 16 MiB per canonical text record, 1 MiB per detached signature/public-key file, and the existing 4 KiB legacy-timestamp limit before every direct/indirect read, including fallback keys. Other metadata retains its existing stricter limits. Source record-limit failures reject the record and affected companions/dependents; destination-limit failures identify the local path and stop safely.
- **Processing:** text validation must preserve existing character rules and process a valid 16 MiB record within a 128 MiB PHP test process. Dependency rejection must scale with records and dependency links, preserving valid bootstrap cycles, conflict decisions and root-cause reporting. This is not a 128 MiB promise for a whole archive/read model.
- **Budgets:** default 300 seconds per invocation across download, extraction, planning, writes and publication, including lock waits; preserve stricter existing phase deadlines. Signature verification gets at most 10 seconds per verification and 1 MiB captured child output in aggregate. Expiry must terminate/reap owned children and release locks within a further 5-second cleanup allowance on supported Linux/WSL. Any operator-selected time override must remain explicit, finite and reported; no silent unlimited fallback.
- **Results and recovery:** preserve exit meanings: 0 complete, 2 partial review, 1 operation failure. Report stage, path where applicable, violated limit and next action. Before canonical writes, failure leaves canonical content unchanged; after writes begin, interruption retains accurate resumable state without overwriting local edits or duplicating commits. Verification timeouts/output exhaustion are operation failures, not assertions of an invalid signature. Never hide storage/programming failures as skipped content.
- **Compatibility:** preserve signatures, trust/authority exclusions, legacy-date integrity, traversal/link/checksum defenses and publication access controls. Exercise F1–F4 and C1 through normal CLI and shared consumers; no new dependency, schema, UI, trust model or OS sandbox.

## Delivery scope and completion boundary

Work type: application change. Extend the existing archive validator, readers, planner and verifier with shared limits; include regression tests, CLI help/runbook, review evidence, plans and study ledger. Completion requires preview/import/repeat/resume, successful publication for accepted content, bounded failure cleanup and shared-consumer regression evidence. Do not claim universal malicious-input protection.

Known environment: the user's WSL checkout runs `./v3 import-instance zenmemes.com`. Verify that flow against an isolated initialized destination on WSL/Linux; inspect served read-model/offline results. After failure, the operator receives either retry guidance for a corrected/smaller source or `--resume` with matching destination options. Repeating a successful import adds no commit; a bounded resume completes without re-downloading. Report any physical Lenovo/production verification not performed.

## Risks and shared components

- Stricter limits may reject legitimate historical keys/content. Before dependent implementation, inventory representative archives and boundary fixtures; expose the affected path/limit and retain bytes rather than silently truncating them.
- Timeout termination near commit/promotion may leave inconsistent state. Validate interruption points early; durable recovery and child cleanup are release gates. Budget enforcement feasibility on Linux/WSL must be established before dependent stages.
- Shared parser/verifier changes may alter authentication or writes. Extend canonical components, retaining trust semantics; verify their existing callers before release.

Shared inventory: import preview/import/resume and signature-audit CLI; canonical readers used by startup/rebuild, HTTP reads and offline publication; GPG verification used by authentication API, signed post/reaction/retraction/profile/settings writes and Railspike approvals. Reuse these components and existing CLI reports; no new UI or duplicate format parser. Confirm the complete caller inventory in Step 3.

## User flow and success criteria

1. Operator previews or imports through the existing command; safe inputs proceed within declared limits.
2. Unsafe archives fail early; malformed individual records yield accurate partial reports; budget failures stop with recovery guidance.
3. Operator corrects the source or resumes the saved operation, then verifies published content and duplicate-only repeat behavior.

Acceptance: each review reproducer has a permanent regression; exact/over-limit and indirect reads are covered; controlled slow/noisy children stop within budget and leave no owned child/lock behind. Invalid names cannot crash report persistence. Long chains demonstrate bounded work, not just a fragile timing assertion. Mixed valid/rejected content, signature bytes, conflicts, dependency causes and interrupted publication remain correct. Representative valid imports must fit defaults; unresolved compatibility/budget evidence blocks release.

## Approval and study checkpoint

2026-10-27: Step 1 explicitly approved; scope v1 clarified / R1 / P2 / baseline `00c84badd8832ed9e81fa0980c670283ae9fa318` retained. **0.90 total reaffirmed**, including shared-consumer and budget/recovery verification. Codex single exposed rater; exact frozen R1 hashes reverified; original intake/Step 1 and alternative estimates preserved. No implementation, measured labor or accepted-count increment; documents uncommitted. Await **Approved Step 2** before development planning.

*— Codex · 2026-10-27 UTC*
