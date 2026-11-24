> **Feature plan:** [Step 1](./push_on_events_step1_solution_assessment.md) · [Step 2](./push_on_events_step2_feature_description.md) · [Step 3](./push_on_events_step3_development_plan.md) · [Step 4](./push_on_events_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/push_on_events`; host ledger [push_on_events.md](../effort_ledger/push_on_events.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.70**

## Problem

A member whose app is closed never learns that someone sent them a DM or replied to their post until they open the site. Push devices can be turned on (cycle 2), but nothing sends to them.

## Approved direction

The maintainer approved Step 1 on 2026-11-24 with Option A: a pending notice is recorded when a post or DM is saved, sent right after the poster's response has gone out, and the task queue sends anything left within a minute. He gave no answers to the three open questions, so this plan carries their assumptions forward, and he can still overrule them:

- Hold window five minutes; at most about 6 notices per member per 10 minutes.
- Agent replies a reader asked for (`reply-agent`) notify the post's author like any reply.
- The host's PHP is detected at run time. Both after-response routes are built.

The guest-reply, read-state and lock-screen answers from Step 1 stand. Guests show as "guest replied" with the first line. Read state is checked just before each send. Hiding who wrote waits for cycle 7.

Concepts touched: [push subscription](../../concepts/push_subscription.md), changed (notices for DMs and replies, the pending list, the burst rule; its "Proposed, not built yet" line shrinks to cycle 5); [internal task queue](../../concepts/internal_task_queue.md), changed (task type `push_notify` added to the list); [caught-up mark](../../concepts/caught_up_mark.md), used unchanged; [ported thread](../../concepts/ported_thread.md) and [thread activity](../../concepts/thread_activity.md), used unchanged. Feedback: no matching item in `docs/feedback/items/`.

## User stories

- As a member with the app closed, I want a notification when someone DMs me, naming who, so that I can answer while it matters.
- As a member, I want a notification when someone replies to my post, with who and how it starts, so that I can decide whether to open it.
- As a member whose posts an agent answers in bulk, I want one grouped notice instead of a string of them, so that I keep notifications on.
- As a member who already read it on my laptop, I want my phone to stay quiet, so that notices mean something new.
- As an operator, I want this behind the existing flag and working without cron, so that a new install still sends.

## Core requirements

- A DM to a member, or a reply whose parent is one of their posts under Unread's Replies rule, reaches every device they turned on. Nothing they wrote themselves, under any of their keys, notifies them.
- A DM notice names the sender and never the content. A reply notice names the replier ("guest" when unsigned) and its first line of plain text, cut at about 100 characters. Each uses one `tag` per conversation or thread.
- The first notice for a tag is sent seconds after the post. Later ones for that tag within five minutes are held and sent as one replacement at the window's end. A member gets at most about 6 notices per 10 minutes; any beyond that are folded into one "N new replies" notice.
- A notice whose DM or reply is already read on the server (DM seen mark, Unread caught-up mark) when its turn comes is dropped. A device whose session is signed out, or that the push service reports gone, is removed and not sent to.
- With `FORUM_PUSH_ENABLED` off, nothing is recorded or sent. A failed or slow send never fails, slows or blocks the post or DM that caused it.

## Delivery scope

- Work type: application change. It covers the push store (pending notices, devices by username), hooks where replies and DMs are accepted, the send pass, the after-response route, a `push_notify` task type and its worker, tests, the push subscription and internal task queue concept entries, the notifications checklist and `docs/reference/agent_api.md` (one line saying agents' replies may notify people).
- Not notified: new threads, ported comments, Scripture expansions, tag-only and `title:`-only replies, bare claim and release lines in the claims thread, imports, and DMs copied by history sync.
- Out of scope: opening the DM or reply on tap (cycle 5), the app badge (6), per-member choices including hiding who wrote (7), mentions (8), the iPhone guide (3), and Brave's "Try again".

## Completion boundary

- Normal entry: someone replies to a member's post from any write path (the compose form, Quill or the JSON API, an agent reply), or sends them a DM. No new control for the recipient. Their existing Preferences row stays the switch.
- End-to-end outcome: within seconds, each of the recipient's devices shows the notice; a tap focuses or opens the app as today.
- Recovery: if the after-response send fails or the request dies, the queue sends the pending notice within about a minute. If both are unavailable (no cron, no detached process), notices wait in the list and go out the next time either runs. A notice older than 24 hours is dropped instead of sent late.
- Release: merged, deployed with the flag on at zenmemes.com, cron running, and the maintainer's iPhone check passed.

## Known user and environment

- The maintainer on his iPhone SE (3rd gen) in the ZenMemes home-screen app, signed in as `ilyag`, with Notifications on, at zenmemes.com. A second account (another key, or a guest) supplies the reply and the DM.
- Failures to handle: no notice arrives, it arrives late, it shows the wrong person or text, or a burst arrives as many notices. His next unaided step is Preferences → Send a test, which tells a broken device apart from a missing trigger. If the test arrives but replies don't, the Deploy page's existing worker row says whether the task worker is running, and `./v3 task-queue` over SSH shows what each `push_notify` run reported.
- Evidence on that iPhone, app closed: a reply notice within about 10 seconds; a second reply in the same thread arriving as one replacement after the window; a DM notice with the name and no text; a DM read on the laptop before its window ends producing no follow-up. A desktop or Android check does not replace this.

## Risks

- **Agent bursts.** Claude's sweeps answer many of the maintainer's posts at once; unheld, he would turn notifications off. Earliest validation: a test sending ten replies across ten threads in a minute and counting sends. Mitigation: the per-member cap, proven by that test before the iPhone check.
- **A write path is missed or double-counted.** Some replies never notify, or an agent's retry sends twice. Earliest validation: one test per reply path listed in Step 1, plus a retry of the same post. Mitigation: one record per post ID, and the pending list ignores a post it has already seen.
- **The after-response route behaves differently on the host.** With php-cgi, `fastcgi_finish_request()` is missing and the detached worker may not start, so notices fall back to cron's minute. Earliest validation: the maintainer's first reply test on zenmemes.com, with the send's recorded route (after-response or queue) visible in the task report. Mitigation: the queue fallback is always armed, so the worst case is late, never lost.
- **iPhone treats a replacement as a fresh alert, or ignores `tag`.** Grouping still buzzes, or notices pile up. Earliest validation: the iPhone check's second-reply step. Mitigation: holding within the window keeps it to one send either way. If `tag` is ignored, it becomes a cycle 5 follow-up.

## Shared component inventory

- `src/ForumRewrite/Push/`: `PushSender::send()` unchanged for each device; `PushSubscriptionStore` extended with a listing by username and the pending-notice list in the same `push.sqlite3` (named-column inserts); `PushSendResult` handling as in `PushApiController::test()`, whose device and session checks move into one shared helper instead of being copied.
- `ViewerSessions::signedInIdentityForKey()`: the per-device session check, unchanged.
- Reply rule: `InboxFeed`'s Replies filter (parent written by any of the member's keys, not by them). Read state: `InboxMarkStore` and `PrivateMessageReadState`. Both reused, not reimplemented.
- Skips: `PortedSource` headers, `AgentResponseMetadata::EXPANSION`, `TagOnlyLeafFilter`, the claims-thread bare-line rule already in `InboxFeed`.
- Hooks: `LocalWriteService` reply paths (`createReply()`, `createSignedPost()`, `createPreparedPost()`) after their commit, outside the write lock; `PrivateMessageMailboxService::send()` after a new envelope is accepted (not `PrivateMessageStore`, which history sync also writes through).
- `SqliteTaskQueueStore` and `TaskQueueWorker`: one more task type with a fixed dedup key; `DetachedTaskQueueLauncher` for hosts without `fastcgi_finish_request()`.
- `public/service_worker.js` `pushNotice()`: used unchanged; it already reads `title`, `body`, `url` and `tag`.

## User flow

1. A member has Notifications on for their iPhone and closes the app.
2. Someone replies to one of their posts; the poster's page answers as fast as before.
3. A second later the iPhone shows "claude replied" with the reply's first line.
4. Two more replies land in that thread within five minutes; at the end of the window one notice replaces the first: "3 replies · claude, guest".
5. A friend sends a DM; the phone shows "<friend> · New message". The member reads it on their laptop, and the friend's next DM two minutes later sends no follow-up.

## Success criteria

- Tests show: each reply path and DM send records exactly one pending notice, and the skipped kinds record none; self-replies and own DMs never notify; held follow-ups produce one send per tag per window; ten replies in a minute produce at most the cap; read items are dropped; signed-out and gone devices are removed; the flag off records nothing; a send failure never changes the post's response.
- The poster's response time is unchanged within normal noise on the compose and API paths.
- The maintainer's iPhone check passes the evidence in Known user and environment, and each `push_notify` run's report says which route sent each notice.

## Enhancement requests

- None yet.

## Study snapshots and approval

- 2026-11-24 UTC, Step 1 approved (Option A); open-question assumptions carried forward.
- 2026-11-24 UTC, Step 2 handoff: **0.70** projected total, unchanged from Step 1. New understanding without a scope change: the DM hook belongs in the mailbox service, not the store, and the device check is shared with Send a test. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `6b66069d`. **Approved Step 2** by the maintainer, 2026-11-24.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*
