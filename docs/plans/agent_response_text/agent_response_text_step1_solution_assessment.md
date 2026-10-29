> **Feature plan:** [Step 1](agent_response_text_step1_solution_assessment.md) · [Step 2](agent_response_text_step2_feature_description.md) · [Step 3](agent_response_text_step3_development_plan.md) · [Step 4](agent_response_text_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/agent_response_text — [ledger](../effort_estimation_trial.md)
Extended estimates: enabled

**Cycle estimate: 0.70**

## Original Query

Write Step 1, make sure checklist is up to date.

## Understood Intent

Assess F05 from the [human-readable format checklist](../human_readable_formats_checklist.md) and reconcile its current status after F03 merged. Planning and checklist upkeep are authorized; implementation is not.

## Problem and concepts

`Agent-Response: {…}` embeds JSON inside signed posts; replacing its representation must preserve attribution, source versions and expansion relationships without invalidating old signatures.

Concepts touched: Agent-response attribution (representation changed), response expansion/version binding and offline projection (behavior preserved). Relevant inventory entries are proposed for implementation; the existing approval inventory is unrelated. Sources: [metadata](../../../src/ForumRewrite/Canonical/AgentResponseMetadata.php), [post parser](../../../src/ForumRewrite/Canonical/PostRecordParser.php), [writer](../../../src/ForumRewrite/Write/LocalWriteService.php), [expansion service](../../../src/ForumRewrite/Agent/ScriptureExpansionService.php).

## Option A — New headers, unchanged historical posts (recommended)

**Estimate: 0.70**

Write individually named headers for new scripture-analysis and expansion replies; read both formats into the existing metadata model. Preserve old posts/signatures exactly, including when a new expansion refers to an old response.

- Retains attribution, source/initial hashes, deterministic expansion identity and existing retry behavior. Keep derived `agent_response_json`, offline JSON and internal hash serialization as machine data; no schema change is intended.
- Isolate legacy header decoding behind one compatibility boundary, returning the same validated metadata as new headers. Keep shared validation, source/version hashing and machine JSON independent of it; new writers emit only named headers.
- Include a read-only retirement-readiness check listing old-format posts, dependent expansions and invalid/ambiguous metadata; report archive coverage as unknown unless inspected. Document the exact adapter/parser wiring and compatibility tests to remove. Verify new-format generation, expansion, rebuild, import and offline behavior with legacy decoding unavailable.
- Unlike F03's unsigned receipts, these signed records cannot be converted in place. Removing legacy interpretation requires an explicit decision about retained posts, dependent relationships and old archives. No automatic sunset, data deletion, historical rewrite or silent treatment of old metadata as absent.
- Main work/uncertainty: consistent mixed-format parsing, ambiguous/duplicate-header rejection and end-to-end expansion/offline verification. Specify mixed old/new headers explicitly rather than silently choosing one.

## Option B — New headers only, recreate dependent responses

**Estimate: 0.45**

Accept only the new metadata format for supported agent responses, with old replies and their dependent expansions explicitly discarded or regenerated as new records.

- Smaller compatibility surface; no ongoing legacy-header decoder.
- Requires explicit F05 authorization to lose or replace historical response evidence and dependent links, plus a separate old-archive decision. Existing F01/F02 exceptions do not apply; new signatures cannot preserve the old signed bytes or identity of a regenerated response. Main uncertainty is whether any such evidence is disposable.

## Recommendation and completion boundary

Choose A: one slice from normal agent generation and expansion to readable source/download, online display, full/incremental rebuild, import/export and offline rendering. Verify old-to-new response/expansion combinations, unchanged source/version hashes, signature checks, stale-source rejection and publication retry. Ordinary replies without metadata and existing pre-metadata expansion handling remain unchanged. The readiness inspection is part of this scope; its operator entry point will be specified in Step 2. No runtime sunset flag is required. Preserve normal compatibility until an explicitly authorized later removal.

Scope includes format specification, affected tests/docs, touched concept entries, checklist and study bookkeeping. No agent-quality/prompt redesign, authority change, dependency additions, production data mutation or unrelated format conversions. The checklist remains 4 complete/12 open; F05 is in Step 1, not complete.

## Study snapshots and approval

Cycle: v3/agent_response_text; scope v1/A; baseline 9005e87611b82e2304c0be52505d994674dc4efa; reference R1; protocol P2, standing enrollment. Codex single exposed rater, 2026-10-29 UTC. Intake **0.65** recorded before this cycle's detailed source inspection, with prior audit/F03/reference-score exposure disclosed. Step 1 handoff **0.65 reaffirmed** after parser/writer, generation/expansion and read-model/offline inspection; all three frozen hashes verified. Estimates cover complete scoped cycles, are not additive or measured time. Distinct F05 cycle, no parent split/replacement; no implementation or accepted outcome. Await Approved Step 1; documents remain uncommitted.

*— Codex · 2026-10-29 UTC*

## Revision 1 — Explicit legacy retirement design

Request: “Will we retain compatibility with old format? If so, can you design keeping in mind that we want to eventually remove that?”
Disposition: Yes, retain compatibility under Option A and make removal readiness explicit: isolated decoding, independent current-format behavior, read-only dependency inspection and a documented removal procedure. Incorporated as scope v2/A; actual retirement and disposition of old signed evidence remain later decisions. This request revises Step 1; it does not approve advancement.
Study checkpoint: 2026-10-29 UTC, Step 1 revision 1; whole-cycle **0.70** revises preserved intake/v1 handoff **0.65** for the added readiness/removal verification scope. Option B remains **0.45**, non-additive. Same original baseline/R1/P2/single exposed Codex rater; frozen hashes reverified. No implementation, measurement or accepted outcome; await Approved Step 1.

*— Revised by Codex · 2026-10-29 UTC*

Approval update: The maintainer approved Step 1, revised Option A. [Step 2](agent_response_text_step2_feature_description.md) records the requirements; earlier approval-state snapshots remain historical.

Implementation update: Steps 1–3 approved; all five stages are recorded in the [Step 4 summary](agent_response_text_step4_implementation_summary.md). Earlier approval/checkpoint statements remain historical.
