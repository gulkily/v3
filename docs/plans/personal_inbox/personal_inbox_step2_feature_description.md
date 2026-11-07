> **Feature plan:** [Step 1](./personal_inbox_step1_solution_assessment.md) · [Step 2](./personal_inbox_step2_feature_description.md) · [Step 3](./personal_inbox_step3_development_plan.md) · [Step 4](./personal_inbox_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/personal_inbox`; [host ledger](../effort_estimation_trial.md#cycle-v3personal_inbox). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.80** (revised; Step 1 0.70, first Step 2 draft 0.75)

## Revised Direction

Maintainer instruction (2026-11-07): "it should have a feature flag like most other features." Inbox gets its own site flag, which operators can switch on the Feature flags page. With the flag off, the site behaves exactly as it does today.

## Problem

A member cannot tell what is new since their last visit. Nothing tells them that someone replied to them, and only direct messages track unread state.

Concepts touched: [recent conversation](../../concepts/recent_conversation.md) (changed: its "no personal read tracking" line now points to Inbox); [navigation slots](../../concepts/navigation_slots.md) (changed: Inbox becomes a destination); [viewer session](../../concepts/viewer_session.md) and [private message history](../../concepts/private_message_history.md) (used unchanged). New inventory entry: **caught-up mark**, the point up to which a member has seen one Inbox section. It moves only forward and only when the member asks.

Related feedback: PF-20261030-F05 (since last visit; visiting must not mark items read). PF-20261030-F03 and PF-20261104-F01 stay separate.

## User stories

- As a member, I want one page that shows unread messages, new replies to me and other new posts, in that order, so that I see the most personal things first.
- As a member, I want a count on the Inbox link, so that I notice a reply or message without opening Inbox.
- As a member who uses a phone and a laptop, I want both devices to agree on what I have caught up on, so that I never re-read the same replies.
- As a member, I want to choose when a section is caught up, so that glancing at Inbox does not hide things I have not read.

## Core requirements

- Inbox shows three sections in order. **Messages** lists conversations with unread messages, using the existing DM unread state. **Replies** lists posts whose parent was written by any of my approved keys, excluding my own posts. **New posts** lists every other visible post newer than my mark.
- Each Replies and New posts section has a **Caught up** button. It moves that section's mark to the newest item shown on the page, never to items that arrived later. Opening Inbox moves no mark. Messages keep their existing per-conversation read behavior.
- Marks belong to the username, like DM unread, so every approved key and device under one username shares them. A new member's marks start at the moment Inbox first loads for them, so the board's history is not "new".
- The Inbox link shows one count: unread conversations plus unread replies. New posts are not counted, because the board would almost never show zero.
- Inbox is a live, no-store page for signed-in approved members, with the same access as Messages. Caught up works without JavaScript; only the link count needs it.
- A new site flag, `FORUM_INBOX_ENABLED` ("Inbox"), controls the feature. It defaults to off and sits in the Forum experience group. Off means no Inbox link, an Inbox address that answers "not found", and Messages back in its usual bar slot. Turning it off keeps stored marks, so turning it back on resumes where each member left off. The navigation changes as soon as the flag changes, as Messages and Gallery already do.
- Inbox does not require private messages. With `FORUM_PRIVATE_MESSAGES_ENABLED` off, Inbox shows only Replies and New posts, and the count includes only replies.

## Delivery scope

- Work type: application change.
- In scope: the `FORUM_INBOX_ENABLED` flag and its registry, order and dependency checks, the Inbox page and route, a private mark store beside the DM read state, a read-model index for replies by parent, the Inbox nav item and its count, the Inbox icon in Word97, Chicago and Midnight, tests, and the concept, schema and architecture docs. Ledger bookkeeping for this cycle.
- Navigation, with the flag on: Inbox takes the Messages slot on the bar. Messages moves into the You menu and stays one tap away from Inbox.
- Out of scope: follow and unfollow, @mentions, email or push, keyless readers, and scrolling to the first new reply inside a thread. These remain Enhancement Requests.

## Completion boundary

- Normal entry: the Inbox link on the bar or phone tab bar.
- End-to-end outcome: a reply to my post raises the Inbox count on my other device after its next refresh. It appears under Replies, links to the reply itself, and disappears after Caught up on both devices.
- Recovery: a failed Caught up keeps the items and shows a retry, never a false success. A signed-out viewer sees the existing sign-in prompt. A missing or retracted item is skipped without breaking the page. If the DM store is unavailable, the other two sections still show.
- Recovery: turning the flag off mid-visit shows "not found" on the next Inbox load and restores Messages to the bar; turning it on again restores each member's marks.
- Release condition: smoke and changed tests pass, including the flag registry checks, a read-model rebuild from the previous schema version passes, and the full journey is checked by hand on two browsers with JavaScript on and off.

## Risks

- **Backdated posts.** A post's time comes from its signed record, so it can be older than a mark. Impact: such a reply never appears. Earliest validation: check whether the read model keeps any server-side arrival order that survives rebuild. Mitigation: if none survives, compare marks against the arrival order and accept that a rebuild resets marks to the newest item.
- **Crowded navigation.** Moving Messages into the You menu changes a familiar place, and the bar already feels crowded. Impact: members may not find Messages. Earliest validation: maintainer review of this placement before Step 3. Mitigation: keep Messages as Inbox's first section and in the You menu.
- **Reply lookup cost.** The read model has no index on a post's parent. Impact: Replies would scan every post. Earliest validation: count posts in production snapshots. Mitigation: add the index with the required schema version bump and rebuild test.

## Shared component inventory

- `src/ForumRewrite/Messaging/PrivateMessageReadState.php`: model for the new mark store. It keeps one baseline and forward-only marks per username in private SQLite. Reuse its pattern; it stays unchanged, because its marks are per conversation.
- `PrivateMessageMailboxService::unreadState()` and `/api/private_messages/unread`: reuse unchanged for the Messages section and count.
- `public/assets/private_message_unread.js`: extend so the Inbox link shows the combined count, keeping its refresh triggers. Mirrors in `nav.php` and `app_header.php` follow the same pattern.
- `templates/partials/private_message_conversation_row.php`: reuse for Messages rows.
- `ApprovedUserKeyResolver`: reuse to find every approved key of the viewer's username for Replies.
- `RecentPostRepository` and the Recent row template: inspect for reuse as Replies and New posts rows.
- `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php`: extend with the flag, appended to the Forum experience group as CONTRIBUTING requires. The Gallery flag is the model for a flag that adds a destination.
- `GET /api/feature_flags/navigation` and `public/assets/feature_flags.js`: reuse unchanged, so the nav updates in place when the flag changes.
- `TemplateRenderer::navItems()`, `templates/partials/nav.php`, `public/assets/theme-nav-icons.css`: extend with the Inbox item and icon.
- New: an Inbox controller and page, and the mark store. No existing page combines personal sections.

## Simple user flow

1. Alice replies to Bob's post.
2. Bob's laptop shows a count of 1 on Inbox after his next page load.
3. Bob opens Inbox and sees Alice's reply under Replies, above any new posts.
4. Bob opens the reply, comes back and selects Caught up under Replies.
5. Bob's phone shows no count and no Replies on its next refresh.

## Success criteria

- A reply to any of a member's approved keys appears under Replies, and their own replies never do.
- The Inbox count equals unread conversations plus unread replies, on every device of that username after refresh.
- Opening Inbox changes no mark; Caught up hides exactly the items it showed.
- A reply that arrives while Inbox is open stays unread after Caught up.
- Inbox lists items for a member with no JavaScript, and Caught up works there.
- With the flag off, every page renders the same navigation as before this cycle, and `/inbox` answers "not found".

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
*— Revised by Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-07T12:01:03Z, `railspike-effort` / `v3/personal_inbox`, Step 2 handoff. Step 1 Option B explicitly approved. Scope v1 unchanged. Starting revision `f343b6269a3b786e4ebe1ab383096f2109461933`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Projected total 0.70 → **0.75**, from new understanding, not changed scope: replies need a new read-model index with a schema bump and rebuild test, and Messages moves into the You menu. No implementation, measured labor or accepted outcome.

Revised 2026-11-07T12:02:51Z, same `railspike-effort` / `v3/personal_inbox`, Step 2 revision. Scope v2: v1 plus a site flag, `FORUM_INBOX_ENABLED`, default off, by maintainer instruction. Same starting revision, R1, P2 and single exposed Claude rater. Projected total 0.75 → **0.80**, reason changed scope: flag registration and its checks, navigation for both flag states, and an Inbox that works without private messages. No implementation, measured labor or accepted outcome.

## Enhancement Requests

Carried from Step 1, all not approved and backlog only: follow and unfollow threads, @mentions, email digest or web push. Scrolling to the first new reply inside a thread (PF-20261104-F01) is out of this cycle.
