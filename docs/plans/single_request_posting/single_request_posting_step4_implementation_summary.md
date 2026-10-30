> **Feature plan:** [Step 1](./single_request_posting_step1_solution_assessment.md) · [Step 2](./single_request_posting_step2_feature_description.md) · [Step 3](./single_request_posting_step3_development_plan.md) · [Step 4](./single_request_posting_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/single_request_posting; [ledger](../effort_estimation_trial.md#cycle-v3single_request_posting)
Extended estimates: enabled
Cycle estimate: 1.05
Status: Implementation in progress

Entry: 2026-10-30 UTC; Step 3 approved, scope v2/B, R1/P2, baseline b3a5b1621e9df25bac6e023c26aca66537852dfe. Planning commit 2bea587fb on feature/single-request-posting. 1.05 reaffirmed; Codex single exposed rater, pristine intake missing, no measured labor. Original S1–S8 estimates: 0.16/0.12/0.20/0.18/0.10/0.10/0.08/0.15, not additive. Shared acceptance belongs to S8. frm.py remains paused.

## Stage 1 - Validate locally constructed signed records

Original estimate: 0.16
Retrospective stage work: 0.16
Changes: Added strict canonical ordinary/bootstrap shape validation, IDs/time window and exact-byte policy checks; signature/repository validation remains in S2 as planned. Updated canonical spec and test registration.
Verification: php tests/run.php SignedPostBundleValidatorTest — 2/2 passed, including unsupported headers/tags, Unicode symbol refusal and timestamp boundary. No HTTP path exists yet.
Notes: Scope unchanged; original estimates retained. No dependency addition.

*— Codex · 2026-10-30 UTC*

## Stage 2 - Resolve new and known identities

Original estimate: 0.12
Retrospective stage work: 0.12
Changes: Mutation-free key/bootstrap resolution, two-signature verification, derived registration files, legacy lowercase key lookup and known-identity reuse. Existing identity state is never overwritten.
Verification: php tests/run.php SignedPostIdentityTest — 1/1 passed with actual GPG, fresh registration bundle, known-key/bootstrap reuse, omission and tampered signature rejection.
Notes: Stage 1 commit efa0734f1. No estimate revision or dependencies. Transaction lock and atomic storage follow in S3.

*— Codex · 2026-10-30 UTC*

## Stage 3 - Commit registration and post atomically

Original estimate: 0.20
Retrospective stage work: 0.20
Changes: Private readable pending manifest with exact payload files; owned-path checks, isolated staging, canonical-commit detection and rollback/recovery. Recovery runs before later writes and before Application read-model startup. No unrelated index reset or key overwrite.
Verification: php tests/run.php SignedPostTransactionTest — 2/2 passed; injected failures at journal/file/staged/committed boundaries, unrelated staged content preservation and symlink refusal. Abrupt process termination is reserved for integrated S8 replay.
Notes: S2 commit 4191c636f. No scope/estimate revision. Journal is private recovery state, not a new canonical record family. No schema/dependency delta.

*— Codex · 2026-10-30 UTC*

## Stage 4 - Reconcile retries and refresh derived state

Original estimate: 0.18
Retrospective stage work: 0.18
Changes: Added locked createSignedPost integration, committed-byte replay/original-commit response, deletion/conflict checks, atomic label side effects and known-key omission. Post-commit refresh failures return accepted with recovery guidance. A full existing rebuild synchronizes the combined bundle once; this favors correctness over an unproven combined incremental update and is a latency limitation to measure in S8.
Verification: php tests/run.php SingleRequestPostingTest SignedPostBundleValidatorTest SignedPostIdentityTest — 6/6 passed. Real GPG first thread/reply, separate anchor, exact replay/key omission, one canonical commit, count stability, invalid bootstrap signature and injected refresh failure/recovery. Concurrency, aged replay and removal remain in S8 integration.
Notes: S3 commit 35c23c142. No schema or scope change. Text policy applies to new submissions; committed replay remains independent of later mode changes.

*— Codex · 2026-10-30 UTC*

## Stage 5 - Expose the single-request HTTP path

Original estimate: 0.10
Retrospective stage work: 0.10
Changes: POST /api/create_signed_post, route catalog, structured validation/access/conflict/removal errors and endpoint-specific outer-host failures. Busy returns JSON 503 with Retry-After 10. Only newly published ordinary posts enqueue fast-score work.
Verification: php tests/run.php SingleRequestHttpTest ApiRouteCatalogTest — 6/6 passed over real HTTP, including fresh submission/retry, wrong method, non-text fields, held execution lock, broken startup database, sanitized server error and members-only denial. PHP lint and diff whitespace checks pass.
Notes: S4 commit 03f538a4c. Existing route error contracts unchanged. Failures before PHP FrontController entry remain outside this handler's control.

*— Codex · 2026-10-30 UTC*

## Stage 6 - Provide the standalone signing example

Original estimate: 0.10
Retrospective stage work: 0.10
Changes: Executable shell/Python-standard-library/curl/GPG example constructs and signs both records locally, saves exact retry artifacts and submits once. Existing keys and known-key bootstrap omission supported; generated keys stay in a private bundle-local keyring. No automatic retry or draft rewriting. Legacy example cross-linked.
Verification: SingleRequestHttpTest and AgentApiWorkedExampleTest — 5/5 passed. Additional executable-example rerun passed with initial thread, exact saved replay and known-key reply without bootstrap. The old prepare/sign/finalize example still publishes successfully.
Notes: S5 commit e230c1686. Example-generated keys are unencrypted and protected by local directory permissions, explicitly documented in script help/source. No new packages.

*— Codex · 2026-10-30 UTC*
