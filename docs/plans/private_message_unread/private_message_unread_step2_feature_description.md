# Private message unread state Step 2 feature description

> **Feature plan:** [Step 1](./private_message_unread_step1_solution_assessment.md) · [Step 2](./private_message_unread_step2_feature_description.md) · [Step 3](./private_message_unread_step3_development_plan.md) · [Step 4](./private_message_unread_step4_implementation_summary.md)

## Problem

Messages does not identify conversations needing attention. Unread state must work across devices without implying decryption or verification.

## User stories

- As a participant, I want unread indicators so that I can find conversations needing attention.
- As a multi-device user, I want shared seen-state so that acknowledged conversations stay acknowledged.
- As a reader, I want retryable updates so that failures do not hide arrivals or lose drafts.

## Core requirements

- Count unread **conversations** across all list pages, with accessible row indicators and a clear zero state. Own sends neither create unread state nor acknowledge arrivals.
- Share private seen-state across approved identities/devices for the same username. Treat history stored when tracking is enabled as seen using a one-time baseline, never a per-device reset. Store no plaintext or verification outcomes; expose no sender-facing receipts.
- Acknowledge only the opened snapshot's received-message boundary after read attempts settle and its latest-message region appears in a visible, focused conversation. This includes earlier unloaded messages and displayed read failures, without claiming verification. List previews, background tabs, older-history loading, and unavailable conversations do not acknowledge messages.
- Progress cannot move backward or acknowledge later arrivals, including backdated arrivals. Retry uncertain/failed updates safely, preserving history/drafts without falsely confirming success. Reject unauthorized access or updates.
- Refresh on navigation, return to a visible focused page, and confirmed acknowledgment. Row/count values must agree after refresh; other devices converge on their next refresh. Distinguish stale/unavailable state from zero; preserve no-store responses and static/offline exclusion.

## Delivery scope and completion boundary

Work type: **application change** with shared private persistence; existing message fields cannot represent viewer progress. Complete the normal badge → unread row → conversation → acknowledgment → consistent list/count journey, including recovery. Release requires focused regressions, isolated browser evidence, and initialization/rollback preserving messages and drafts. Exclude polling/live delivery, manual mark-unread, key recovery, and warning redesign. Rescope before implementation if exceeding one day or eight stages.

## Risks

- **False clearing:** trace ties, backdated arrivals, restarts, and two-device updates before planning; require bounded, forward-only acknowledgments and test races before UI integration.
- **Misleading acknowledgment:** check hidden tabs, early scrolling, failed reads, and unavailable counterparts first; enforce presentation conditions and distinguish seen from verified.
- **State reset/disclosure:** review identity scope and initialization before planning; test concurrent first use, identity switching, and rollback early. Require one baseline, authentication, and release exclusion.

## Shared component inventory

- Extend `TemplateRenderer`/`partials/nav.php` and the Messages list/controller, row partial, and script; reuse authentication/navigation, pagination, and previews.
- Extend conversation/controller/script; reuse reader completion and snapshots. Preserve message rendering, history recovery, and conversation/profile/aggregate-user composers.
- Extend private-message API/service/store with new state retrieval/acknowledgment because neither exists. Preserve list/conversation contracts, inbox/sent APIs/redirects, retained mailbox rendering, send/key behavior, and static/offline isolation.

## Simple user flow

1. See the Messages count and open an unread conversation from its row.
2. View the current window; acknowledge its boundary or retry a failed update without losing context.
3. Return or refresh on another device; see matching indicators while later arrivals remain unread.

## Success criteria

- Two incoming conversations produce count 2; acknowledging one produces 1 across refreshed devices, including beyond the first list page. Sends/previews do not clear it.
- Ties, concurrent/backdated arrivals, stale updates, hidden tabs, mixed read failures, access loss, and failed/uncertain requests obey the requirements without lost drafts.
- Baseline initializes once; unauthorized access and private static/offline leakage are rejected. Keyboard/mobile indicators remain understandable without color alone; existing send/history journeys pass.
