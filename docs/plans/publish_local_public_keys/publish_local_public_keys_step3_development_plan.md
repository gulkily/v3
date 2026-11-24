> **Feature plan:** [Step 1](./publish_local_public_keys_step1_solution_assessment.md) · [Step 2](./publish_local_public_keys_step2_feature_description.md) · [Step 3](./publish_local_public_keys_step3_development_plan.md) · [Step 4](./publish_local_public_keys_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/publish_local_public_keys; [cycle ledger](../effort_ledger/publish_local_public_keys.md), standing P2 enrollment.
Extended estimates: enabled

**Cycle estimate: 0.65**

## Completion Contract

`./v3 publish-keys DESTINATION --plan=FILE` previews; `--publish=FILE --key=FINGERPRINT` publishes reviewed selections. Default invocation only previews. Bind source/destination, confirm remote identities and support partial retry. Preserve approvals and existing keys. Verify ordinary WSL commands against isolated instances, document recovery and review the final diff. Production acceptance needs separate authorization; full suite remains maintainer-run.

## Key Risks

- **High risk:** public labels can conceal secret packets; the synthetic GPG probe confirmed this. Require public-only inspection before network access.
- **High risk:** stale plans/names/retirements can publish unwanted identities. Bind source hashes/destination and reject changes before writes.
- Lost responses can hide acceptance. Distinguish genuine absence from refusal/error; read back before retry. [Source and recovery evidence](publish_local_public_keys_assessment_evidence.txt).

## Stage 1 — Read-only preview

**Estimate: 0.18**

- Goal: preview canonical identities and destination status.
- Dependencies: approved plans.
- Expected changes: wire `v3`, `scripts/publish_public_keys.php` and bounded publisher helpers; classify names, retirements and responses.
- Verification approach: private/malformed/escaped inputs, preview-only HTTP; smoke plus OpenPgpKeyInspectorTest and canonical parser checks.
- Risks or open questions: reject secret packets, redirects and unknown responses before publication exists.
- Canonical components/API contracts touched: reuse identity/name/retirement parsers and get_profile; add opt-in public-only inspection without changing existing inspect behavior.
- Estimate details: inventory/response integration; imported-data uncertainty.

## Stage 2 — Publication and retry

**Estimate: 0.22**

- Goal: publish explicit selections with confirmed outcomes.
- Dependencies: Stage 1 safeguards pass.
- Expected changes: Key: value plans, source/destination binding, selected registration and read-back results.
- Verification approach: changed inputs, duplicate races, partial acceptance and dropped responses; smoke plus publisher/command tests.
- Risks or open questions: stop changed plans before writes; keep unknown outcomes retryable.
- Canonical components/API contracts touched: GenericTextRecordParser and existing link_identity/get_profile; normal bootstrap anchors, no new server API.
- Estimate details: review binding/reconciliation; response-compatibility uncertainty.

## Stage 3 — Ordinary-flow acceptance

**Estimate: 0.15**

- Goal: replay the WSL command flow and failures on isolated instances.
- Dependencies: Stage 2 complete.
- Expected changes: real CLI/HTTP fixtures, fault injection and scoped corrections.
- Verification approach: zero preview writes; exact selection; duplicate-free retry; mixed/private/name/retirement/refusal cases; shared registration consumers.
- Risks or open questions: fixture success does not prove production acceptance; retain unavailable checks without bypassing access.
- Canonical components/API contracts touched: existing PHP fixtures, WriteApiSmokeTest and IdentityBootstrapDiagnosticsTest; preserve browser/Quill contracts.
- Estimate details: recovery replay; timing/baseline-failure uncertainty.

## Stage 4 — Documentation and handoff

**Estimate: 0.08**

- Goal: enable unaided preview, publish and retry.
- Dependencies: Stage 3 evidence retained.
- Expected changes: CLI guidance, touched concepts, feedback progress, cycle summary/ledger.
- Verification approach: follow documented commands on fixtures; check outputs, paths, index and diff; report actual test/production limits.
- Risks or open questions: reconcile F03's accepted intake outside main without overwriting IDs or closing the broader request.
- Canonical components/API contracts touched: docs/reference/v3_cli.md, scripts/README.md, concepts and existing feedback record.
- Estimate details: owns shared release documentation; feedback-integration uncertainty.

## Concepts and study checkpoint

Add public-key publication; use approval acceptance/key retirement unchanged, as approved in Step 2. Preserve import behavior. 2026-11-22T21:43:30+00:00: Step 2 approved; scope v1/A, baseline 22fc9f10a4d4f8cba9ecaed501aba2ea90d129e4, R1/P2. Total revised 0.50 → 0.65 for newly understood strict inspection, review binding and fault verification; scope unchanged. Stage estimates cover their own checks/corrections, not repeated cycle planning; never sum them. Codex, single exposed rater; frozen hashes reverified. No implementation, measured effort or acceptance claim.

*— Codex · 2026-11-22 UTC*
