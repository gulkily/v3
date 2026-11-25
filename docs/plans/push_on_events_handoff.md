# Handoff: `push_on_events` (notifications cycle 4)

Written 2026-11-24 by Claude for the next session. Cycle 4 of the [notifications checklist](./notifications_checklist.md). The maintainer chose to skip cycle 3 (`push_ios_guide`) for now and go straight here.

## Where things stand

- Cycle 2 (`push_subscribe`, PR #397) is merged and deployed on zenmemes.com with `FORUM_PUSH_ENABLED` on. The maintainer turned it on and got a test notification on his iPhone SE home-screen app on 2026-11-24, so Apple accepts our VAPID token and encrypted messages. Plans and the Step 4 summary: [push_subscribe/](./push_subscribe/push_subscribe_step4_implementation_summary.md).
- Desktop Brave shows "Try again" on Turn on, likely because Brave turns off Google's push service by default. It is a lower-priority follow-up in the checklist and is not part of this cycle.
- Nothing sends a notification except Send a test.

## What cycle 4 delivers

A DM or a reply to your post sends a notification to each of your devices. A DM names who wrote, never what (DMs are end-to-end encrypted). A reply names who replied and its first line. One notification per conversation or thread, replaced by the next (`tag`). Your own posts never notify you. The checklist entry for cycle 4 has the scope and the 2026-11-24 notes on speed, read state and the lock screen.

Run it as a staged Railspike cycle (estimate 0.60 in the checklist). The maintainer approves each step himself and shares plans as attached files in the chat.

## Questions Step 1 must settle

From the checklist:

1. What a reply notification shows when the reply is unsigned (a guest post).
2. Whether a post already read on another device still notifies. The 2026-11-24 lean: wait a short moment, then check server read state, and skip it if read.

Also compare in Step 1:

- **When to send:** right after the post is saved, once the response is already sent (for example `fastcgi_finish_request()` where available; nothing in `src/` uses it yet), against the task queue (cron, up to about a minute; may be off on a new install). The maintainer wants it near instant and accepts the queue as the fallback.
- **Lock screen:** whether "hide who wrote" is in this cycle or waits for cycle 7 (`push_choices`).

## Code to start from

- Sender and store from cycle 2: `src/ForumRewrite/Push/` (`PushSender::send()`, `PushSubscriptionStore`, `PushStorageConfig`). The store has `find()` by endpoint but no per-username listing yet; cycle 4 needs one. Each row's session must still be signed in as its key before sending (`ViewerSessions::signedInIdentityForKey()`); see `PushApiController::test()` for the check, and `docs/concepts/push_subscription.md`.
- The worker already shows a payload's `title`, `body`, `url` and `tag` (`public/service_worker.js`, `pushNotice()`). Tap routing beyond focusing the app is cycle 5.
- Where DMs are saved: `PrivateMessageApiController::send()` and `PrivateMessageMailboxService`; DM read state comes from `PrivateMessageApiController::read()` / `acknowledge()`.
- Where replies are saved: `LocalWriteService::createReply()`, `createSignedPost()` and `createPreparedPost()` (reached from `WritePostAndIdentityApiController`). Check every path that can create a reply, including agent replies (`TaskQueue/AgentReplyTask.php`) and ported threads, and decide which should notify.
- Unread's caught-up marks: `src/ForumRewrite/Inbox/InboxMarkStore.php`.
- Task queue: `src/ForumRewrite/TaskQueue/` (`SqliteTaskQueueStore`, `TaskQueueWorker`, `DetachedTaskQueueLauncher`) and `docs/concepts/internal_task_queue.md`.

## Keep in view

- The failure modes listed in the checklist, above all sending too much: agents post a lot, so self-notices, agent-to-agent replies and bursts need care.
- `PRINCIPLES.md`, `AGENTS.md` (feedback items, smoke tests only, no new dependency without approval) and the Railspike effort study enrollment.
- Follow-ups go on a new branch from a fresh `origin/main`.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*
