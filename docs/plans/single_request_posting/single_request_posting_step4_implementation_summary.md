> **Feature plan:** [Step 1](./single_request_posting_step1_solution_assessment.md) · [Step 2](./single_request_posting_step2_feature_description.md) · [Step 3](./single_request_posting_step3_development_plan.md) · [Step 4](./single_request_posting_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/single_request_posting; [ledger](../effort_estimation_trial.md#cycle-v3single_request_posting)
Extended estimates: enabled
Cycle estimate: 1.05
Status: Eight stages complete locally; release verification pending

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

## Stage 7 - Update discovery, references and handoffs

Original estimate: 0.08
Retrospective stage work: 0.08
Changes: Updated agent reference, generated llms route/guidance, README, trust/access references, onboarding checklist and G2 follow-up notes. Canonical specs and examples were updated in their owning earlier stages. Exact limits, bootstrap/key reuse, member access and endpoint-specific replay/errors are documented.
Verification: php tests/run.php LlmsTxtRendererTest ApiRouteCatalogTest — 10/10 passed, including reference route inventory/rendering and members-only doc access. Assumption search in current reference/spec/architecture/examples/scripts found the three existing publish_text clients and prepared example: retained deliberately as supported prepared-flow alternatives. Historical agent_api_docs/bootstrap/profile-cost plans remain historical, with G2 follow-up notes only. No new documentation requirement discovered.
Notes: S6 commit 74ead35cf. The paused agent_posting Step 3 is an untracked/unapproved earlier-cycle document: handoff appended in the working tree but intentionally not added wholesale to this cycle's commit. This summary is the committed handoff: on explicit resumption, prefer create_signed_post with prepared fallback; frm.py remains paused. No implementation in that cycle is authorized.

*— Codex · 2026-10-30 UTC*

## Stage 8 - Verify ordinary use and close the cycle

Original estimate: 0.15
Retrospective stage work: 0.22
Changes: Added integration coverage for aged replay, concurrency, SIGKILL recovery, rejected Git commits, separate-anchor approval/deletion, actual HTTP Unicode and snapshot/CLI rebuild/import. Corrected replay to consult combined signed/operator removal receipts; restricted armor to a single public-key/signature block, sanitized key-inspection failures, and restricted Git commit to owned paths. CLI rebuild recovers interrupted bundles before scanning. These are corrective work inside approved recovery/validation scope, not new features.
Verification: Focused PHP run 51/51 passed; final targeted rerun 19/19 passed after armor/error hardening and actual CLI import coverage. Tests include real GPG and HTTP, fresh thread/reply, retained bootstrap/key, optional known material, CRLF armor replay, malformed fields, members-only denial, busy Retry-After, startup errors, Unicode sample readback and symbol rejection, original-commit replay, two concurrent first posts with different bootstrap proposals, post-count stability, lost-response equivalent resend, aged replay, rejection/cleanup and post-commit refresh recovery. SIGKILL tests cover file/staged/committed boundaries. Existing prepared worked example and selected approval/retraction flows pass. ./v3 rebuild and snapshot export/extraction/rebuild passed; CLI import passed after byte-preserving archive layout repack (see limit below).
Browser verification: Existing approval_review.mjs passed desktop/mobile confirmation, cancellation, missing review, signing failure and retry. A live local ZenMemes-profile instance containing identities created by the new endpoint rendered pending/profile views at 1280 and 390 pixels in light, dark and Word97; Approve visible, no pending-page document overflow. Phone screenshot visually inspected. New identity was separately approved through real signed server flow. No UI markup or navigation icons changed.
Notes: S7 commit c9856e9c1. One initially failing process-kill test invoked only the Application constructor; corrected to exercise handle(), where recovery runs. Approval fixture needed author_identity_id for the existing prepared contract. Those were test corrections, not runtime regressions. Full-suite, physical devices and production checks were not run.

## Closeout and release limits

Outcome: Additive POST /api/create_signed_post accepts a separately signed bootstrap plus an ordinary signed post atomically, reuses existing key/anchor state, and supports exact committed-record retries. Docs and runnable example are complete. frm.py remains paused. Branch feature/single-request-posting; planning 2bea587fb; S1 efa0734f1, S2 4191c636f, S3 35c23c142, S4 03f538a4c, S5 e230c1686, S6 74ead35cf, S7 c9856e9c1; S8 is this closeout commit. No merge, push or deployment.

Performance limitation: Every acceptance/replay currently uses the existing full read-model rebuild for correctness of combined registration/post counts. Small local fixture tests complete quickly, but no production-sized latency benchmark was performed. This is not a claim of lower end-to-end latency on large instances despite fewer requests.

Existing archive limitation: ImageBackup exports repository/records, while scripts/import_repository_archive.php (unchanged from baseline) expects records/ or local_repository/records/. Direct exported-archive CLI import failed with that existing layout error. Extracting the snapshot and repacking its unchanged records/ subtree allowed actual CLI import; post/signature bytes and bootstrap pointers survived. Automated direct layout interoperability remains a follow-up, not a delivered fix. The snapshot reader/rebuild path works without repacking.

Release checks: Live ZenMemes discovery/download/read and any authorized production write remain pending after deployment. Full suite, production scale, physical devices and maintainer comfortable-fit/acceptance are pending. Local generated-key workspace recovery is verified; this is not deployed user acceptance. Upstream proxy/missing-PHP errors are outside the endpoint's JSON guarantee.

Dependency/schema delta: zero external additions/upgrades/copied components; no manifest/lock changes, no read-model schema changes and no canonical record migration. Existing PHP, Python standard library, shell, curl, GPG, Git and already installed Playwright were used. No changes to todo.txt. Prior untracked request and paused-cycle plans remain uncommitted; only this cycle's ledger records are included in closeout.

Study closeout: 2026-10-30 UTC, v2/B, original baseline/R1/P2, Codex single exposed rater. Entry/projected total 1.05 retained with no in-cycle prospective revision; separate retrospective scope assessment 1.10. Original S1–S8 estimates retained above; retrospectives 0.16/0.12/0.20/0.18/0.10/0.10/0.08/0.22 are not summed. Eight stages complete technically. No measured labor, independent rating or accepted-outcome increment; maintainer fit and external release acceptance pending. Pristine intake remains missing.

*— Codex · 2026-10-30 UTC*
