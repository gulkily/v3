> **Feature plan:** [Step 1](./fastmod_score_transfer_step1_solution_assessment.md) · [Step 2](./fastmod_score_transfer_step2_feature_description.md) · Step 3 · Step 4

Debug mode: enabled
Effort study: railspike-effort / v3/fastmod_score_transfer; [standing P2 ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
**Cycle estimate: 0.70**
Status: Step 1 Option A approved; Step 2 proposed.

## Problem

Content export/import omits Fastmod scores, requiring paid rescoring before compatible derived tags can reappear. Transfer completed scores alongside accepted content without moving private workflow state or replacing destination assessments.

## User stories

- As a source operator, I want exported content to include scores automatically without extra configuration.
- As a destination operator, I want to import compatible scores with posts so that derived tags work without another provider call.
- As an operator, I want previews and safe retry so that existing content and local scores survive an interrupted transfer.

## Core requirements

- Normal content TAR and ZIP exports automatically include scores; ordinary `import-instance` preview/import processes them when present. No inclusion parameter, configuration setting or environment option. Document that scores are visible to archive recipients. No new page or per-rubric switches.
- Supplement uses versioned Key: value plain text, containing only successful named probabilities, post/content/rubric identifiers and original creation/update timestamps for posts present in the export. Exclude jobs, failures, prompts, provider exchanges, secrets, policy, legacy decisions and suppressions; never expose the private SQLite database. Scores are advisory imported results, not signatures or approval evidence.
- Import only scores matching accepted destination post bytes and the destination's current rubric revision, with finite probabilities in 0–1 and valid timestamps. Keep original revisions/timestamps; do not refresh retention ages. Existing destination rows for the same post/content/rubric key win. Report duplicate/conflicting, incompatible and rejected entries; malformed or unknown-version supplements import no scores and do not invalidate otherwise valid content. Never fabricate compatibility or enqueue scoring work.
- Score-only changes affect new archive requests despite unchanged Git content. Use a consistent bounded score snapshot; old archives without a supplement remain valid. Image archives, canonical signed bytes, the raw read-model download and private backup policies remain unchanged.
- Dry-run reports score eligibility without writes. Successful content publication plus score transfer yields matching effective tags in live and freshly generated views; no model call. An interrupted/failed score merge reports incomplete work and a usable retry/resume action, preserves local results, and never reports full success prematurely. Repeats do not duplicate/reset results; destination tagging policy and legacy exclusions remain authoritative.

## Delivery scope and completion boundary

Work type: Application change with format specification, runbook/reference updates, tests, this cycle's planning/study records and touched concept inventory entries. No dependencies, new authentication protocol, extra archive format support, real-instance conversion, deployment or live activation. Only import-instance's currently supported content archive path gains score import; exporting ZIP does not add ZIP import support. Existing source download/access rules still apply.

Completion: Normal content download → destination preview/import → compatible scores and tags → interruption/retry with preserved destination state. Round-trip, score-only cache changes, old archives, invalid supplements, conflicts, retention timestamps and recovery must pass before local closeout. Source suppression decisions are deliberately excluded, so imported scores may produce a tag that the source had suppressed.

## Shared components and concepts touched

Reuse ImageBackup/ArchiveCache/ExportIdentity and Backup's existing TAR/ZIP links; extend RepositoryArchive's bounded allowlist, import-instance/ContentImportRunner recovery and SqliteFastScoreStore merge behavior. Reuse CLI help/preview/status and Fastmod's existing score display. Derived-tag cards, Tags/Forte, text/detail APIs and static/offline builders consume imported scores unchanged; no separate rendering path. Keep approval acceptance and canonical record validation unchanged.

Concepts touched: Fastmod scoring (changed: imported advisory results), content snapshot export (changed: automatic score supplement/freshness), instance content import (changed: validated optional score merge/recovery), effective tags (used unchanged). Relevant inventory entries are missing; proposed additions under docs/concepts will describe delivered behavior and reference these sources during implementation, not during planning. Reuse existing entries if another cycle adds them first; no inventory survey or old-plan backfill.

## Risks and early validation

- Disclosure/false source assessments: selected scores become visible to archive recipients and can affect derived tags. Validate the field allowlist before export implementation; never describe imported values as locally computed or independently verified.
- Cache staleness or score/content mismatch: trace snapshot identity and content-hash calculation before Step 3; test score-only changes and retain exact compatibility checks.
- Partial content/score completion: trace existing saved-source/resume lifetime before Step 3; use bounded validation and repeat-safe destination-wins merging, with explicit incomplete-state reporting.

## Simple flow and success criteria

1. Normal content downloads automatically include only the allowed score fields.
2. Destination previews and imports normally; ordinary content acceptance rules run first, then compatible missing scores are merged.
3. Operator sees imported/skipped counts and matching tags without provider calls; a failed merge can be retried without replacing local scores or extending retention.

Success: Both export formats carry the same permitted supplement; normal import handles it and old archives; bad data causes no score writes; two identical imports preserve rows/timestamps; interruption recovery and generated-tag visibility pass. No score job, secret/exchange export or canonical byte change. Real-model accuracy and production migration are not claimed.

## Approval and study checkpoint

Step 2 handoff: 2026-10-29 UTC; v1/A, original baseline 53f0c89260c08e3fdf1d5535380d84d7cec92768; current checkout f6f295f9 after authorized origin/main merge (9005e876). Whole-cycle 0.70 reaffirmed, including explicit opt-ins, cache/recovery integration and new Railspike concept documentation. R1/P2, exact three hashes verified; Codex single exposed rater; earlier estimates/missing pristine intake preserved. No implementation, measured labor or accepted outcome. Predecessor remains a separate completed/unmerged cycle; branch reuse not yet authorized. Await Approved Step 2 before development planning.

*— Codex · 2026-10-29 UTC*

## Automatic inclusion revision — 2026-10-29

Request: Include scores without a parameter.
Disposition: User-directed scope v2/A supersedes source and destination opt-ins with automatic inclusion. All allowlist, compatibility, destination-wins and recovery constraints remain. Prior checkpoint descriptions of opt-ins are historical. No implementation authorization inferred; Step 3 approval remains pending.
Study: Existing cycle estimate reaffirmed; removing option plumbing leaves the dominant snapshot/recovery work. Original estimates and R1/P2 baseline retained, single exposed Codex rater; inherited reference verification, no measured labor or accepted outcome.

*— Revised by Codex · 2026-10-29 UTC*
