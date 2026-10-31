> **Feature plan:** [Step 1](./message_sync_activity_step1_solution_assessment.md) · [Step 2](./message_sync_activity_step2_feature_description.md) · [Step 3](./message_sync_activity_step3_development_plan.md) · [Step 4](./message_sync_activity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/message_sync_activity — [ledger](../effort_estimation_trial.md#cycle-v3message_sync_activity)
Extended estimates: enabled
Status: Revised Step 3 approved; Stages 1–7 complete; Stage 8 closeout pending

**Cycle estimate: 0.95**

Entry: 2026-10-30 UTC; projection 0.95 reaffirmed, scope v1/A, baseline 4b14e622f, R1/P2, Codex single exposed rater; prior verified references retained. Planning b58d281c7 precedes this summary. Original S1–S8 estimates 0.12/0.10/0.15/0.17/0.13/0.13/0.15/0.06, scopes/uncertainties preserved in Step 3; non-additive. No measured labor. Retirement and cap remain separate unapproved proposals.

*— Codex · 2026-10-30 UTC*

## Stage 1 - Baseline and admission

**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Changes: Added rolling private admission before history processing, covering GET/POST legacy and combined routes with 429/Retry-After. One bounded event list per account contains at most 64 admitted attempts, independent of session renewal; expired events are discarded on use and rejected requests cannot extend lockout. Added 115-key fixture support and preserved the read-only natural-reload diagnostic.
- Verification: Focused history/feature-flag tests 23/23; additional eight-process concurrency test passes both 64/account and 16/single-key aggregate limits. Fake-clock checks cover exact window expiry, independent accounts and repeated rejection. Existing sync-store outage/isolation tests pass; git diff --check passes.
- Notes: Private admission table is additive; no public read-model or dependency change. Browser pacing/429 recovery arrives in Stage 6; no new release claim until the full slice is complete. Original scope and estimate unchanged.

*— Codex · 2026-10-30 UTC*

## Stage 2 - Private foreground activity

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Changes: Add private coalesced activity keyed by authenticated account/fingerprint, ordered by server time. Record on approved sign-in and explicit foreground unread refresh; no extra heartbeat. Background unread refresh, sync and acknowledgments do not promote activity. A sync-store outage cannot block sign-in or unread status, and disabled messaging does not touch sync storage.
- Verification: 33/33 focused history/feature-flag/mailbox tests pass; shared-page gate test checks activity on visible entry but not background refresh. Tests cover five-second coalescing, account isolation and no activity promotion by combined discovery. Static shared-page identity checks remain passing.
- Notes: Prior stage 68167edd2; additive private activity table/index, no canonical record or public read-model changes. No estimate revision. Cross-account sender activity is not exposed.

*— Codex · 2026-10-30 UTC*

## Stage 3 - Bounded identity and payload retrieval: compatibility gate

**Original estimate: 0.15**
**Retrospective stage work: unavailable — stage incomplete**

- Finding: Submission identity does not prove the original encrypted signer. The proposed exact-key shortcut excluded another currently approved signer. A bounded all-key fallback returned a permanent-data failure as transient unavailability for large accounts. Neither behavior is acceptable.
- Verification: The new submission/signature-identity regression fails against the experiment and passes with the committed implementation. The original 21 focused tests (including two proposed schema checks) passed the experiment, demonstrating why that evidence alone was insufficient. After withdrawal, all 20 history-service tests pass. Existing real-key crypto verification confirms correct-signer success and wrong-signer rejection. See the compatibility proposal for the boundary and remaining verification.
- Disposition: Removed the experimental service/schema/startup-test changes; retained the new compatibility regression. Stages 1–2 remain committed (68167edd2, f2c3a8a98). Stage 3 is not complete; Stages 4–8 have not started. No release, idle-traffic, activity-priority, browser-continuation or full recovery claim.
- Proposed decision: [Reviewable Step 2 revision](./message_sync_activity_compatibility_revision.md) keeps all ceilings, introduces bounded modern verification continuation and explicitly requests approval for upgrade-required errors on legacy requests that cannot fit. This is a proposed compatibility change, not an approved implementation refinement.
- Study: Original estimates retained; no stage/cycle completion assessment or accepted outcome. Dependency delta zero. No merge, push, deployment or full-suite verification.

*— Codex · 2026-10-30 UTC*

## Planning revision — preserve sending

Date: 2026-10-30 UTC
Direction: User rejects blocking sends and selects recorded-key-first recovery with bounded fallback. Revised Step 2 records this requirement; no sending change, legacy upgrade requirement or dependency is authorized. The earlier upgrade-required proposal is superseded as a recommendation, with its history retained.
State: Completed work and estimates unchanged. Legacy compatibility under the resource ceilings remains a Step 3 feasibility check; the revised implementation plan has not been written or approved. No new application changes or runtime acceptance.
Verification: Documentation diff and local relative-link targets checked; runtime/UI/deployment checks inapplicable to this planning-only revision. Prior runtime evidence remains separately recorded above.

*— Codex · 2026-10-30 UTC*

2026-10-30 UTC — Revised Step 2 approved. Revised Step 3 specifies the recorded-key-first path, bounded verification continuation and legacy checkpoint/timeout acceptance gate. No application work resumed; completed stages and prior failed experiment remain preserved.

## Stage 3 - Bounded retrieval and compatible signer fallback

**Original estimate: 0.15**
**Retrospective stage work: 0.20**

- Changes: Replaced history service full-account public-key materialization with indexed exact/next-key retrieval and a shared 64-key/160-decision request budget. Verification tries the submitting-key hint first and includes bounded alternatives; remaining subsets use private non-secret page state and tokens echoed through existing checkpoints. Timeout checkpoints without tokens retain pending subsets. Progress cannot create access receipts. Transfer and work payloads paginate by encoded size. Updated browser separates successful decryption from signature failure, avoiding unrelated candidate lookups. Sending unchanged.
- Verification: 31/31 focused history, crypto and schema tests pass; repeated schema check invokes the actual ./v3 rebuild on an isolated fixture and normal application startup upgrades version 38 to 39 before use. Real 115-key fixture recovers an original signed by the final key using the frozen unchanged legacy coordinator and updated coordinator; each response stays under 4 MiB/64 keys/160 decisions. Legacy uses 16 work pages and 15 candidate lookups; updated coordinator uses zero candidate lookups for that case. Tests cover rewritten timeout checkpoints, duplicate old acknowledgments, maximum-sized material, eligibility, revocation, restore, original signature rejection and both existing crypto bundles.
- Compatibility: No legacy upgrade error or send-time key-match check. The legacy fixture uses later visits after its existing eight-bundle limit; automatic uninterrupted continuation is Stage 6 work, not proven by this compatibility harness. Source fixture retains the project-owned coordinator from 4b14e622f, not a third-party dependency. Broader natural-flow acceptance remains Stage 7.
- Storage: Additive private verification table holds only current-page identifiers/cursors/tokens, at most ten entries per account/key/mode. Read-model version 39 adds the approved-key traversal index; schema specification/startup regression updated. No private keys, extracted session keys or plaintext persisted.
- History: Revised planning commit e25676b2e precedes resumed implementation. Earlier withdrawn experiment and failed regression are retained above. Original stage estimate preserved; retrospective reflects extra legacy adapter/testing, not measured labor. Dependency delta zero. No full-suite, production or final traffic acceptance.

*— Codex · 2026-10-30 UTC*

## Stage 4 - Activity ordering and quiet completion

**Original estimate: 0.17**
**Retrospective stage work: 0.20**

- Changes: Store separate destination positions and bounded scheduler metadata. Prefer recently foreground-active same-account keys for three selections, with stable-order fairness on the fourth; finish pending verification subsets before switching destinations. Completed account/sender passes return a small idle response without original/key discovery. Submission/verification progress never creates readability evidence.
- Invalidation: Private message mutation tokens, public membership-key tokens, own incoming transfer changes and deleted donor contributions reopen relevant passes. Epoch tokens distinguish newly initialized/rebuilt private stores; tokens survive reconnects. Recipient activity and receipts never enter sender scheduling. Ordinary profile post-count changes and foreground activity do not reopen settled crypto work.
- Verification: 37/37 focused history/crypto/feature-flag/schema checks pass, plus a dedicated revision privacy/invalidation test. The 115-key service fixture selects the active key first, protects the fourth fair selection, finishes all useful work, then returns one sub-8-KiB response with zero key loads/decisions. New originals reopen it. Tests exposed and fixed pending-verification pinning and sender completion after successful verification/contribution. Both legacy and modern real-key compatibility harnesses remain passing.
- Schema: Version 40 adds derived membership revision tokens/triggers; isolated ./v3 rebuild and normal startup from version 39 pass. Message and sync storage additions are private, additive, and contain no decrypted secrets. Existing encrypted history/send acceptance unchanged. Source metadata lookup and per-destination scheduling replace full-account key materialization; first enumeration remains bounded necessary work.
- Limitations: This establishes server scheduling/quietness; browser turn deduplication, automatic continuation and natural-flow acceptance remain Stages 5–7. No production replay, full suite, dependency change, merge or push. Original estimate retained; retrospective reflects revision and scheduler interaction tests, not measured labor.

*— Codex · 2026-10-30 UTC*

## Stage 5 - Deduplicate recovery and receipts

**Original estimate: 0.13**
**Retrospective stage work: 0.13**

- Changes: Share extracted/verified originals across destinations in one visible turn (ten entries, 64 KiB retained key-material encoding limit); clear transient results on turn completion/cancellation and identity/visibility boundaries. Separate missing decryption from failed signer subsets. Background candidate processing streams past bad donors and retains only a usable candidate. Server discovery supplies candidate availability and a revision token; absent candidates avoid lookup requests, and revisions invalidate reused evidence. Missing decryption can finish its verification scan without an access receipt.
- Receipts: Coalesce foreground confirmations for 50 ms into batches of ten; suppress already confirmed or in-flight confirmations with bounded non-secret sets. Work acknowledgments reuse server-validated existing receipts instead of writing them repeatedly. No receipt is inferred from upload or a failed signature. Lost acknowledgments remain retryable.
- Verification: 32/32 focused service/crypto tests pass. Coordinator tests assert exactly two extractions/verifications across repeated two-original destination work and two first-time receipts; reader test checks ten foreground confirmations coalesce into one request. Real 115-key mismatch recovery retains zero modern candidate lookups. Missing-access scan test confirms no fabricated receipt; original-binding, invalid donor, lost receipt, unsigned content, revocation and restore tests pass.
- Refinement: Concurrent tests exposed unnecessary repeated schema DDL on existing history stores under load. Constructor now checks the final installed-schema index before attempting DDL; admission and concurrent-message responsiveness tests pass afterward. New/old-schema initialization retains the additive path.
- Limits: Secret caches are in memory only; this stage does not yet establish the 16-attempt/10-original turn ceiling or natural automatic continuation (Stage 6). No dependencies, UI copy, send restriction, production replay, merge or push. Estimate unchanged; no measured labor.

*— Codex · 2026-10-30 UTC*

## Stage 6 - Bounded automatic continuation

**Original estimate: 0.13**
**Retrospective stage work: 0.16**

- Changes: Count retries in the sixteen-attempt shared turn budget; admit at most ten distinct originals, retain the three-request concurrency gate, stop new work at ten seconds and cancel network work at fifteen seconds. Yield at least one second before automatic continuation, honor 429 Retry-After, and preserve non-secret candidate-page positions across turns. Stop on completed/no-progress passes and cancel timers/secrets when hidden, navigating, disabled or changing identity. Web Locks coalesce tabs where supported; server admission is the fallback when unavailable.
- Progress: Partial work does not advance a full checkpoint. Server verifier state is pruned to the work actually offered, preventing already covered/contributed rows from pinning continuation. A bounded non-secret completed-checkpoint set detects repeated unchanged work. A pending field in the existing coordinator state supports verification of timer completion without new UI.
- Verification: Ten focused crypto/scheduling/server-progress checks pass, plus the final continuation harness after pending-state wiring. A single initial trigger processes 25 originals in three yielded turns; all turns meet ten-original/sixteen-attempt limits. Tests cover counted transient retries, exact three-second Retry-After, continuation after fifteen invalid candidate pages, idle/no-progress timer stop, hidden cancellation and another tab holding the lock. Existing real-key 115-key compatibility, canonical reader, three-request disable race and interrupted sender tests pass.
- Limits: These are browser/service harness results; natural real-page scale acceptance is Stage 7. A broader run passed 32/33 with a transient concurrent-writer test failure under shared-machine load; that test subsequently passed standalone. No claim of a clean full suite or production acceptance. Sending unchanged; dependency delta zero. Original estimate retained; retrospective reflects candidate-cursor and cancellation integration, not measured labor.

*— Codex · 2026-10-30 UTC*

## Stage 7 - User-flow and scale acceptance

**Original estimate: 0.15**
**Revised stage-work estimate: 0.25**
**Retrospective stage work: 0.30**

- Resumption: User requested continued implementation on 2026-10-31. Same approved v2/A scope, original baseline, R1/P2, extended estimates and debug enabled; single exposed Codex rater. All three frozen R1 hashes reverified. Whole-cycle projection 0.95 reaffirmed independently. Stage revision reflects discovered foreground/background integration defects and natural-flow verification, not additional feature scope or measured labor.
- Corrections under verification: Skip already covered/contributed work before loading verifier keys; authorize each shared candidate bundle item; reuse bounded transient candidates and recovered originals in readers/previews; deduplicate concurrent sender-key lookups. Retry early reads once the viewer becomes known. Foreground recovery waits for the background turn and honors temporary throttling; subsequent background turns yield to pending foreground reads. Corrected the HTTP Retry-After header to use RouteServices’ list-of-header-lines contract. In-place read-model rebuilds include membership revision state in their drop/recreate list; no table/column/index shape change beyond committed schema 40.
- Verification: 41/41 server/storage/API checks and 16/16 reader/crypto/schema checks pass; commands and limits are in [acceptance.txt](./acceptance.txt). Real same-account and sender-assisted 31-original journeys pass after the corrections, including partial donors, draft-preserving reload, restored-device forwarding, paired restore, ordinary sending during sync outage and signed toggles across themes. Direct retained-transfer reading/reload passes. The 115-real-key journey restores 19 mixed originals without forced refresh, then returns one 245-byte idle response; ordinary sending succeeds during confirmed history 429 with a valid Retry-After header. Desktop/phone overflow checks and screenshots reviewed. Legacy/modern signer-mismatch, both crypto bundles, request/response ceilings, fairness, cancellation and receipt/privacy regressions pass.
- Environment and failure history: System boot cleanup deleted active /tmp fixtures; later browser runs use TMPDIR=/home/wsl/.cache/v3-message-sync-verification outside the repository. SQLite worker contention exposed retryable SQLITE_BUSY; concurrency tests now drain all workers and retry only that error up to five attempts while preserving fixed-clock aggregate limits and foreground latency assertions. Rejected admissions no longer rewrite unchanged accounting. Earlier natural-flow failures, malformed HTTP header and diagnostic-client timing assumptions are recorded in acceptance.txt. The broader smoke run found an unrelated missing repositoryArchiveDownloadFilename method already absent at pre-change HEAD. No full-suite or production success claim.
- Notes: Original 0.15 and revised 0.25 preserved; retrospective 0.30 reflects foreground/background scheduling, HTTP and real-flow corrections and verification, not measured labor or added feature scope. Stage 7 includes code, tests and this evidence; Stage 8 remains pending. Sending unchanged; dependency delta zero. Sibling retirement/cap proposals remain separate.

*— Codex · 2026-10-31 UTC*
