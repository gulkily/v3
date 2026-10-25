> **Feature plan:** [Step 1](retraction_text_format_step1_solution_assessment.md) · Step 2 · [Step 3](retraction_text_format_step3_development_plan.md) · Step 4 pending

Debug mode: enabled.  
Effort study: `railspike-effort` / `v3/retraction_text_format`; [ledger](../effort_estimation_trial.md#cycle-v3retraction_text_format). Standing P2 enrollment.  
Extended estimates: enabled. Approval: Steps 1–2 approved; Step 3 drafted.

**Cycle estimate: 0.60**

## Problem and user stories

Retraction records hide readable signed evidence inside JSON. The selected format must match the platform's text conventions while preserving deletion authority and recovery.

- As an author or operator, I want to retract through the existing action so that the post is removed and independent replies survive.
- As a reader or maintainer, I want readable source records so that I can distinguish the request, its signature, and the site's acceptance without decoding JSON.

## Core requirements

- New retractions retain three files: a `Key: value` signed request, its detached armored signature, and a separate `Key: value` acceptance receipt. Keep signed bytes intact; bind the receipt to the exact request/signature and target. No embedded JSON or escaped multiline evidence.
- Commit the three files together with removal of the original post/signature. Retain actor, time, author/operator classification, display attribution, source hash and necessary conversation structure; retain no deleted prose or cleanup-progress ledger.
- Preserve authorization, signature checks and local-acceptance trust. An imported request or receipt alone cannot authorize deletion; existing accepted deletions prevent archive resurrection.
- New-record retry, interruption and rejected-commit recovery must leave a consistent accepted deletion or restore the original state. A committed deletion remains accepted if derived refresh/cleanup fails; existing retry/rebuild actions remain usable without the original post.
- Previous retractions need no migration or backward compatibility, as explicitly requested. Document this boundary; do not restore deleted prose or rewrite historical repositories as part of implementation.

## Delivery and completion boundary

Application change: new author/operator retraction from existing browser/API entry through readable source, surviving conversation structure, rebuild/import and delayed cleanup. Reuse current interactions; no new navigation or explanatory UI. Include format specification under `docs/specs/`, affected retraction/source/API/CLI references, stale attribution documentation, tests and the existing policy/audit/index/study artifacts. Other JSON conversions, dependencies, deployment and history purge are excluded.

Release readiness requires passing normal-flow and failure-path checks plus inspection of the resulting text files and source pages. API results must identify the retained request, detached signature and acceptance receipt unambiguously. Recovery must distinguish failure before commit from failure after acceptance; retry must not create another acceptance commit.

## Risks and early validation

- **Evidence mismatch:** altered signed bytes or detached receipt metadata could invalidate signatures or misattribute acceptance. Validate real signed author/operator examples and tampering rejection first; define byte bindings and signed-versus-local fields before dependent work.
- **Partial file set:** multi-file failure can lose evidence or misreport deletion. Exercise failed commit and interrupted/repeated writes early; require coherent restoration or retry before reader integration.
- **Path assumptions:** existing readers/import/cleanup distinguish JSON acceptance from legacy text. Inventory those boundaries during planning; specify consistent recognition of the new file roles before switching writers. A formatting change must not admit forged imports or miss cleanup.

## Shared surfaces

Extend existing prepare/finalize and browser retraction controls, canonical parsers/repository, deletion transaction, source-path validation and import catalog/planner. Reuse current and historical source views, activity/source links, repository downloads, read-model rebuild and incremental updates, sweep CLI/worker and existing partial-failure messages. Verify shared projections used by threads/replies, boards/search/activity/profiles/feeds, post/thread APIs, static/high-traffic pages and offline base/update exports. No parallel rendering or acceptance system.

## Flow and success criteria

1. Author/operator requests and signs a retraction through the ordinary browser/API flow.
2. Acceptance removes the post and retains the three readable files in one commit; source access exposes their distinct roles.
3. Replies remain intact; rebuild/import and deferred cleanup consume the new receipt. On failure the user retries or rebuilds through existing recovery actions.

Verify author/operator and attribution cases, exact signature verification, mismatched/missing evidence rejection, failed/interrupted/repeated acceptance, partial refresh recovery, rebuild, archive non-resurrection and sweep recognition. Inspect browser/source flows on phone and desktop under `CONTRIBUTING.md`; no runtime success is claimed by this requirements artifact.

## Study checkpoint and request disposition

2026-10-25 UTC: scope v3/Option B, original baseline `2a07313a738af9e0a0f15796e8155d301d949ed5`, R1/P2; **0.60 total reaffirmed**. Exact three frozen hashes verified; Codex single exposed rater with prior inspection/plans visible. No new implementation inspection, measured labor or accepted outcome. Step 1 explicitly approved; Step 2 pending. User-requested audit prioritization is completed in the checklist; its 0–12 priority scores are separate from effort estimates and authorize no additional conversions. Earlier v1/v2 projections remain in Step 1. Planning and ledger remain uncommitted.

*— Codex · 2026-10-25 UTC*

Approval recorded, 2026-10-25 UTC: user explicitly sent “Approved Step 2.” Scope v3/Option B unchanged; proceed to development planning only.

*— Revised by Codex · 2026-10-25 UTC*
