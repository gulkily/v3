> **Feature plan:** [Step 1](./private_message_sync_idle_step1_solution_assessment.md) · [Step 2](./private_message_sync_idle_step2_feature_description.md) · [Step 3](./private_message_sync_idle_step3_development_plan.md) · [Step 4](./private_message_sync_idle_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/private_message_sync_idle — [ledger](../effort_estimation_trial.md#cycle-v3private_message_sync_idle), standing P2 enrollment
Extended estimates: enabled
Status: Step 1 Option A approved by user on 2026-10-29; Step 2 drafting authorized; no implementation approval

**Cycle estimate: 0.75**

## Original Query

“Can we make it into one request maybe?”
“Like, one request and other requests if work actually needs to be done?”
“And also, can we gate it somehow for users who have never used messaging before?”
“Write step 1.”

## Understood Intent and problem

Reduce private-message history synchronization traffic during ordinary production browsing: accounts without sent or received messages should not start synchronization; accounts with history should make one work check per synchronization run and continue only for necessary work. Today the browser separately scans account/sender work and acknowledges even empty batches.

## Option A — History eligibility plus one combined work check

**Estimate: 0.75**

Include account-level history eligibility in the existing unread-status response. Eligible accounts make one combined synchronization check covering their own recovery and sender contributions; a genuinely idle result ends the run without transfer or acknowledgment calls. Return available work in that response, avoiding a redundant discovery request.

- Benefits: Zero dedicated sync requests for unused accounts; one for idle accounts with history; keeps expensive recovery discovery separate from unread status.
- Costs/uncertainty: Must distinguish finished history from an empty page, suppress already-supplied sender contributions using donor-owned evidence, and preserve bounded scans/retries. These recovery and privacy checks drive effort.

## Option B — Include work discovery in unread status

**Estimate: 0.95**

Have the existing unread-status request also discover and return synchronization work. Accounts without history receive no work; additional synchronization calls occur only to process returned work or necessary continuation.

- Benefits: No separate idle synchronization request, even for accounts with history; first-message eligibility and discovery arrive together.
- Costs/uncertainty: Couples unread indicators to multi-key history scanning and recovery failures; requires stricter latency isolation and broader messaging regressions. Discovery frequency may follow unread refresh frequency unnecessarily.

## Recommendation and acceptance boundary

Choose A as a complete slice: ordinary browsing stays quiet, the first sent/received message activates sync automatically, and new devices still recover older messages. Eligibility means history exists for the account, not “Messages was visited” or “this browser has sent a message”; a stale local false value cannot permanently suppress recovery.

Prove zero dedicated sync calls without history, one combined check on an idle run with history, and necessary follow-up requests when work exists. Preserve progress through empty/covered pages, large histories, interrupted visits, newly approved keys and partial donors; an unfinished scan must not be mislabeled idle. Do not trade request savings for an unbounded server scan. Sender scheduling must not expose recipient read/recovery state; use the sender's own contribution history rather than recipient acknowledgments. Private keys and plaintext remain browser-side.

Retain existing APIs for older clients, foreground message reading/retry, unread semantics and the messaging feature flag. Use existing stores and controls; no new UI, dependencies or planned read-model schema changes. Include actual production network replay after separately authorized deployment; source inspection has not verified the current production build. Both user refinements are included in proposed scope v1; the merged Railspike fix is separate.

## Concepts touched

Private-message history recovery and sender assistance: changed scheduling, unchanged encryption/authorization. Messaging history eligibility: derived scheduling gate, not a new membership state. Unread status: extended in A, additionally owns discovery in B. Relevant inventory entries are missing; propose adding these descriptions during implementation, grounded in [history service](../../../src/ForumRewrite/Messaging/PrivateMessageHistorySyncService.php), [browser coordinator](../../../public/assets/private_message_history_sync.js) and [sender privacy contract](../private_message_sender_history_sync/private_message_sender_history_sync_step2_feature_description.md).

## Study snapshots

Scope: v1/A; baseline 02dafa857152adab9e8eab488c947b6c34f988e4; R1/P2; Codex single exposed rater.
Intake: 2026-10-29T20:00:41Z; 0.75 after prior conversational/source inspection; pristine pre-inspection intake unavailable, not reconstructed.
Step 1 handoff: 2026-10-29 UTC; 0.75 reaffirmed. All three frozen host-local R1 hashes verified. Alternatives estimate complete cycles, including planning, recovery, verification, concept/docs and ledger upkeep; not additive or measured labor. Distinct follow-up to existing private-message history/sender synchronization; no accepted outcome or implementation approval.

*— Codex · 2026-10-29 UTC*
