> **Feature plan:** [Step 1](./push_on_events_step1_solution_assessment.md) · [Step 2](./push_on_events_step2_feature_description.md) · [Step 3](./push_on_events_step3_development_plan.md) · [Step 4](./push_on_events_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/push_on_events`; host ledger [push_on_events.md](../effort_ledger/push_on_events.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.70**

Concepts touched: [push subscription](../../concepts/push_subscription.md), changed (Stage 5); [internal task queue](../../concepts/internal_task_queue.md), changed (Stage 5, task type list); [caught-up mark](../../concepts/caught_up_mark.md), used, with one read-only lookup added (Stage 2); [ported thread](../../concepts/ported_thread.md) and [thread activity](../../concepts/thread_activity.md), used unchanged. Feedback: none.

## Completion Contract

- Normal entry: any reply to a member's post (compose form, JSON API, Quill, agent reply) or a DM to them. The recipient's switch stays Preferences → Notifications from cycle 2.
- End-to-end outcome: each of the recipient's signed-in devices shows the notice within seconds; a follow-up in the same thread or conversation within five minutes arrives as one replacement; at most about 6 notices per member per 10 minutes.
- Recovery: a pending notice survives a failed after-response send and goes out on the next `push_notify` run; read items, signed-out devices and gone endpoints are dropped; notices older than 24 hours are dropped, not sent.
- Deployment: no schema change to the read model; `push.sqlite3` gains a table on first open. Cron must run `./v3 task-queue` for the fallback and the held follow-ups.
- Release: merged, deployed, flag on at zenmemes.com, and Stage 5's iPhone replay passed by the maintainer.

## Key Risks

- **High risk:** **Opening Unread's mark creates it.** `InboxMarkStore::mark()` inserts a mark at "now" when a member has none, which would make every reply look read and silence it. Early validation: a Stage 2 test for a member who never opened Unread. Mitigation: a read-only lookup that treats a missing mark as unread.
- **High risk:** **Agent bursts.** Unheld, a Claude sweep sends ten notices and the member turns them off for good. Early validation: Stage 1's ten-replies test. Mitigation: the hold window and the per-member cap, both in Stage 1 before any hook exists.
- **The worker has no request host.** Cron can't build the VAPID contact from `HTTP_HOST`. Mitigation: each pending notice stores the site address of the request that created it.
- **php-cgi host.** No `fastcgi_finish_request()`. Early validation: Stage 4 test with the function absent. Mitigation: start the one-task worker detached; the queue is always armed.

## Stage 1 — Pending notices and the send pass

**Estimate: 0.20**

- Goal: a tested pass that sends due notices to every device of a member, with the hold window, cap, read check and device check, before anything records notices.
- Dependencies: none.
- Expected changes:
  - `PushSubscriptionStore`: table `push_pending` (kind, source ID, recipient username, tag, title, body, url, site address, created, due, sent route), `devicesFor(string $username): list`, `addPending(...)`, `due(int $now): list`, `markDone(...)`. One row per kind, source ID and recipient; a repeat is ignored. Named-column inserts.
  - `PushNotifier::sendDue(int $now, string $route): array` (counts sent, held, merged, read, dropped): groups by recipient and tag; sends the first notice of a tag at once and holds later ones until five minutes after that tag's last send, then sends one replacement naming the count and up to three names; past 6 sends per member in 10 minutes, folds the rest into one "N new replies" notice; asks a read-check callable before each send; drops rows older than 24 hours.
  - The device and session check from `PushApiController::test()` moves into one `PushNotifier` helper that `test()` then calls.
- Verification approach: `tests/PushNotifierTest.php` with a fake sender and clock: one device and three devices; second reply held then merged; ten replies across ten threads stay within the cap; read rows dropped; signed-out and 410 devices deleted; old rows dropped; repeat source ignored. `./v3 test --smoke PushNotifierTest PushApiTest PushSenderTest`.
- Estimate details: driver: the hold and cap rules; uncertainty: low.
- Risks or open questions:
  - Impact: wrong grouping either floods or silences.
  - Early warning / validation: the burst tests above.
  - Mitigation: the rules live in one method with a fixed clock.
- Canonical components/API contracts touched: `PushSender::send()` (unchanged), `PushSendResult`, `PushSubscriptionStore`, `ViewerSessions::signedInIdentityForKey()`, `PushApiController::test()` (now calls the shared check).

## Stage 2 — Replies record notices

**Estimate: 0.15**

- Goal: each notifying reply records one pending notice per recipient; skipped kinds record none.
- Dependencies: Stage 1.
- Expected changes:
  - `LocalWriteService`: optional `?callable $onReplyPublished` beside the existing enqueue callables, called after the commit and outside the write lock in `createReply()`, `createSignedPost()` and `createPreparedPost()` for a new reply (not an `already_posted` replay). Never `createPortedThread()`.
  - `ReplyNotice::for(array $post, PDO $readPdo): ?array`: recipient username from the parent's author (Unread's rule), none when the replier holds a key of that username; skips Scripture expansions, ported posts, `TagOnlyLeafFilter` leaves and bare claim lines in the claims thread; title "<name> replied" ("guest" unsigned), first plain-text line about 100 characters, tag `thread:<id>`, url the reply.
  - `InboxMarkStore::isCaughtUp(string $viewer, string $postId, string $createdAt): bool`, read-only, false with no mark. Used as Stage 1's reply read check only when `FORUM_INBOX_ENABLED` is on.
  - `Application` and the worker's agent-reply wiring pass the callable when `FORUM_PUSH_ENABLED` is on; a failure inside it is logged and never reaches the poster.
- Verification approach: `tests/PushReplyNoticeTest.php`: one test per path (compose form, JSON reply, signed post, prepared post, agent reply), self-reply, reply under another key of the same username, guest reply, expansion, tag-only reply, claim line, ported thread, replayed post, flag off; the no-mark case; a callable that throws still returns the post. Smoke set plus `InboxFeedTest`.
- Estimate details: driver: covering every reply path; uncertainty: medium (prepared posts carry their parent in the record, not the input).
- Risks or open questions:
  - Impact: a missed path never notifies; the mark side effect silences everything.
  - Early warning / validation: the per-path and no-mark tests.
  - Mitigation: one callable at the shared post-commit point in each method.
- Canonical components/API contracts touched: `LocalWriteService` (constructor callable precedent: `enqueueOfflineSnapshotPublication`), `InboxFeed` reply rule, `InboxMarkStore`, `TagOnlyLeafFilter`, `AgentResponseMetadata`, `PortedSource`.

## Stage 3 — DMs record notices

**Estimate: 0.08**

- Goal: a newly accepted DM records one notice for its recipient.
- Dependencies: Stage 1.
- Expected changes: `PrivateMessageMailboxService::send()` calls an optional `?callable $onMessageAccepted` only for a new envelope (not an `acceptedEnvelope()` replay), outside the publication lock; title the sender's username, body "New message", tag `dm:<sender>`, url the conversation. `PrivateMessageReadState::isSeen(string $viewer, string $counterpart, string $messageId): bool` as the DM read check. History sync is untouched.
- Verification approach: `tests/PushMessageNoticeTest.php`: new DM records one; resend of the same message ID records none; message to self none; seen DM dropped by the pass; history sync records none. Smoke set plus the private message tests touched.
- Estimate details: driver: small; uncertainty: low.
- Risks or open questions: a DM notice must never carry envelope bytes; the test asserts the payload holds only the name.
- Canonical components/API contracts touched: `PrivateMessageMailboxService`, `PrivateMessageReadState`, `PrivateMessageStore` (unchanged).

## Stage 4 — Sending right after the response, and the queue

**Estimate: 0.15**

- Goal: due notices go out seconds after the post on php-fpm, by a detached worker elsewhere, and by cron in every case.
- Dependencies: Stages 1–3.
- Expected changes:
  - `SqliteTaskQueueStore::PUSH_NOTIFY`, enqueued with one fixed dedup key whenever a notice is recorded; `TaskQueueWorker` runs `PushNotifier::sendDue(now, 'queue')`, records the counts as task progress, and requeues itself while held notices remain.
  - `FrontController::handle()`: when the request recorded a notice, after the response it calls `fastcgi_finish_request()` and runs `sendDue(now, 'after-response')`, under `ignore_user_abort` and a short time limit; without that function, `DetachedTaskQueueLauncher` gets a new `launchForPush()` (not gated by the emergency variable, since one task at a time is already enforced by the dedup key).
- Verification approach: tests with the function stubbed present and absent; launcher called once for two notices; worker run sends and records progress; response body and status unchanged. Manual: `./v3 start`, two browser sessions, reply, watch the local push log. Smoke set plus the task queue tests touched.
- Estimate details: driver: request-end wiring; uncertainty: medium (host SAPI).
- Risks or open questions:
  - Impact: a slow push service holds a php-fpm worker.
  - Early warning / validation: the time-limit test with a slow fake sender.
  - Mitigation: `PushSender`'s 10-second timeout and at most one pass per request.
- Canonical components/API contracts touched: `SqliteTaskQueueStore`, `TaskQueueWorker`, `DetachedTaskQueueLauncher`, `FrontController`, `docs/concepts/internal_task_queue.md`.

## Stage 5 — Docs and the iPhone replay

**Estimate: 0.07**

- Goal: the docs say what notifies, and the maintainer's iPhone confirms it.
- Dependencies: Stage 4.
- Expected changes: `push_subscription.md` (what notifies, what is skipped, the burst rule, routes); `internal_task_queue.md` (task type); `notifications_checklist.md` cycle 4 entry; one line in `docs/reference/agent_api.md` that agents' replies to people may notify them; Step 4 summary with the replay steps.
- Verification approach: `git diff --check`; links resolve. The replay from Step 2's Known user and environment on the iPhone SE, app closed: reply within about 10 seconds; second reply as one replacement after the window; DM with name, no text; DM read on the laptop first gives no follow-up; and the task report names the route. Pending until the maintainer runs it after deploy; a desktop pass does not count.
- Estimate details: driver: docs; uncertainty: the replay is outside the session.
- Risks or open questions: if iPhone ignores `tag`, record it and open a cycle 5 follow-up rather than widening this cycle.
- Canonical components/API contracts touched: concept entries and the checklist named above.

Final review before closeout: the diff has no copied device check, no new dependency and no read-model change, and the poster's response is unchanged.

## Study snapshot

| Field | Value |
| --- | --- |
| Checkpoint | Step 3 handoff |
| Projected total | 0.70, unchanged; stage work 0.20, 0.15, 0.08, 0.15, 0.07 (not summed) |
| New understanding | the Unread mark side effect and the worker's missing host; neither changes scope |
| Rater / exposure | Claude, single exposed rater |
| Reference / protocol | R1 / P2 |
| Baseline | `6b66069d` |
| Approval | **Approved Step 3** by the maintainer, 2026-11-24 |

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*
