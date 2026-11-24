> **Feature plan:** [Step 1](./push_on_events_step1_solution_assessment.md) · [Step 2](./push_on_events_step2_feature_description.md) · [Step 3](./push_on_events_step3_development_plan.md) · [Step 4](./push_on_events_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/push_on_events`; host ledger [push_on_events.md](../effort_ledger/push_on_events.md). Standing P2 enrollment. New cycle; the [notifications checklist](../notifications_checklist.md) gave an inherited estimate of 0.60.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection. They are alternatives and are not summed.

Cycle estimate: 0.70

## Original Query

Handoff: `push_on_events` (notifications cycle 4)
Written 2026-11-24 by Claude for the next session. Cycle 4 of the [notifications checklist](../notifications_checklist.md). The maintainer chose to skip cycle 3 (`push_ios_guide`) for now and go straight here.

Where things stand

* Cycle 2 (`push_subscribe`, PR #397) is merged and deployed on zenmemes.com with `FORUM_PUSH_ENABLED` on. The maintainer turned it on and got a test notification on his iPhone SE home-screen app on 2026-11-24, so Apple accepts our VAPID token and encrypted messages. Plans and the Step 4 summary: [push_subscribe/](../push_subscribe/push_subscribe_step4_implementation_summary.md).
* Desktop Brave shows "Try again" on Turn on, likely because Brave turns off Google's push service by default. It is a lower-priority follow-up in the checklist and is not part of this cycle.
* Nothing sends a notification except Send a test.

What cycle 4 delivers
A DM or a reply to your post sends a notification to each of your devices. A DM names who wrote, never what (DMs are end-to-end encrypted). A reply names who replied and its first line. One notification per conversation or thread, replaced by the next (`tag`). Your own posts never notify you. The checklist entry for cycle 4 has the scope and the 2026-11-24 notes on speed, read state and the lock screen.
Run it as a staged Railspike cycle (estimate 0.60 in the checklist). The maintainer approves each step himself and shares plans as attached files in the chat.

Questions Step 1 must settle
From the checklist:

1. What a reply notification shows when the reply is unsigned (a guest post).
2. Whether a post already read on another device still notifies. The 2026-11-24 lean: wait a short moment, then check server read state, and skip it if read.

Also compare in Step 1:

* When to send: right after the post is saved, once the response is already sent (for example `fastcgi_finish_request()` where available; nothing in `src/` uses it yet), against the task queue (cron, up to about a minute; may be off on a new install). The maintainer wants it near instant and accepts the queue as the fallback.
* Lock screen: whether "hide who wrote" is in this cycle or waits for cycle 7 (`push_choices`).

Code to start from

* Sender and store from cycle 2: `src/ForumRewrite/Push/` (`PushSender::send()`, `PushSubscriptionStore`, `PushStorageConfig`). The store has `find()` by endpoint but no per-username listing yet; cycle 4 needs one. Each row's session must still be signed in as its key before sending (`ViewerSessions::signedInIdentityForKey()`); see `PushApiController::test()` for the check, and `docs/concepts/push_subscription.md`.
* The worker already shows a payload's `title`, `body`, `url` and `tag` (`public/service_worker.js`, `pushNotice()`). Tap routing beyond focusing the app is cycle 5.
* Where DMs are saved: `PrivateMessageApiController::send()` and `PrivateMessageMailboxService`; DM read state comes from `PrivateMessageApiController::read()` / `acknowledge()`.
* Where replies are saved: `LocalWriteService::createReply()`, `createSignedPost()` and `createPreparedPost()` (reached from `WritePostAndIdentityApiController`). Check every path that can create a reply, including agent replies (`TaskQueue/AgentReplyTask.php`) and ported threads, and decide which should notify.
* Unread's caught-up marks: `src/ForumRewrite/Inbox/InboxMarkStore.php`.
* Task queue: `src/ForumRewrite/TaskQueue/` (`SqliteTaskQueueStore`, `TaskQueueWorker`, `DetachedTaskQueueLauncher`) and `docs/concepts/internal_task_queue.md`.

Keep in view

* The failure modes listed in the checklist, above all sending too much: agents post a lot, so self-notices, agent-to-agent replies and bursts need care.
* `PRINCIPLES.md`, `AGENTS.md` (feedback items, smoke tests only, no new dependency without approval) and the Railspike effort study enrollment.
* Follow-ups go on a new branch from a fresh `origin/main`.

— Claude · 2026-11-24 UTC · Model: claude-opus-5-5

## Problem

A DM or a reply to a member's post reaches their phone only if they open the site, because nothing calls `PushSender` except Send a test.

Concepts touched: [push subscription](../../concepts/push_subscription.md), changed (devices listed per username, a pending-notice list); [internal task queue](../../concepts/internal_task_queue.md), changed (one new task type); [caught-up mark](../../concepts/caught_up_mark.md), used unchanged; [ported thread](../../concepts/ported_thread.md) and [thread activity](../../concepts/thread_activity.md), used unchanged to decide what is skipped. Feedback: no matching item in `docs/feedback/items/`.

## What the code check found

- Every reply that matters comes through `LocalWriteService`: `createReply()` (compose form, the JSON API, agent replies from `AgentReplyFulfillmentService`, Scripture expansions), `createSignedPost()` and `createPreparedPost()` (Quill and other agents), and `createPortedThread()`. DMs come through one place, `PrivateMessageStore::acceptEnvelope()`. DMs copied into a local clone go through the history sync instead, which should never notify.
- "A reply to your post" already has a definition: Unread's Replies section (`InboxFeed`) counts a post whose parent was written by any key of your username, and not by you. Cycle 4 uses the same rule, so a notice and Unread always agree.
- Read state already exists on the server. A DM is read once its row is at or below `private_message_seen` for that conversation. A reply is read once Unread's caught-up mark covers it. Opening a thread directly does not mark its replies as read, though; only Unread and its catch-up do.
- The task queue has no payload column and no "run after" time. A notice needs both (who, what, when), so it waits in a small private table, and the queue task just drains that table.
- Agents have no devices, because push needs a browser subscription. Agent-to-agent replies therefore notify nobody already. The flood risk is agents replying to *people*: one Claude sweep can answer ten of the maintainer's posts in a minute.
- Nothing in `src/` calls `fastcgi_finish_request()`. The runbook mentions both php-fpm and php-cgi hosts. Which one zenmemes.com runs is not recorded, so the code has to detect it.

## Shared by every option

- **What notifies.** A DM to you. A reply whose parent is yours, under Unread's rule. Never your own posts, under any of your keys. Skipped: ported comments (their authors aren't members), Scripture expansions, tag-only and `title:`-only replies that the thread page moves into the activity drawer (`TagOnlyLeafFilter`), bare claim and release lines in the claims thread, and anything from an import or history sync.
- **What a notice says.** DM: the sender's username, "New message", tag `dm:<sender>`. Reply: "<name> replied", the first line of plain text (Markdown removed, cut at about 100 characters), tag `thread:<id>`. `Urgency: high` for both.
- **Every device of the member.** The store gets a listing by username. Each device is checked before sending (its session must still be signed in as its key), as Send a test does. A 404 or 410 deletes the device.
- **Bursts.** The first notice for a thread or conversation goes out at once. Further ones for the same tag within five minutes are held and sent together at the end of the window, as one replacement ("3 replies · claude, ilyag"). Each member also has a cap, about 6 notices in 10 minutes. Past it, the rest become one "N new replies" notice. A replaced notice may still buzz on iPhone, so holding is the only sure way to stay quiet.
- **Already read.** Just before each send, the server checks read state (DM seen-row, Unread mark) and drops the notice if it is read. Because of the burst rule, a burst's follow-ups get a five-minute grace for free.
- **Flag.** Everything stays behind `FORUM_PUSH_ENABLED`. With the flag off, nothing is recorded or sent.

## Option A — Pending list, sent right after the response, queue as fallback

**Estimate: 0.70**
Main driver: two small hooks, the pending table, the burst and read checks, and the after-response drain with its detection. Uncertainty: medium, from how the host's PHP finishes a request.

When a post or DM is saved, the request adds a pending notice to `state/private/push.sqlite3`, once the write lock is released. After the response has gone to the poster, the same request sends whatever is due. It uses `fastcgi_finish_request()` where it exists (php-fpm). Elsewhere it starts a detached one-task worker, as `DetachedTaskQueueLauncher` already does for imports. Each save also queues one `push_notify` task, with a fixed dedup key so many saves share it. Cron runs it within a minute and sends anything left: held follow-ups, sends interrupted by a crash, and hosts where neither route worked.

- Pro: near instant on php-fpm, without slowing the poster; nothing is lost if the request dies, because the queue picks it up.
- Pro: two hooks (post commit, DM accept) cover every path found above.
- Con: on a host without php-fpm, each post may start one short process. The queue's dedup key keeps that to one at a time.
- Con: the detection and the after-response code need a real-host check, since tests run under the CLI.

## Option B — Queue only

**Estimate: 0.55**
Main driver: Option A without the after-response sending. Uncertainty: low.

The same hooks add pending notices and queue `push_notify`. Only the cron worker sends.

- Pro: the least code, and one sending path to test.
- Con: notices arrive up to a minute late, which the maintainer wants to avoid. On a new install with no worker, nothing is sent until `./v3 start` or cron runs.

## Option C — No hooks; scan for new posts and DMs

**Estimate: 0.75**
Main driver: a safe cursor over the read model and the DM store. Uncertainty: high.

A pass, run after write requests and by cron, reads posts and DMs newer than a saved cursor and builds notices from them. No write path changes.

- Pro: a write path added later can't be missed.
- Con: a rebuild, an import or a 500-comment port moves the cursor in ways that could flood or drop notices. Guarding against that costs more than the hooks would.
- Con: creation times come from clients and can be skewed (`accept_and_stage`), so "newer than" is not reliable.

## Recommendation

Option A. It meets "near instant" where the host allows and degrades to Option B's one-minute queue elsewhere, using the same table. Option C's only gain, never missing a path, is cheaper to get from one hook at the shared commit step plus a test listing every reply path.

Vertical slice: with the flag on at zenmemes.com, a second account replies to one of the maintainer's posts. Within seconds, the iPhone SE (app closed) shows "<name> replied" with the reply's first line. A second reply in the same thread a minute later replaces it after the five-minute window, saying "2 replies". A DM shows "<name> · New message". Reading the DM on the laptop before the window ends means the follow-up is never sent.

## Answers to the handoff's questions

1. **Unsigned (guest) reply:** notify as "guest replied" with the first line, the same as on the thread page. Guests count toward the burst cap like anyone else. The alternative, hiding a guest's text on the lock screen, is a one-line change if spam shows up.
2. **Already read elsewhere:** yes, check just before sending, as above. For the first notice the "short moment" is the time until the response is done, about a second. Waiting longer would hold a php-fpm worker or delay every first notice. Follow-ups get the five-minute window anyway.
3. **When to send:** Option A.
4. **Hide who wrote on the lock screen:** wait for cycle 7 (`push_choices`). It is a per-member choice and needs that cycle's settings. A DM notice already never shows content.

## Open questions for the maintainer

1. Five minutes for the hold window and about 6 notices per 10 minutes per member: are those right to start with?
2. Should agent replies that a reader asked for (`reply-agent`) notify the post's author? This plan assumes yes, since they are replies to your post like any other.
3. Which PHP runs zenmemes.com (php-fpm or php-cgi)? `./v3 status` over SSH, or the Tools system page, would tell us. The plan works either way; the answer only says which route Stage 1's check exercises.

— Claude · 2026-11-24 UTC · Model: claude-opus-5-5

## Study snapshot and approval state

| Field | Value |
| --- | --- |
| Checkpoint | Step 1 handoff |
| Projected total (recommended scope, Option A) | 0.70 |
| Alternatives (not summed) | A 0.70, B 0.55, C 0.75 |
| Inherited estimate | 0.60, from the notifications checklist (Claude, 2026-11-24) |
| Reference / protocol | R1 / P2 |
| Rater | Claude, single exposed rater |
| Exposure | Estimated after reading the write paths, read state and queue, so not strictly prospective. Raised from 0.60 for burst holding and the after-response route. |
| Baseline | `6b66069d` |
| Approval | **Approved Step 1** by the maintainer, 2026-11-24, with Option A; the three open questions were not answered, so Step 2 carries their assumptions forward |
