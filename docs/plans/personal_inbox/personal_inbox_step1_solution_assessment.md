> **Feature plan:** [Step 1](./personal_inbox_step1_solution_assessment.md) · [Step 2](./personal_inbox_step2_feature_description.md) · [Step 3](./personal_inbox_step3_development_plan.md) · [Step 4](./personal_inbox_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/personal_inbox`; [host ledger](../effort_estimation_trial.md#cycle-v3personal_inbox). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.70

## Original Query

As a user, I want somewhere I can easily find new content I have not read yet, and especially replies to things I've posted, and especially DMs.

## Understood Intent

The request ranks three kinds of unseen content: direct messages first, then replies to the reader's own posts, then everything else. The reader wants one place that shows all three in that order.

Today only direct messages track unread state. `PrivateMessageReadState` keeps a shared server cursor per username, and the Messages link shows a count. Nothing finds replies to a reader's posts. Nothing remembers what a reader has already seen on the board. The read model already links each reply to its parent post and each post to its author, so the server can find replies without new records.

[v3 feature priorities](../../product/v3_feature_priorities.md) ranks "personal notifications and unread state" first. Its smallest release is an inbox for replies and message arrival, a last-read position and an unread count.

Problem: A member cannot tell what is new since their last visit, and nothing tells them someone replied to them.

Concepts touched: [recent conversation](../../concepts/recent_conversation.md) (changed: it says "no personal read tracking"); [viewer session](../../concepts/viewer_session.md) (used unchanged: the server knows a signed-in member through it); [navigation slots](../../concepts/navigation_slots.md) (changed: a new destination); [private message history](../../concepts/private_message_history.md) (used unchanged: Inbox links to existing conversations). Candidate new concept: **caught-up mark**, the point up to which a reader has seen a list.

Related feedback: PF-20261030-F05 (since last visit; merely visiting must not mark items read), PF-20261030-F03 (exact-reply links), PF-20261104-F01 (reload shows new content).

## Shared by every option

- One new page, Inbox, lists three sections in the requested order: unread direct messages, replies to my posts, then other new posts.
- The messages section reuses the existing DM unread state and rows. It adds no new message storage.
- A reply to my post is a post whose parent I wrote. Step 2 decides whether replies inside threads I started also count.
- The reader marks a section caught up with one button. Opening Inbox alone marks nothing, as PF-20261030-F05 asks.
- The Inbox link carries one count, reusing the existing Messages badge pattern. Server HTML stays the same for every viewer.

## Option A - caught-up marks in the browser

**Estimate: 0.45**
Main driver: a client-filled page and a new "posts since" read endpoint. Uncertainty: low.

The browser stores one timestamp per section in local storage. Inbox asks the server for public posts newer than each mark and for replies to the reader's key. Keyless readers get the "new posts" section too.

- Pro: no new private storage; works for keyless readers.
- Pro: smallest change and easy to inspect.
- Con: each device keeps its own marks, so a phone shows replies already read on a laptop.
- Con: clearing browser storage loses the marks.
- Con: behaves differently from DM unread, which already works across devices.

## Option B - caught-up marks on the server

**Estimate: 0.70**
Main driver: a private store and authenticated endpoints, modeled on `PrivateMessageReadState`. Uncertainty: medium, in how marks combine across keys of one username.

The server keeps one mark per section per username in private SQLite, next to the DM read state. Inbox requires sign-in. Marks only move forward, as DM marks do.

- Pro: every device agrees, like DM unread today.
- Pro: one model for messages, replies and new posts.
- Con: keyless readers get no Inbox.
- Con: new private storage to back up, migrate and test.

## Option C - server marks for members, browser marks for others

**Estimate: 0.85**
Main driver: Option B plus a second storage path behind the same page. Uncertainty: medium.

Option B for signed-in members. Keyless readers see only "new posts", with the mark kept in their browser as in Option A.

- Pro: everything in Option B, and keyless readers also benefit.
- Con: two storage paths to keep consistent and test.
- Con: a reader who signs in later starts from a fresh server mark.

## Recommendation

Choose Option B. The request centers on replies and DMs, and both belong to a signed-in member. Cross-device agreement matters more than reach here, and DM unread already set that expectation. Keyless readers keep Recent, and Option C's browser path can follow later if they ask for it.

First vertical slice: Inbox with the existing DM section and a "replies to my posts" section, a server mark for replies, and a combined count on the link. The "other new posts" section follows in a second slice, using the same store.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-07T11:11:45Z, `railspike-effort` / `v3/personal_inbox`, intake and Step 1 handoff. Scope v1: Option B as recommended above. Starting revision `f343b6269a3b786e4ebe1ab383096f2109461933`. Reference R1, protocol P2; frozen R1 hashes not re-verified for this checkpoint. Single exposed Claude rater. Before rating, a read-only search mapped DM unread state, the read model's reply links, sessions, navigation and related plans and feedback. Pristine intake is missing. Intake and Step 1 projected total **0.70**. Option estimates 0.45, 0.70 and 0.85 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

## Enhancement Requests

- Follow and unfollow threads, from the feature priorities. Disposition: separate follow-up cycle.
- @mentions as a fourth Inbox source. Disposition: separate follow-up cycle.
- Email digest or web push. Disposition: separate follow-up cycle, after Inbox.
- Scroll to the first new reply inside a thread (PF-20261104-F01). Disposition: Step 2 decides whether Inbox links do this.
