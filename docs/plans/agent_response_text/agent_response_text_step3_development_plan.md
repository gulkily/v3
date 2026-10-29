> **Feature plan:** [Step 1](agent_response_text_step1_solution_assessment.md) · [Step 2](agent_response_text_step2_feature_description.md) · [Step 3](agent_response_text_step3_development_plan.md) · Step 4 pending

Debug mode: enabled
Effort study: railspike-effort / v3/agent_response_text — [ledger](../effort_estimation_trial.md)
Extended estimates: enabled

**Cycle estimate: 0.70**

## Completion Contract

Normal scripture generation/expansion writes readable signed headers; old signed records, mixed-generation relationships and retry behavior survive source inspection, rebuild, import/export and offline reading. Read-only readiness identifies live legacy dependencies without implying archive clearance. New-format flows must work with legacy decoding unavailable. Deliver verified code/spec/CLI/retirement guidance; no production mutation, deployment or actual reader removal.

Concepts touched: Agent-response attribution (representation changed), expansion/version binding and offline projection (preserved); add only these inventory entries. Approved documentation scope remains Step 2. Zero dependency delta; no schema change intended. Any necessary read-model schema change must satisfy CONTRIBUTING before dependent work.

## Key Risks

- **High risk:** Reserialization invalidates signatures/version relationships. S1 proves semantic equivalence without rewriting historical bytes before writer changes.
- **High risk:** Removing a shared JSON decoder could break machine-data consumers. Separate canonical legacy decoding from shared validation; S4 blocks adapter loading to expose coupling.
- **High risk:** Incomplete readiness scans could falsely permit retirement. S4 defines conservative completeness and dependency reporting; old archives/history remain unknown.

## Stage 1 — Compatible canonical metadata

**Estimate: 0.15**

- Goal/dependencies: Define and parse both representations; no prior stage.
- Changes/contracts: `AgentResponseMetadata`, `PostRecordParser`, isolated legacy-header adapter and canonical spec; proposed interfaces `fromHeaders(array, ?string): ?array` and `toHeaders(array): string`, with shared semantic validation independent of legacy storage. Reuse generic duplicate-header rejection.
- Verification: Old/new metadata equivalence, unchanged source/version hashes and signed fixture bytes; mixed, duplicate, incomplete and invalid declarations reject; ordinary and pre-metadata replies remain supported.
- Risk/mitigation: Preserve historical interpretation; no opportunistic tightening of unrelated legacy semantics. Resolve ambiguous-header rules before S2.
- Stage-work driver/uncertainty: Parser boundary and compatibility fixtures; historical optional metadata variation.

## Stage 2 — Signed generation and expansion

**Estimate: 0.15**

- Goal/dependencies: Publish new headers through normal generation and expansion; S1.
- Changes/contracts: Shared reply writer, generation fulfillment and expansion service; preserve signer, identity, deterministic expansion ID and existing retry protocol.
- Verification: Actual generation/expansion entry points using deterministic provider fixtures; verify detached signatures, source header readability, new expansion of an old response, stale-source rejection and commit/refresh retry without duplicate publication.
- Risk/mitigation: Stored generation and existing expansion recovery must not rewrite signed records; compare historical bytes and retry identity before proceeding.
- Stage-work driver/uncertainty: Signed publication/recovery integration; hidden assumptions in stored metadata comparisons.

## Stage 3 — Rebuild, transfer and offline compatibility

**Estimate: 0.12**

- Goal/dependencies: Carry both formats through normal consumers; S2.
- Changes/contracts: Full/incremental projections, archive/content import/export, offline snapshot/reader and shared expansion presentation; preserve existing normalized machine JSON, changing consumers only where required.
- Verification: Old/new source-initial-expansion combinations; full/incremental equivalence, archive round trip with unchanged signed bytes, source/download inspection, offline grouping and stale-version rejection.
- Risk/mitigation: A metadata rename must not alter visibility or version matching. Compare normalized results before/after rather than redesigning these policies.
- Stage-work driver/uncertainty: Cross-consumer verification; offline fixture coverage. Keep any discovered policy redesign outside this cycle.

## Stage 4 — Retirement inspection and removal proof

**Estimate: 0.15**

- Goal/dependencies: Provide a bounded future-removal path; S3.
- Changes/contracts: `v3 agent-reply format-readiness`, read-only scanner, top-level/subcommand help and removal guide. Scan canonical flat/dated posts without bootstrapping or rebuilding; list legacy IDs/paths, dependent expansions and malformed/ambiguous records. Report incomplete/unreadable/changing input conservatively. Default named text; explicit `--json` preserves the same facts and documented exit semantics.
- Verification: Actual CLI with clean, legacy, mixed-generation, invalid and incomplete fixtures; no writes. Fresh-process tests block legacy adapter loading while new-format generation, expansion, rebuild, import and offline export work; old input fails explicitly.
- Risk/mitigation: Do not confuse adapter removal with removal of machine JSON or hash verification. Document exact wiring/tests to remove and retained-post/archive decisions; no sunset setting or automatic deletion.
- Stage-work driver/uncertainty: Reliable dependency/completeness inspection; graph and unreadable-path edge cases.

## Stage 5 — Acceptance and closeout

**Estimate: 0.15**

- Goal/dependencies: Verify the full contract and close F05 with evidence; S1–S4. Owns shared acceptance overhead.
- Changes/contracts: Focused parser/generation/expansion/offline/CLI tests, normal API/browser acceptance, affected spec/reference/concept docs, checklist, ledger and implementation summary.
- Verification: Actual online thread/post/expansion flow, source and offline rendering at phone/desktop under CONTRIBUTING guidance; pending/failure/retry/stale cases, permissions and signature checks. Use existing deterministic provider infrastructure; stubbed UI checks alone do not establish publication. Record exact commands, limitations and dependency delta.
- Risk/mitigation: Leave F05 open if normal-flow or decoder-unavailable acceptance fails. Do not infer full-suite, live-provider quality, physical-device or deployment results.
- Stage-work driver/uncertainty: End-to-end fixtures and documentation; browser/offline setup and ordinary corrections.

## Execution and study checkpoint

Approved Step 2 received; scope v2/A and eventual-removal requirements unchanged. After Approved Step 3, create a feature branch, organize plans/debug log and repair index/links, commit approved planning, then create Step 4 summary. Verify and commit each stage with its summary before advancing. Planning remains uncommitted now.

2026-10-29 UTC: Whole-cycle **0.70 reaffirmed**, original baseline 9005e87611b82e2304c0be52505d994674dc4efa, R1/P2, Codex single exposed rater; frozen hashes verified. Prior 0.65 intake/v1 remains preserved. S1–S5/v1 stage-work originals are independent, non-additive estimates; no measured labor. Added exposure: generic parser already rejects duplicate headers; existing expansion browser tests use intercepted responses, so normal publication needs separate acceptance. No implementation or accepted outcome; await Approved Step 3.

*— Codex · 2026-10-29 UTC*
