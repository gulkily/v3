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

## Stage 3 - Downloadable client and reads

Original estimate: 0.12
Retrospective stage work: 0.12
Changes: Versioned standalone public/frm.py, explicit site selection, curl transport without redirects/config inheritance, index/read/check and recognizable-catalog protocol discovery. Public GET/HEAD delivery uses UTF-8 text and nosniff through normal host and Application routes; public packaging already includes public assets. Read operations create no identity state.
Verification: FrmClientTest and AgentPostingApiTest: 4/4 passed. Downloaded bytes executed from isolated workspace against two instances; real index/read/check, missing and members-only unknown status, MIME and HEAD verified. Corrected initial Application delivery call to use projectRoot/public (Application has no publicRoot property). Apache routes through this same host; no separate Python execution handler added.
Notes: Plain HTTP remains supported for legacy compatibility. Transport errors remain unknown, and authored data is never redirected. No estimate revision.

*— Codex · 2026-10-30 UTC*

## Stage 4 - First-use signed threads and replies

Original estimate: 0.20
Retrospective stage work: 0.20
Changes: Persistent per-instance key selection and private generated keyring; first-use key generation defaults to frm-agent. Signed path constructs separate bootstrap/post locally and sends one publication bundle. Known keys and anchors are reused; older recognized catalogs use prepared registration/publication. Original bodies, exact signing files and operation metadata are retained before submission. Existing-key custody and GPG passphrase behavior are explicit in help.
Verification: FrmClientTest: 2/2 passed, including downloaded client first thread, root/parent replies, repeated key reuse, selected existing key, actual older-catalog fallback and unchanged Unicode readback. Identity count remains one new identity per key. Initial count assertion corrected to include the fixture's existing identity. Runtime uses Python standard library/curl/GPG only.
Notes: Replay/recovery orchestration and detailed failure tests follow in S5/S7. Generated keys are unencrypted, confined to private local directories; no private material sent. No estimate revision.

*— Codex · 2026-10-30 UTC*

## Stage 5 - Bounded retries and resumable recovery

Original estimate: 0.18
Retrospective stage work: 0.20
Changes: Default two busy retries (configurable 0–5), honored Retry-After with waits above 60 seconds left to explicit resumption; exact payload persistence and thread/reply --resume. In-flight operations block new writes. Signed bundles replay unchanged; legacy finalization reconciles readback and stops when missing/unknown, while interrupted registration resolves the same key. Rejected signed validation permits a corrected new draft. Errors show original-file positions through NFC/newlines; corrected composed-grapheme mapping for a format character after an accent.
Verification: FrmClientTest 7/7 and UnicodeTextPolicyTest 13/13 passed. Real HTTP/GPG tests inject accepted-but-lost responses on both protocols, bounded busy/exact payload retries, interrupted legacy registration, missing/expired legacy token, blocked duplicate write and original-coordinate character correction. Commit HEAD remains unchanged on accepted retry. No protocol fallback after submission.
Notes: Older prepared API cannot prove non-acceptance from a missing read; operator reconciliation is the explicit recovery boundary. A killed process may leave writing.lock; help/error names the empty directory to remove only after confirming no client is active. Stage retrospective increased for recovery integration; whole-cycle projection remains 0.95, no scope change or measured labor claim.

*— Codex · 2026-10-30 UTC*
