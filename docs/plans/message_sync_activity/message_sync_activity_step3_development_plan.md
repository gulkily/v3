> **Feature plan:** [Step 1](./message_sync_activity_step1_solution_assessment.md) · [Step 2](./message_sync_activity_step2_feature_description.md) · [Step 3](./message_sync_activity_step3_development_plan.md) · [Step 4](./message_sync_activity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/message_sync_activity — [ledger](../effort_estimation_trial.md#cycle-v3message_sync_activity), standing P2 enrollment
Extended estimates: enabled
Status: Revised Step 3 approved; Stages 1–2 committed; resume Stage 3

**Cycle estimate: 0.95**

## Completion Contract

Sending remains unchanged: no new send rejection, reauthentication requirement, key-match check or outer-signature requirement. Recovery tries the eligible recorded submitting key first, then bounded pages of other currently approved sender keys when needed. The recorded identity is a hint, never proof of the encrypted signer. No legacy upgrade-required response is authorized.

Ordinary visible Messages visits on an existing donor and a recently active new key recover sent and received history at the observed 115-key scale without test-only repeated refresh calls. Recent authenticated foreground activity orders same-account destinations; one in four selections protects older destinations. Once useful work is settled, an eligible refresh is one response of at most 8 KiB, with no repeated originals, candidate queries or receipts; unused accounts remain silent. Missing donors, interruption, new activity and new valid transfers have explicit resume/invalidation paths.

All [Step 2 ceilings](./message_sync_activity_step2_feature_description.md#proposed-hard-limits-and-success-criteria) are binding: 160 discovery decisions/64 public-key records per request, 4 MiB non-idle response, 16 attempts/10 distinct originals/3 concurrent requests per browser turn, 10-second work window/15-second cancellation deadline, at least one-second continuation yield, and server rolling admission of 16 requests/key and 64/account per 10 seconds. Budgets cover legacy endpoints and work hidden behind helpers. No retirement, approval cap, new dependency or public recipient activity. Local completion requires real-browser and server evidence; deployment/user-environment replay is separate and must remain explicitly pending until performed.

## Key Risks

- **High risk:** Submitting identity need not equal the encrypted signer. Verify a real message submitted under A and signed by approved B, including B beyond the first key page. Separate successful decryption from signature verification; a wrong verification key must not initiate another transfer search. Legacy clients can rewrite checkpoints on timeout, so prove resumable fallback before implementing dependent scheduling.

- **High risk:** “Settled” or unavailable caches can suppress later recovery, especially sent history. Establish revision/invalidation and interrupted-progress tests before suppression; never infer readability from upload alone.
- **High risk:** Activity ranking or donor responses can expose another account's behavior. Keep activity private and same-account; vary recipient activity/coverage in invariance tests before sender integration.
- **High risk:** Hard limits can move amplification into key resolution, serialization or retries. Instrument actual records/bytes/requests before redesign; resolve oversized-original/key and legacy-client compatibility before dependent browser work.
- Fairness, multiple tabs and 429 responses can create starvation or loops. Exercise adversarial ordering and fake-clock boundaries before continuous continuation; no background self-promotion or timer after useful work settles.

## Stage 1 — Baseline and server admission

**Estimate: 0.12**

- Goal: Reproduce the reported flood and stop unrestricted history API admission.
- Dependencies: Approved Step 3, feature branch and planning commit.
- Expected changes: Extend isolated fixtures to 115 approved keys, both message directions and partial donors; capture per-endpoint traffic and helper work. Add atomic rolling key/account admission in private sync storage before expensive processing, with bounded accounting retention and Retry-After. Planned store contract: admit(account, source, now), returning allowed/retry delay.
- Verification approach: Parallel independent clients, exact window edges, old routes, rejected retries, storage outage and normal send/read independence; assert 16/key and 64/account ceilings and no bypass through session renewal.
- Risks or open questions: Accounting itself can amplify writes; bound state per authenticated identity/window and reject without doing discovery. Do not claim broad network DoS prevention.
- Canonical components/API contracts touched: HistorySyncController/Store and private database configuration; shared 429/no-store semantics. Additive private bookkeeping, not canonical content.
- Estimate details: Original stage work; driver is atomic admission; uncertainty is concurrent SQLite contention. Owns baseline instrumentation reused later.

## Stage 2 — Private foreground activity

**Estimate: 0.10**

- Goal: A normal visit makes an active same-account key eligible for priority without a new heartbeat.
- Dependencies: Stage 1 committed.
- Expected changes: Record coalesced server-time activity on authenticated sign-in and existing visible-entry/return status flow; background work, timer continuation and receipt processing never refresh priority. Planned contract: recordActivity(viewer, now). Store private activity and bounded scheduling metadata separately from public profiles.
- Verification approach: Shared pages, Messages, sign-in in place, new devices, stale responses, session/account changes and repeated background calls; confirm activity never appears in public HTML/API or cross-account responses.
- Risks or open questions: Server authentication proves the key, not a human's visibility; foreground hints remain untrusted scheduling hints, constrained by admission/coalescing rather than treated as authorization.
- Canonical components/API contracts touched: AuthApiController, unread API/coordinator, member-approval lifecycle, private sync store. Normal unread meaning and first-paint markup unchanged.
- Estimate details: Original stage work; driver is lifecycle wiring; uncertainty is dynamic asset initialization.

## Stage 3 — Bounded identity and payload retrieval

**Estimate: 0.15**

- Goal: Enforce record/byte ceilings without losing original verification or legacy compatibility.
- Dependencies: Stage 2 committed; admission and activity tests pass.
- Expected changes: Replace sync's full-account key loading with bounded identity/approved-target retrieval. Use submitting identity only as the first verification hint; paginate other approved sender keys without assuming a failed signature means missing decryption access. Planned contracts: verificationPage(viewer, messageId, digest, cursor, budget) and acknowledgeVerificationProgress(viewer, checkpoint). Bind progress to account, authenticated key, original/digest, destination and mode; validate membership at use. Additive bounded retrieval reuses history authorization and admission. Deduplicate originals/keys within responses and provide continuation where needed. Apply an explicit budget through discovery, transfer retrieval and serialization, including old endpoints.
- Verification approach: First run a minimal real-key recovery slice with submitting key A, signer B beyond the first page, missing/revoked hints, invalid signatures and no donor. Exercise unchanged legacy GET/combined clients, timeout checkpoint rewrites, duplicate/lost acknowledgments, interrupted navigation and retry. Then cover 115 and larger key sets, maximum accepted originals/keys and malformed cursors; count helper key loads and actual encoded bytes. Verify supported crypto paths. No access receipt until the original signature succeeds.
- Risks or open questions: Legacy clients have no verification-page request. Test server-held verification position independent of destination/message scan position: present bounded key subsets through existing sender_keys, advance only via validated progress acknowledgments, and do not finish the cycle while subsets remain. Timeout-rewritten checkpoints must not skip pending subsets. This is a candidate adapter, not proven compatibility. Commit Stage 3 only after old and updated clients recover under every ceiling; if infeasible, return with measured failing cases to Step 2, without shipping permanent 503 loops, truncation or upgrade errors.
- Canonical components/API contracts touched: ApprovedUserKeyResolver/ProfileRepository queries used by sync, HistorySyncService/Controller, crypto verification contract. Any required read-model index/schema-dependent change includes version bump, schema docs, prior-version startup regression and rebuild/API verification.
- Estimate details: Original stage work; driver is compatibility under byte/key budgets; uncertainty is legacy progress across failed verification and timeout. Owns the minimal browser verification consumer and compatibility harness; Stage 5 owns shared caches and receipt deduplication.

## Stage 4 — Activity-ordered progress and quiet completion

**Estimate: 0.17**

- Goal: Recently active destinations receive useful work first, while settled history avoids rescanning every key.
- Dependencies: Stage 3 committed; bounded historical retrieval resolved.
- Expected changes: Persist per-destination progress independent of priority order, plus a fairness position. Select recent same-account targets for three turns and an eligible older/unknown destination for the fourth. Add cheap revision-aware completion/unavailable evidence; planned contracts include nextTargets(viewer, budget) and invalidateRelevantWork(change). Message/digest, approved-key membership, transfer availability and restore/reset changes reopen affected work; scan movement and activity alone must not invalidate all completed cryptographic work.
- Verification approach: A new active key behind 114 old keys, continuous competing activity, restart/restore, partial donors, missing retained transfers and membership changes; exactly one small idle response after settlement. Vary recipient activity/read state and assert identical sender-visible scheduling.
- Risks or open questions: Incorrect revisions strand sent history or reopen all work on every request. Validate dependency-specific invalidation and distinguish supplied access from verified readability before browser consumption.
- Canonical components/API contracts touched: Combined/legacy work, private scan/contribution/coverage state, message-store and approval revision sources. No public activity timestamps; sender assistance retains donor-owned evidence only.
- Estimate details: Original stage work; driver is fairness plus invalidation; uncertainty is restore/revision integration. Additive private scheduling state needs upgrade/backup coverage.

## Stage 5 — Deduplicate recovery and receipts

**Estimate: 0.13**

- Goal: Processing the same original for several destinations does not repeat empty requests or cryptography.
- Dependencies: Stage 4 committed.
- Expected changes: Share bounded in-memory original-key extraction, candidate and signature results within a turn; batch or coalesce acknowledgments. Let discovery state indicate when no candidate retrieval is useful, tied to invalidation evidence. Preserve original/identity/digest binding and independent validation of each transfer. Share successful decryption across verification-key pages; retry signature verification with the next bounded page instead of fetching the same transfer again. Keep missing access, unfinished verification and exhausted verification distinct. Bound both entry count and bytes of in-memory results; clear secrets on identity change, hiding/navigation or lifecycle cancellation. Additive modern protocol paths reuse existing services; keep old routes within approved budgets.
- Verification approach: Request counts for repeated targets, invalid donors followed by valid donors, lost upload/ack responses, changed identities and new transfers after a negative result. Assert no persisted plaintext/private keys/session keys and no receipt before verification.
- Risks or open questions: Cache retention can create stale failures or secret accumulation; cap entries/bytes, clear on identity/navigation boundaries and reopen through explicit reader retry or changed evidence.
- Canonical components/API contracts touched: History coordinator/crypto, transfer and acknowledgment APIs, canonical conversation reader/list preview recovery.
- Estimate details: Original stage work; driver is shared async work; uncertainty is foreground/background races.

## Stage 6 — Bounded automatic continuation

**Estimate: 0.13**

- Goal: One ordinary visible visit advances pending work and then becomes quiet.
- Dependencies: Stage 5 committed.
- Expected changes: Replace repeated fresh cycles with resumable turns carrying progress and completion state. Enforce all browser ceilings across candidates, upload, receipts and retries; yield at least one second and honor Retry-After. Coalesce triggers/tabs, cancel hidden/navigation/identity/disabled runs, preserve resume points and stop after no useful work remains. Existing storage/facilities only; server admission remains the backstop when browser coordination is unavailable.
- Verification approach: Fake-clock and real-browser tests for budget exhaustion, missing donors, cancellation, simultaneous tabs, unavailable browser storage, 429 recovery and stale responses. An idle page must not restart itself on a timer. Verification-page progress is unfinished discovery; exhausted pages without a valid signature wait for relevant changes or explicit retry rather than restarting. Verify cancellation clears transient secrets while retaining non-secret resume metadata.
- Risks or open questions: Indefinite continuation recreates the flood; distinguish unfinished bounded discovery from a completed no-progress pass and wait for relevant change/ordinary return after the latter.
- Canonical components/API contracts touched: History coordinator, unread/availability lifecycle, foreground reader retry and private progress contract.
- Estimate details: Original stage work; driver is cancellation and turn accounting; uncertainty is multi-tab fallback behavior.

## Stage 7 — User-flow and scale acceptance

**Estimate: 0.15**

- Goal: Prove both the delayed recovery and continuing post-recovery traffic are fixed.
- Dependencies: Stage 6 committed.
- Expected changes: Complete real signed fixtures and network/resource assertions at 115 keys, with incoming/outgoing history, a recent new key, inactive destinations and partial donors. Capture sanitized before/after evidence; correct only in-scope defects.
- Verification approach: Natural Messages reload/visible return on donor and recipient, without repeated test-only refresh calls; sent and received content readable, recent priority plus older fairness, then 8 KiB/one-request idle checks with no repeated lookup/receipt traffic. Exercise new donor arrival, outages, oversized records, direct/legacy clients, restore, concurrent tabs, phone/desktop and both crypto paths; verify every ceiling from Step 2 and ordinary sending during sync throttling. Include signed A/B mismatch recovery without any change to send acceptance, and confirm no send-time key-match restriction was introduced.
- Risks or open questions: Full recovery for the active key does not mean every historical key is settled. Record finite useful background progress separately from repeated no-progress traffic. Replay the user's local/deployed trace only within authorization; retain pending external acceptance explicitly.
- Canonical components/API contracts touched: Existing history/read-state/feature-flag tests and browser fixtures; no new product UI or diagnostic disclosure.
- Estimate details: Original stage work; driver is realistic scale and recovery matrix; uncertainty is reproduction fidelity. Owns shared end-to-end acceptance; earlier stages own focused tests.

## Stage 8 — Compatibility, operations and closeout

**Estimate: 0.06**

- Goal: Leave a reviewable release with accurate state, limits and recovery instructions.
- Dependencies: Stage 7 committed.
- Expected changes: Update history API/operations docs, touched concept entries, cycle summary/debug and study ledger. Document private state upgrades, bounded cleanup, backup/restore and code/asset rollback retaining encrypted history; verify any read-model migration obligations from Stage 3. Preserve user-reported eventual recovery and prior diagnostic evidence.
- Verification approach: Links/contracts/file-scope review, dependency delta (expected zero), stage commits/evidence, normal startup on existing private stores and rollback/re-upgrade recovery. Record full-suite, physical-device and deployment omissions honestly.
- Risks or open questions: Operational claims must match tested environments; no unperformed production replay or broad DoS protection claim.
- Canonical components/API contracts touched: Existing history rollout guide, private database support, [history concepts](../../concepts/private_message_history.md) and proposed private-key-activity concept; no unrelated inventory backfill.
- Estimate details: Original stage work; driver is lifecycle documentation; uncertainty low. Owns shared closeout records, not repeated stage verification.

## Original execution and study checkpoint (historical)

Step 3 handoff: 2026-10-30 UTC; whole-cycle 0.95 reaffirmed, scope v1/A, baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4, R1/P2 and Codex single exposed rater. Earlier 0.85/0.95 snapshots remain intact; inherited exact R1 verification retained. Eight original stage-work estimates are independent annotations, not additive or measured hours. Recent source inspection confirms full-key resolution and no existing history admission limiter; implementation risk remains concentrated in Stage 3/4 gates. No implementation or runtime acceptance in this step.

After Approved Step 3, create feat/message-sync-activity, organize only this cycle's plans/debug/diagnostic references and index entries, and make the planning-only commit before creating its summary. Keep retirement/cap plans uncommitted and untouched by the selected cycle's commit; ledger changes for those cycles remain unstaged. Include only authorized organization reference fixes in the planning commit; substantive study bookkeeping accompanies a later stage. Every stage must pass its checks and be committed with its summary before the next. Do not advance dependent work through unresolved compatibility/budget risks or silently widen scope. No merge, push or deployment authorization is inferred.

*— Codex · 2026-10-30 UTC*

## Revised execution and study checkpoint — 2026-10-30 UTC

- Approval: Revised Step 2 explicitly approved. This revision preserves its non-blocking send requirement and all limits; no legacy compatibility relaxation, new dependency, retirement or cap.
- State: Stage 1 (68167edd2) and Stage 2 (f2c3a8a98) are complete; preserve them and their summaries. Stage 3 is incomplete; its withdrawn experiment and regression remain documented. Stages 4–8 are pending. Stage identities and original estimates remain unchanged; substantive revisions affect Stages 3, 5, 6 and 7 only.
- Design evidence: Current browser echoes work.checkpoint on the ordinary path, but on a deadline can replace it with target/after_id (or null for sender work). Server-held verification progress must therefore survive that existing behavior. No runtime claim follows from source inspection; the Stage 3 harness is the acceptance gate.
- Estimate: Whole-cycle 0.95 reaffirmed, v2/A, original baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4, R1/P2, single exposed Codex rater. All three frozen R1 hashes reverified. Original stage-work values retained: fallback/compatibility were already part of Stage 3, now specified more precisely. This is not remaining effort, measured labor or a completed outcome.
- Resume after Approved Step 3: Reuse this cycle's feat/message-sync-activity branch; do not create a new cycle or replay completed stages. Commit the revised approved planning artifacts before restarting Stage 3; keep sibling retirement/cap proposals and their ledger changes out. Then update the existing summary and implement each remaining stage sequentially with its tests, summary and stage-scoped commit. Do not amend completed commits. No merge, push or deployment authorization.
- Verification of this planning revision: Documentation scope, relative links and diff checks only. Runtime/UI tests not run because this turn changes no application code. Production replay and final traffic/recovery acceptance remain pending.

*— Codex · 2026-10-30 UTC*
