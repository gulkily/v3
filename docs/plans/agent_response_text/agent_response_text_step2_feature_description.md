> **Feature plan:** [Step 1](agent_response_text_step1_solution_assessment.md) · [Step 2](agent_response_text_step2_feature_description.md) · [Step 3](agent_response_text_step3_development_plan.md) · [Step 4](agent_response_text_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/agent_response_text — [ledger](../effort_estimation_trial.md)
Extended estimates: enabled

**Cycle estimate: 0.70**

## Problem and user stories

Agent-response metadata is embedded JSON inside signed posts. New records need readable headers while historical signatures and expansion relationships continue to work.

- As a reader, I want readable source metadata so that I can inspect a response's origin and version.
- As a member, I want existing responses and their expansions to keep working across the format change.
- As a maintainer, I want isolated legacy decoding and a readiness report so that later removal has explicit prerequisites.

## Core requirements

- Write only named text headers for new scripture-analysis and expansion replies; document fields and malformed-input behavior. Preserve author identity, source/initial version hashes, deterministic expansion identity and signing of the complete new record. Ordinary replies without metadata remain unchanged.
- Read old and new formats into the same validated metadata model. Preserve historical post/signature bytes exactly; support retained old expansions and new expansions of old responses. Reject mixed old/new declarations and duplicate or incomplete new metadata explicitly rather than choosing one or treating it as absent. Preserve existing pre-metadata handling; no historical conversion or regeneration.
- Isolate legacy canonical-header decoding behind one removable boundary. Shared validation, internal hash serialization, derived read-model/offline JSON and new-format consumers must work without that adapter. Keep normal compatibility enabled until a separately authorized retirement; no sunset flag.
- Add read-only `./v3 agent-reply format-readiness --repository-root=…`, with readable named values and explicit `--json` output. List legacy posts, dependent expansions and invalid/ambiguous metadata; distinguish blockers, an incomplete scan and a completed live-tree scan. Do not initialize storage, rebuild or mutate records. Report unexamined Git history/external archives as unknown. Document removal wiring/tests and the required retained-record/archive decision; readiness never authorizes deletion or implies universal restore compatibility.
- Preserve generation/expansion entry points, permissions, stale-source rejection and publication retry through source/download, online display, full/incremental rebuild, import/export and offline reading. Failed generation must retain its existing recovery path; retries must not publish a second expansion or alter an already signed record.

## Delivery scope and shared components

Work type: Application change. Extend the existing post parser/writer and `AgentResponseMetadata`; reuse generation fulfillment and `ScriptureExpansionService`. Preserve shared post/thread-card expansion rendering, thread/post and direct-expansion routes, expansion request/status and scripture status APIs, raw source/download/archive paths, read-model projection and offline reader/snapshot consumers. The new readiness subcommand extends the existing `agent-reply` command family because there is no current format-retirement inspection entry point. No product UI redesign or schema change is intended.

Concepts touched: Agent-response attribution (representation changed), expansion/version binding and offline projection (behavior preserved); add only these proposed inventory entries during implementation. Documentation scope: cycle plans/debug, study ledger, checklist, canonical/read-model format descriptions and affected agent/CLI/retirement references. No prompt/quality changes, unrelated formats, dependencies, production mutation, actual reader removal or deployment.

## Flow and completion boundary

1. A member requests scripture analysis, then expands an eligible response; new signed records expose readable metadata and retain their source/version relationships.
2. Old responses and mixed-generation expansion chains remain usable after rebuild, import and offline export; failed/stale requests retain the existing actionable retry behavior.
3. An operator runs readiness, reviews named legacy records/dependencies and archive uncertainty, then follows the documented prerequisites for a future removal decision.

Complete when normal generation/expansion and recovery pass for old/new records, signatures and historical bytes remain intact, ambiguous input fails explicitly, and new-format write/read/rebuild/import/offline flows pass with legacy decoding unavailable. Verify affected rendered flows under CONTRIBUTING guidance. Report unverified environments; deployment and actual retirement are separate.

## Risks and early validation

- Signature or version drift would invalidate evidence: before writer changes, establish old/new fixtures with identical metadata/hash results and byte-preserved historical records; never reserialize old signed posts.
- Decoder coupling could break offline/expansion after removal: identify legacy-header versus machine-JSON callers before changing parsing; validate current-format flows with adapter loading blocked.
- A false readiness result could strand posts or backups: define scan/dependency coverage before the command; malformed input and incomplete scans must prevent a ready result, and external archives/history remain explicit unknowns.

## Approval and study checkpoint

Approved Step 1 received for revised Option A (scope v2/A), including explicit eventual-removal design; no outstanding enhancement request. Baseline 9005e87611b82e2304c0be52505d994674dc4efa, R1/P2 standing enrollment, Codex single exposed rater. Step 2 handoff, 2026-10-29 UTC: **0.70 reaffirmed** after command-family and shared UI/API surface inspection; original intake/v1 0.65 remains in Step 1. Frozen reference hashes reverified. No implementation, measured labor, dependency delta or accepted outcome. Planning uncommitted; await Approved Step 2.

*— Codex · 2026-10-29 UTC*

Approval update: Step 2 approved; [Step 3](agent_response_text_step3_development_plan.md) records the development plan. Earlier awaiting-approval snapshot remains historical.

Implementation update: Steps 1–3 approved; all five stages are recorded in the [Step 4 summary](agent_response_text_step4_implementation_summary.md). Earlier approval/checkpoint statements remain historical.
