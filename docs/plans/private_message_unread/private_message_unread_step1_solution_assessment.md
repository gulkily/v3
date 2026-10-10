# Private message unread state Step 1 solution assessment

> **Feature plan:** [Step 1](./private_message_unread_step1_solution_assessment.md) · [Step 2](./private_message_unread_step2_feature_description.md) · [Step 3](./private_message_unread_step3_development_plan.md) · [Step 4](./private_message_unread_step4_implementation_summary.md)

## Original Query

Looks good. Please merge to main and start the next one.

## Understood Intent

Cycles 2 and 3 are merged into local `main`. Assess Cycle 4 of the [master checklist](../private_messaging_usability_master_checklist.md): unread indicators and a Messages navigation count. Decryption-widget and historical-key follow-ups remain deferred.

## Problem Statement

The [private message store](../../../src/ForumRewrite/Messaging/PrivateMessageStore.php) has no read-state fields; reliable unread indicators require agreed semantics and persistence.

## Option A Browser local seen state

Remember each conversation's seen position in the current browser and derive indicators from subsequent incoming messages.

- Pros: avoids server schema changes; small initial scope.
- Cons: cleared storage resets state; devices disagree; browser identity changes require isolation.

## Option B Shared conversation seen state

Persist a private seen position for each username and counterpart; a conversation needs attention when it contains a later incoming message.

- Pros: matches mailbox ownership; consistent across identities/devices; compact state; independent of decryption.
- Cons: needs private persistence and rollout handling; opening a window acknowledges earlier history without proving it was read.

## Option C Track individual verified reads

Track received messages individually after their plaintext is verified and presented, rather than acknowledging a conversation window.

- Pros: finer-grained message counts; avoids acknowledging unseen older messages.
- Cons: more synchronization; unavailable keys can leave permanent badges; verification still cannot prove human reading.

## Recommendation

Choose **Option B**, with these proposed semantics:

- Count **conversations**, not messages, in navigation; each affected row gets an accessible unread indicator. Own sends neither create unread state nor clear incoming unread state.
- Share state across the username's approved identities and devices. Acknowledgment on one device clears that position for the others; expose no sender-facing read receipts.
- Acknowledge the opened snapshot's received-message boundary only after its read attempts settle and its latest-message region is presented in a visible, focused conversation. List previews, background tabs, and older-history loading do not acknowledge it. Displayed failures count as seen, never as verified. Earlier messages through that boundary are also acknowledged, even if not loaded.
- Keep acknowledgments monotonic and bounded to the window actually opened. Concurrent arrivals remain unread, including backdated arrivals; failed updates retain unread state and offer retry without disturbing drafts or history.
- Treat history stored when tracking is enabled as seen. Establish that baseline once, not on each device's first visit; subsequent incoming messages count as unread.
- Persist metadata privately, never plaintext or verification results. Existing fields cannot represent viewer progress, justifying new persistence; settle minimal schema, upgrade, and rollback in planning.
- Refresh on navigation, return to a visible focused page, and successful acknowledgment. Cross-device changes appear on refresh, not instantly. Refresh failure must not imply zero unread. Preserve static/offline exclusion.

The vertical slice is Messages badge → unread row → open conversation → acknowledge its boundary → consistent list/count after return, including recovery and concurrent arrivals. Exclude live delivery/polling, manual mark-unread, key recovery, and warning redesign. Keep within one day or eight stages; split before implementation if needed.
