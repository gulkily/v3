> **Feature plan:** [Step 1](./private_message_sync_idle_step1_solution_assessment.md) · [Step 2](./private_message_sync_idle_step2_feature_description.md) · [Step 3](./private_message_sync_idle_step3_development_plan.md) · [Step 4](./private_message_sync_idle_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/private_message_sync_idle — [ledger](../effort_estimation_trial.md#cycle-v3private_message_sync_idle), standing P2 enrollment
Extended estimates: enabled
Status: Step 2 approved by user on 2026-10-29; Step 3 drafting authorized; implementation not authorized

**Cycle estimate: 0.75**

## Problem and user stories

Ordinary production browsing makes separate private-message recovery checks and empty acknowledgments, including for accounts with no messages. Reduce that traffic without delaying discovery of recoverable history.

- As a member who has never sent or received a message, I want no history-sync requests so ordinary browsing stays quiet.
- As a member with messages, I want one idle check and automatic necessary recovery so older messages remain available across devices.

## Core requirements

- Derive eligibility from account-level sent or received history through the existing unread check. Visiting Messages is neither required nor sufficient. The first send or receipt becomes eligible on the next normal status refresh; stale local state cannot permanently suppress sync, including on a new device.
- For an eligible account, one combined check covers account recovery and sender assistance and returns available work directly. A genuinely idle run ends there: no separate mode check, transfer call or empty acknowledgment. Additional requests must serve returned work, required scan continuation or bounded recovery from failure.
- Keep discovery and processing bounded and fair across history, keys and recipients. Empty or already-covered pages do not prove completion. Interrupted runs, partial donors and newly approved keys must retain a path to eventual recovery without endless repeated completed work.
- Suppress completed sender contributions using the sender's own contribution evidence; never expose recipient read or recovery state. Preserve signature/original-message binding, authorization, browser-held private keys and browser-side plaintext.
- Preserve existing client APIs, foreground message reading and retry, unread meaning and the messaging feature flag. Coalesce overlapping triggers; stop on hidden pages, identity invalidation or disabled messaging, with safe resumption through ordinary supported entry points.

## Delivery scope and shared surfaces

Work type: application change, including focused tests, affected API/reference documentation, concept inventory, cycle/debug records and study ledger. No new UI, dependencies or planned read-model schema changes; no Railspike polling changes.

Reuse/extend the canonical unread API and shared unread coordinator across navigation and conversation lists for eligibility. Extend the history-sync work API and browser coordinator for combined discovery. Reuse transfer/acknowledgment APIs for actual recovery, existing messaging availability/identity handling, and foreground conversation reading/per-message retry. Keep legacy mode requests compatible. These surfaces share the same account eligibility and recovery rules; no parallel navigation indicator or recovery control is needed.

Concepts touched: private-message history recovery and sender assistance (scheduling changed; trust unchanged), account history eligibility (new derived gate), unread status (extended). Inventory entries are missing; add concise descriptions during implementation, grounded in the existing [history service](../../../src/ForumRewrite/Messaging/PrivateMessageHistorySyncService.php), [unread coordinator](../../../public/assets/private_message_unread.js) and [sender privacy contract](../private_message_sender_history_sync/private_message_sender_history_sync_step2_feature_description.md).

## Risks and early validation

- False idle or repeated sender work can strand recovery or erase request savings. Before dependent browser changes, exercise covered pages, multiple keys/recipients and already-supplied contributions; require bounded continuation and donor-owned completion evidence.
- Eligibility races can suppress the first message or cross identities. Validate first send/receipt, new devices and identity changes at the status/coordinator boundary; revalidate through ordinary refresh and discard obsolete responses.
- Combining discovery can reveal recipient activity or make unread checks expensive. Validate response/privacy invariants and bounded discovery independently; keep work discovery out of unread status and recipient coverage out of sender scheduling.

## User flow, completion and success criteria

1. A member browses normally; the existing unread refresh establishes account history eligibility. No history means zero dedicated sync requests.
2. An eligible member gets one combined work check. Settled history produces exactly one dedicated sync request per successful idle run; count excludes the pre-existing unread request.
3. Available work or unfinished discovery produces only necessary bounded follow-ups. Later ordinary visits/focus refreshes resume interrupted work, and Messages retains its existing per-message retry.

Completion boundary: prove those network counts and eventual readable history through normal browsing, first send/receipt, a newly approved device, partial history and interruption/failure recovery. Verify unchanged unread/read behavior and legacy API compatibility with focused service and real-browser checks. Record test data, scan limits and omissions; passing source assertions alone is insufficient.

Known environment: the user observed production browsing. After separately authorized deployment, replay ordinary production browsing for an unused account and a settled-history account, then first-message and recovery flows. On transient failure the member can return/focus or reload; unread failure retains its existing Retry on Messages action, and message recovery retains per-message retry. Success is the expected network trace plus readable recovered content. Local evidence permits a code handoff but does not establish production acceptance; deployment and production evidence remain explicit pending items until performed.

## Study checkpoint and approval

Step 2 handoff: 2026-10-29T20:17:46Z; whole-cycle 0.75 reaffirmed for v1/A from baseline 02dafa857152adab9e8eab488c947b6c34f988e4, R1/P2, Codex single exposed rater. All three frozen R1 source hashes reverified. Requirements refine the approved scope without expansion; earlier estimates/exposure remain in Step 1. Includes planning, implementation, recovery/privacy verification and documentation; not measured labor or an accepted outcome. Await Approved Step 2; planning remains uncommitted.

*— Codex · 2026-10-29 UTC*
