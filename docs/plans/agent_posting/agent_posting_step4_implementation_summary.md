> **Feature plan:** [Step 1](./agent_posting_step1_solution_assessment.md) · [Step 2](./agent_posting_step2_feature_description.md) · [Step 3](./agent_posting_step3_development_plan.md) · [Step 4](./agent_posting_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/agent_posting; [ledger](../effort_estimation_trial.md#cycle-v3agent_posting)
Extended estimates: enabled
Cycle estimate: 0.95
Status: Seven stages complete locally; release verification and maintainer acceptance pending

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

## Stage 6 - Quickstart and complete error guidance

Original estimate: 0.08
Retrospective stage work: 0.08
Changes: frm.py quickstart first in llms/reference, explicit instance selection, key custody and --resume help, exact Unicode categories/positions, API error compatibility and bounded retry semantics. Removed unsafe blind-reprepare advice. Updated README, onboarding follow-up, both existing examples and catalog signature legend; repaired predecessor plan link after organization. Canonical post/bootstrap specs reviewed: unchanged protocol, no spec migration required.
Verification: LlmsTxtRendererTest, ApiRouteCatalogTest, FrmClientTest and AgentApiWorkedExampleTest: 18/18 passed. Instance facts cover ASCII/Unicode/emoji settings; rendered reference and members-only discovery pass; downloaded documented commands and old worked example publish through actual HTTP. No visual application UI changed, so theme/icon checks are inapplicable.
Notes: Grouped pending-key approval/session labels remain separate. Live URLs in quickstart are explicit placeholders; no request-host inference. No estimate revision.

*— Codex · 2026-10-30 UTC*

## Stage 7 - Clean-workspace acceptance and closeout

Original estimate: 0.15
Retrospective stage work: 0.18
Changes: Completed real downloaded-client acceptance and failure coverage. Corrected signed-record control diagnostics that otherwise failed in the generic parser before authored-text validation; restored the cheap valid-ASCII fast path. Added isolated malformed-response/discovery/retry-boundary tests and clarified that automatic busy retry applies to writes. These are approved validation/recovery corrections, not added product scope.
Verification: Focused PHP suite 66/66 passed; additional host/browser-response/schema-startup regressions 3/3 passed. Python frm failure-boundary tests 6/6 passed; existing Python/PHP/Perl publisher transport tests 7/7 passed. Includes real GPG/HTTP, downloaded bytes and all five commands, first/repeated/existing-key writes on signed and prepared paths, root/parent replies, unchanged accepted Unicode sample, symbol/spacing/control positions, decomposed accents/CRLF, malformed UTF-8 policy, busy bounds/exact replay, commit-then-response-loss, legacy missing/expired token, interrupted registration, unknown status, member denial, failed key selection, redirect refusal and refresh-pending replay. Snapshot/rebuild compatibility is also covered by the inherited single-request tests. No duplicate acceptance commits after retry.
Validation: Changed PHP files linted; Python parsed; diff whitespace and local plan links pass. Request source preserved in planning commit. No todo.txt or dependency manifest changes. Existing public asset packaging and PHP-routed GET/HEAD delivery require no new dependency or executable Python server handler. No read-model schema or canonical format migration.
Environment: Local Linux/WSL workspace, Python 3.10.12, PHP 8.1.2, GPG 2.2.27, curl 7.81.0; isolated open and members-only HTTP fixtures, ASCII/Unicode settings and older catalog fixtures. Standard library only; no package installation. Client UI is CLI/text, so phone/theme/icon screenshots are inapplicable; browser HTML compatibility and rendered agent reference checked by existing tests.
Notes: Full suite, production deployment/live ZenMemes download/read/write, other operating systems and large-instance latency were not verified. Existing full read-model rebuild cost on signed publication/replay is inherited unchanged. Local tests do not establish deployed acceptance. Upstream proxy/missing-runtime failures can still be non-JSON. Generated keys are unencrypted in private local state; existing protected-key pinentry interaction is supported but not interactively exercised.

## Closeout

Outcome: The approved posting slice is complete locally on feature/agent-posting. Planning commit 17395d9bb; S1 dcf89fe52, S2 0d9fdfdf7, S3 840ce1305, S4 12cd63ef5, S5 42c096407, S6 7a6f87f35; S7 is this closeout commit. No merge, push, deployment or production post.
Recovery boundary: Saved signed bundles can be resumed exactly. Older prepared APIs require operator reconciliation when a missing/unavailable read or expired token cannot establish the outcome; the client preserves the key, draft and operation, and blocks a blind replacement. Authentication/invitation/session automation remains outside this client slice. A killed process can leave a local empty writing.lock; remove it only after confirming no client is active, then resume. check is publication lookup, not signature verification.
Request disposition: Claude requests 1–3 and optional text_rules delivered with user-selected generic frm.py name. Request 4 (pending-key metadata/grouped manual approval) and optional session label remain deferred to separately approved work. No change to approval policy or retraction history behavior.
Documentation inventory: Client help/version, llms, API reference/catalog, README, both worked examples, onboarding checklist, plan navigation/index and study ledger updated. Canonical post/bootstrap specs reviewed and left unchanged. No external components added, updated or copied.
Study closeout: 2026-10-30 UTC; v3/agent_posting, v1.3/A, original baseline/R1/P2, single exposed Codex rater. Original projected 0.85 and revised 0.95 retained; Step 4 entry/projection 0.95, no prospective in-cycle revision. Separate retrospective whole-cycle scope assessment 1.00. Stage originals 0.18/0.18/0.12/0.20/0.18/0.08/0.15 retained; retrospectives 0.18/0.18/0.12/0.20/0.20/0.08/0.18 are not additive. Seven stages technically complete. Missing pristine intake, unknown measured labor and non-independent exposure preserved. Maintainer comfortable-fit/acceptance and release acceptance pending; no accepted-outcome increment. Predecessor protocol implementation remains separately recorded.

*— Codex · 2026-10-30 UTC*
