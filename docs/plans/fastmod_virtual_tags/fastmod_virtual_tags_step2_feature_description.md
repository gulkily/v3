> **Feature plan:** [Step 1](./fastmod_virtual_tags_step1_solution_assessment.md) · [Step 2](./fastmod_virtual_tags_step2_feature_description.md) · [Step 3](./fastmod_virtual_tags_step3_development_plan.md) · [Step 4](./fastmod_virtual_tags_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/fastmod_virtual_tags; [standing P2 ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
**Cycle estimate: 0.60**
Status: Steps 1–2 approved; Step 3 proposed.

## Problem

Automatic meta labels currently require a durable classification lifecycle in addition to stored Fastmod scores. Derive effective tags from those scores through one reusable layer, so later rubric-to-tag mappings do not each need another lifecycle.

## User stories

- As a reader, I want qualifying posts to behave like tagged posts across reading and discovery so that I can find related content.
- As an operator, I want tags to reflect retained scores and current policy so that enabling or adjusting tagging needs no tag-writing job.
- As a contributor, I want future mappings to share the same behavior so that each new tag needs only its mapping and relevant verification.

## Core requirements

- Combine human tags with derived tags without duplication or changes to canonical records. First mapping remains root-only meta at the inclusive existing threshold (default 0.90); ordinary and historical/backfill scores may qualify. Replies gain no tags and do not tag their roots. Existing visibility and access rules apply.
- Use the latest successful result matching current post content and the current scoring rubric revision; a missing mapped rubric supplies no tag. Reads never call the provider. Labels track threshold, valid rescoring and pruning; the existing shared switch controls derived labels, including their disappearance when disabled. Scoring dependencies and private-score isolation remain intact.
- Reuse one shared mapping/effective-tag contract across display, filtering, counts and pagination. Ship only meta; no mapping editor, new rubric, new per-tag flag or speculative rule engine. Additional approved mappings must not require separate classification persistence.
- Stop creating new classification receipts and application/retry/rollback state. Proposed transition: existing active machine tags cease being authoritative; preserve existing suppressions as read-only legacy exclusions so prior corrections do not silently reappear. Preserve legacy data for recovery, with no new per-post exception feature. Retired CLI actions must explain the changed behavior and supported next action rather than silently succeed.
- Generated pages and offline exports capture effective labels at ordinary refresh/build time; downloaded copies remain snapshots. No immediate freshness guarantee or new generation-ledger replacement. Missing private scores leave human tags readable; refresh after recovery updates generated labels.

## Scope, completion and shared surfaces

Work type: Application change, focused tests and operational/reference documentation, plus these plans, study ledger and applicable UI audit notes. No dependencies, deployment or live activation. Normal flow: existing Fastmod scoring → enabled shared tagging policy → normal reading/tag discovery → ordinary generated/offline refresh. Completion requires this whole flow, legacy correction preservation, disable/re-enable, missing-score recovery and human-tag preservation.

Shared inventory: Reuse/extend RouteServices' effective-tag connection, ThreadRepository and TagGrouping; existing thread/root cards in board, thread and Forte views; Tags index/detail/counts and Forte tag groups; existing text/detail APIs and public query consumers that expose thread labels; static HTML builders and public offline snapshot/reader. Keep canonical source/backup output unchanged and private score data out of public queries/exports. Reuse Tools → Feature Flags and Tools → Fastmod; update existing CLI help/status and runbook, without adding UI. Step 3 must trace shared consumers to verify coverage before implementation.

## Risks and early validation

- Score selection or pruning can remove previously stable tags: validate retained-score cases before Step 3 and document this deliberate behavior; old active tags are not a fallback.
- Legacy suppressions could be lost during replacement: inspect correction semantics before Step 3; retain exclusions without continuing the old writer lifecycle.
- Query cost and generated staleness can undermine consistency: trace bulk tag discovery and ordinary refresh paths before Step 3; avoid per-post lookups and preserve correct filtering before pagination. Refresh is the recovery path, not a new immediate-publication guarantee.

## Flow and success criteria

1. Operator enables existing scoring/meta rubric and shared tagging switch; existing qualifying scores become eligible without another model call.
2. Reader sees the same effective labels in ordinary views, tag discovery and APIs; generated/offline views agree after refresh.
3. Score/policy changes alter derived labels; disabling removes derived labels on live reads and subsequent refreshes while human labels and prior exclusions survive.

Acceptance: Deterministic checks cover threshold boundaries, stale/missing scores, rescoring, pruning, historical roots, replies, duplicate human tags, legacy exclusions and disable/re-enable; a normal browser journey confirms discovery and snapshot refresh. Prove no canonical tag writes or additional provider calls. No live-model accuracy claim.

## Approval and study checkpoint

Step 2 handoff: 2026-10-29T18:35:07Z; v1/A, original baseline 60518d9e7ce80b34c93587d73a04d999b3b462b8; current checkout c5783108 after authorized origin merge; R1/P2. Whole-cycle 0.60 reaffirmed, including transition and verification; future extensibility clarifies the shared layer without adding mappings. Three frozen R1 hashes reverified. Codex single exposed rater; original missing pristine intake and Step 1 estimates preserved. No measured labor, implementation or accepted outcome. Proposed legacy-exclusion treatment is part of this Step 2 review. Await Approved Step 2 before development planning.

*— Codex · 2026-10-29 UTC*

Approval update: User explicitly approved Step 2. Requirements, including read-only legacy exclusions, are the development-plan boundary; no subsequent scope additions.

*— Codex · 2026-10-29 UTC*
