> **Feature plan:** [Step 1](./agent_posting_step1_solution_assessment.md) · [Step 2](./agent_posting_step2_feature_description.md) · [Step 3](./agent_posting_step3_development_plan.md) · [Step 4](./agent_posting_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/agent_posting; [ledger](../effort_estimation_trial.md#cycle-v3agent_posting)
Extended estimates: enabled
Cycle estimate: 0.95
Status: Implementing approved Step 3 v1.3/A

Entry: 2026-10-30 UTC. User approved Step 3; planning commit 17395d9bb on feature/agent-posting, prerequisite 28665bdcd. Original baseline b3a5b1621e9df25bac6e023c26aca66537852dfe/R1/P2 retained. Whole-cycle 0.95 reaffirmed; original 0.85 retained in plans. Single exposed Codex rater, missing pristine intake, no measured labor. Stage estimates S1–S7: 0.18/0.18/0.12/0.20/0.18/0.08/0.15; scopes and v1/v2 histories in approved Step 3, not additive. S7 owns shared verification. No dependency additions, deployment, production posting or grouped approval work authorized.

*— Codex · 2026-10-30 UTC*

## Stage 1 - Actionable text rejection

Original estimate: 0.18
Retrospective stage work: 0.18
Changes: Typed text errors expose field, character, U+ code point and original submitted-field index through shared JSON error payloads. Unicode diagnostics track trim/newlines/NFC; ASCII paths reject malformed UTF-8 without invented coordinates. Signed posting uses the same diagnostics. Accepted canonicalization is unchanged.
Verification: UnicodeTextPolicyTest, CanonicalTextBoundsTest, SignedPostBundleValidatorTest and SingleRequestHttpTest passed (21 tests). Explicit coordinate fixtures cover decomposed accents, CRLF, leading whitespace and repeated symbol/spacing/format errors. Existing large-text bound and real signed HTTP checks pass. No UI/schema/dependency changes.
Notes: Server positions on signed bundles are body/subject-relative; original client-file mapping follows in the client stage. No estimate revision.

*— Codex · 2026-10-30 UTC*

## Stage 2 - Rule discovery and API failure recovery

Original estimate: 0.18
Retrospective stage work: 0.18
Changes: Public GET /api/text_rules reports active policy and normalization; route catalog includes discovery. Shared JSON/text response boundaries promote caught lock failures to JSON 503 with Retry-After. Host failures for /api and /api/ descendants are sanitized JSON while retaining schema-recovery scheduling; unknown API and membership errors are JSON. Legacy ordinary text errors and browser HTML remain.
Verification: AgentPostingApiTest, ApiRouteCatalogTest and SingleRequestHttpTest: 11/11 passed over actual HTTP. Covers Unicode diagnostics, members-only discovery/denial, caught write lock failures on prepared/legacy routes, unknown/query paths, /api and /api/ startup errors, unchanged legacy method text and browser HTML. No dependency/schema delta.
Notes: PHP-controlled responses only; upstream proxy/runtime failures can still be non-JSON. No estimate revision.

*— Codex · 2026-10-30 UTC*
