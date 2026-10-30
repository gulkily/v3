> **Feature plan:** [Step 1](./private_message_sync_idle_step1_solution_assessment.md) · [Step 2](./private_message_sync_idle_step2_feature_description.md) · [Step 3](./private_message_sync_idle_step3_development_plan.md) · [Step 4](./private_message_sync_idle_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/private_message_sync_idle — [ledger](../effort_estimation_trial.md#cycle-v3private_message_sync_idle), standing P2 enrollment
Extended estimates: enabled
Status: Step 3 approved by user on 2026-10-29; implementation authorized

**Cycle estimate: 0.75**

## Completion Contract

Ordinary browsing uses existing unread status to gate history synchronization: zero dedicated sync calls for accounts without messages, one combined check for a genuinely idle eligible run, necessary bounded follow-ups otherwise. First send/receipt and new devices activate through normal refresh. Interrupted scans, partial donors and failed requests resume safely; foreground reading/retry remains available. Verify actual network counts and recovered readable content, legacy APIs and privacy. Local completion requires focused service/browser evidence and documented limits; production acceptance requires replay after separately authorized deployment. No new UI, dependencies or planned schema changes.

## Key Risks

- **High risk:** Empty pages or suppression of bad contributions can strand messages. Validate multi-page/key scans, partial donors and invalid transfer replacement before integrating the coordinator; preserve continuation and explicit recovery, and distinguish contribution existence from verified readability.
- **High risk:** Sender filtering can expose recipient activity. Establish donor-owned evidence and response-invariance tests before combined discovery; never use recipient coverage to select sender work.
- Stale eligibility or overlapping lifecycle events can start work for the wrong identity or suppress first use. Test refresh races and canceled generations before normal-flow acceptance; bind status to current identity and coalesce triggers.

## Stage 1 — Gate synchronization through unread status

**Estimate: 0.12**

- Goal: Unused accounts browse without dedicated synchronization requests.
- Dependencies: Approved Step 3 and planning commit.
- Expected changes: Add account-history eligibility to existing unread state; publish validated identity-bound eligibility to the sync coordinator, including initial-load ordering and ordinary refresh activation.
- Verification approach: Unused, sent-only, received-only and fresh-device accounts; first send/receipt then refresh; failed/stale unread response and identity switch; existing unread API/browser checks.
- Risks or open questions: Missing status must not become a permanent false gate; test refresh recovery before proceeding.
- Canonical components/API contracts touched: PrivateMessageStore/MailboxService, unread API, private_message_unread.js and history coordinator; additive status field, legacy clients unaffected.
- Estimate details: Original stage work; driver is lifecycle wiring; uncertainty is existing send/status event ordering.

## Stage 2 — Recognize completed sender contributions safely

**Estimate: 0.14**

- Goal: Stop automatically resending completed contributions without recipient-state disclosure.
- Dependencies: Stage 1 committed.
- Expected changes: Reuse source/target/message/digest contribution evidence in the sync store and sender work selection. Preserve replacement/retry for invalid transfers and separate own contribution evidence from recipient acknowledgment.
- Verification approach: Repeat contribution, changed digest, newly approved key, multiple donors and invalid stored ciphertext; vary recipient read/coverage state and assert identical sender discovery.
- Risks or open questions: An uploaded item is not proof of readability. Resolve the existing invalid-donor repair path before enabling suppression; do not mark recipient recovery complete from upload alone.
- Canonical components/API contracts touched: PrivateMessageHistorySyncStore and Service; existing transfer upload and foreground recovery APIs.
- Estimate details: Original stage work; driver is privacy-preserving suppression; uncertainty is bad-contribution repair interaction.

## Stage 3 — Add bounded combined discovery

**Estimate: 0.16**

- Goal: One discovery response covers both recovery modes without redundant discovery or idle acknowledgment.
- Dependencies: Stage 2 committed; privacy and replacement regressions passed.
- Expected changes: Extend work with an explicit combined mode while retaining account/sender contracts. Return bounded work bundles and explicit continuation/completion state; use existing scan positions and distinguish scan progress from access receipts. Empty covered pages may advance only safe discovery progress; work requiring client processing remains retryable.
- Verification approach: Empty and covered pages, multiple targets/recipients, history beyond one budget, interrupted scans, no-work completion, concurrent calls and legacy mode responses. Assert fixed discovery limits, eventual progress and no fabricated receipts.
- Risks or open questions: Bounded scanning cannot equate an empty batch with global idle; resolve cursor advancement and request method semantics before client integration. Avoid unbounded scans or new derived tables.
- Canonical components/API contracts touched: HistorySyncController, Service and Store; planned work(viewer, mode) extension and combined response contract, existing authorization/no-store rules.
- Estimate details: Original stage work; driver is fair continuation; uncertainty is cursor interaction across both modes.

## Stage 4 — Consume one check and follow only necessary work

**Estimate: 0.16**

- Goal: Eligible ordinary browsing follows the combined protocol and stops immediately on genuine idle.
- Dependencies: Stage 3 committed and combined contract verified.
- Expected changes: Replace alternating discovery with combined work processing; send transfers for contributions and acknowledgments only for actual receipts or necessary progress. Preserve request/time limits, coalescing, identity/visibility cancellation, foreground candidates/confirm and supported old-server behavior.
- Verification approach: Scheduling/reader/donor browser tests count requests for idle and active runs; test focus/pageshow overlap, hidden navigation, disabled messaging, network failure, delayed responses and older responses without a retry storm.
- Risks or open questions: Cancellation or rollout mismatch must not lose work or leak identity; validate restart and compatibility before acceptance.
- Canonical components/API contracts touched: private_message_history_sync.js, shared availability and unread lifecycle, existing crypto/reader interfaces unchanged.
- Estimate details: Original stage work; driver is asynchronous lifecycle coordination; uncertainty is legacy scheduling fixture coverage.

## Stage 5 — Prove the browsing and recovery outcomes

**Estimate: 0.16**

- Goal: Verify the full slice through normal browsing and record a production replay procedure.
- Dependencies: Stage 4 committed.
- Expected changes: Extend existing fixtures and browser acceptance for first use, settled history, multiple pages/keys, partial donors and interrupted recovery. Record precise request budgets/counts and focused regression results; correct only in-scope defects.
- Verification approach: Real signed message send/receipt, newly approved-device recovery and readable original content; actual requests show zero sync without history and one on genuinely idle discovery. Explicit continuation is measured separately, never mislabeled idle. Exercise unread/per-message retry on phone and desktop; preserve static first paint and existing themes. Replay reported production traffic after separately authorized deployment or mark production checks pending with unaided retry instructions.
- Risks or open questions: Mocked scheduling alone misses crypto/server integration; require actual service/browser evidence, and distinguish local from production results.
- Canonical components/API contracts touched: Existing PrivateMessageHistorySyncTest/CryptoTest and history/unread browser suites, test fixture and affected messaging flows.
- Estimate details: Original stage work; driver is recovery matrix; uncertainty is existing fixture reuse. Owns cross-stage acceptance work, not duplicated in earlier unit checks.

## Stage 6 — Document contracts and close out

**Estimate: 0.06**

- Goal: Leave durable behavior, compatibility and verification records.
- Dependencies: Stage 5 committed.
- Expected changes: Update affected API/reference docs, add the scoped concept descriptions, verify links against delivered code and complete summary/debug/ledger records. Record dependency/schema delta, test omissions and production status.
- Verification approach: Documentation/implementation consistency, diff and file-scope review, no dependency changes, all prior stage commits and acceptance evidence present.
- Risks or open questions: Do not turn pending production acceptance into a completion claim; preserve that boundary in the handoff.
- Canonical components/API contracts touched: Messaging documentation and proposed concept entries for history recovery, sender assistance, history eligibility and unread status; no unrelated inventory backfill.
- Estimate details: Original stage work; driver is contract accuracy; uncertainty low. Owns shared final documentation; earlier stages own their summary updates and focused checks.

## Execution and study checkpoint

Step 3 handoff: 2026-10-29 UTC; cycle 0.75 reaffirmed, scope v1/A, R1/P2, Codex single exposed rater. Original baseline 02dafa857152adab9e8eab488c947b6c34f988e4 retained; user-requested main update fast-forwarded to e614fbd9b before planning. Upstream messaging code and governing instructions unchanged; ledger append conflict resolved preserving both histories. Prior exact R1 verification retained. Stage estimates are original stage-work judgments, non-additive, excluding cycle planning; no measured labor or accepted outcome.

After Approved Step 3: create feat/private-message-sync-idle, organize this cycle's plans/debug log and repair index/ledger links, commit approved planning first, then create the implementation summary. Commit each stage with its summary before starting the next. Keep all plans uncommitted until then. No merge, push or deployment authorization is inferred from prior-cycle approvals.

*— Codex · 2026-10-29 UTC*
