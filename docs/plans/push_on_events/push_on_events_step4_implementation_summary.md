> **Feature plan:** [Step 1](./push_on_events_step1_solution_assessment.md) · [Step 2](./push_on_events_step2_feature_description.md) · [Step 3](./push_on_events_step3_development_plan.md) · [Step 4](./push_on_events_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/push_on_events`; host ledger [push_on_events.md](../effort_ledger/push_on_events.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.70**

Branch: `feature/push-on-events`, from `origin/main` at `3dc254a` (the plans were written against `6b66069d`; the two commits between are docs only). Planning commit `bebea6e`.

Step 4 entry, 2026-11-24 UTC: **0.70** reaffirmed from Step 3. Claude, single exposed rater.

Dependency delta so far: zero.

## Stage 1 - Pending notices and the send pass

**Original estimate: 0.20**
**Retrospective: 0.20**

- Changes:
  - `PushSubscriptionStore`: tables `push_pending` (one row per kind, source ID and recipient; a repeat is ignored), `push_sends` (one line per notice sent, for the hold and the cap) and `push_lease` (one sender at a time); `devicesFor()`, `addPending()`, `pending()`, `hasPending()`, `finishPending()`, `recordSend()`, `lastSendAt()`, `sendsSince()`, `prune()`, `takeLease()`, `releaseLease()`. Named-column inserts.
  - `PushDelivery::toDevice()`: the session check, send, gone-delete and `markSent` that `PushApiController::test()` did inline; `test()` now calls it. `PushSendResult::gone()` added for the signed-out case.
  - `PushNotifier::sendDue(int $now, string $route)`: groups pending notices by member and tag; first send at once, later ones held until 300 seconds after the tag's last send, then one replacement ("2 replies", names); at 6 sends in 600 seconds the rest fold into one `more` notice ("4 new replies · claude"), itself held the same way; read notices, notices with no device, and notices older than 24 hours are finished without sending. `payload()` and `overflowPayload()` build what the worker shows.
  - `tests/PushNotifierTest.php`, listed in `tests/run.php`.
- Verification:
  - Passed: `./v3 test PushNotifierTest PushApiTest`, 13 of 13. Every device gets the notice once and a repeat event is ignored; follow-ups in one thread are held and arrive as one replacement while another thread is not held; ten replies in ten threads give six notices plus one folded notice; a DM names only the sender, and a read DM is dropped; signed-out and 410 devices are deleted; day-old and device-less notices are dropped; a second sender waits for the lease. Payloads are checked by decrypting each request as the browser would.
  - Passed: `./v3 test --smoke PushNotifierTest PushApiTest PushSenderTest WebPushCryptoTest`, 511 of 511. Full suite not run.
- Notes:
  - A notice that no device accepted (every device refused) is marked `failed` and not retried, so it is never sent late.
  - The overflow notice opens `/unread/`.
- Estimate details: original scope as in Step 3; no revisions; driver was the hold and cap rules.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*

## Stage 2 - Replies record notices

**Original estimate: 0.15**
**Retrospective: 0.15**

- Changes:
  - `LocalWriteService`: optional `onPostPublished` callable beside the existing enqueue callables. `withTimedWriteLock()` calls it after the lock, next to the high-traffic refresh, for every post-creating write (`$refreshThreadPage`: `createReply()`, `createPreparedPost()`, `createSignedPost()`, thread creation and the format change), when the result has a `post_id` and is not an `already_posted`, `already_applied` or `existing` replay. A throw is logged and the post still returns ok. `createPortedThread()` returns no `post_id`, so ported threads never reach it.
  - `PushNotices::recordReply()`: reads the new post from the read model; notifies the parent's author when that key is approved, its username is not "guest", and the replier is not an approved key of the same username; skips ported, hidden, retracted, Scripture expansion, tag-only and `title:`-only (`ThreadLabels::isTagOrTitleOnly()`, the rule behind `TagOnlyLeafFilter`), bare claim and release lines in the claims thread (`ClaimDirective::isBare()`, `ReleaseDirective::isBare()`), and replies more than an hour old; records nothing for a member with no device. `firstPlainLine()` drops Markdown markers and cuts at 100 characters.
  - `PushNotices::deviceMayReceive()` and `PushNotifier`'s new optional `mayReceive` filter: a device gets its username's notices only if the key that turned it on is an approved key of that username.
  - `RouteServices::writer()` passes the callable when `FORUM_PUSH_ENABLED` is on; `recordPushNotices()` opens the store, remembers the site's address from `HTTP_HOST` (`PushSubscriptionStore::rememberSite()`, new `push_meta` table) and falls back to the remembered one in the task worker.
  - `InboxMarkStore::isCaughtUp()`: read-only; with no mark nothing counts as read.
  - `tests/PushReplyNoticeTest.php`, listed in `tests/run.php`.
- Verification:
  - Passed: `./v3 test PushReplyNoticeTest`, 6 of 6. A guest reply to a member's thread records one notice with the cleaned first line and the site; a new thread, the member's own reply, a tag-only reply and a reply to a guest record none; no device, an unapproved parent key and an hour-old reply record none; the same reply twice is one notice; a throwing hook still saves the post; `isCaughtUp()` leaves no mark behind.
  - Passed: `./v3 test --smoke PushReplyNoticeTest PushNotifierTest PushApiTest InboxFeedTest InboxPageTest HighTrafficPostRefreshTest ThreadPortWriteTest`, 527 of 527. Full suite not run.
  - Not covered by a test: `createSignedPost()` and `createPreparedPost()` (they need a signing key in the fixture). They reach the same post-lock call as `createReply()`; the agent reply path uses `createReply()`.
- Notes:
  - Security finding, fixed in scope: cycle 2 stores a device under the viewer's username, and any key, approved or not, can carry any username. Without `deviceMayReceive()`, an unapproved key named "ilyag" could have received ilyag's DM sender names.
  - The task worker has no `HTTP_HOST`, so the site address comes from the last web request that recorded a notice. A worker-only reply before any web request records nothing.
- Estimate details: original scope as in Step 3, plus the device filter (new understanding, not new scope); no revision.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*

## Stage 3 - DMs record notices

**Original estimate: 0.08**
**Retrospective: 0.08**

- Changes:
  - `PrivateMessageMailboxService`: optional `onMessageAccepted` closure (message ID, sender, recipient), called after the publication lock and only when `acceptEnvelope()` stored a new message, not when `acceptedEnvelope()` returned an earlier one. A throw is logged and the message still returns. History sync writes through `PrivateMessageStore` directly and never reaches it.
  - `PushNotices::recordMessage()`: one notice for the recipient (not to self, not to "guest"), title the sender's username token, no body, tag `dm:<sender>`, url `/messages/conversation/<sender>`.
  - `PrivateMessageReadState::isSeen()` and `PrivateMessageStore::isSeenBy()`: read on any device of the recipient (seen row), or older than read tracking (baseline).
  - `PrivateMessageApiController::service()` passes the closure through `RouteServices::recordPushNotices()` when `FORUM_PUSH_ENABLED` is on.
  - `tests/PushMessageNoticeTest.php`, listed in `tests/run.php`.
- Verification:
  - Passed: `./v3 test PushMessageNoticeTest PrivateMessageMailboxServiceTest`, 12 of 12. A new DM records one notice with no envelope text; a retried send records no second one; a synced message and a recipient with no device record none; `isSeenBy()` turns true only after the recipient acknowledges; a throwing hook still sends.
  - Passed: `./v3 test --smoke PushMessageNoticeTest PrivateMessageMailboxServiceTest PrivateMessagePageControllerTest InboxPageTest`, 511 of 511, after naming the columns in the test's own profile insert (`SqlInsertColumnsTest` caught it). Full suite not run.
- Notes: the read check itself is wired with the sender in Stage 4.
- Estimate details: original scope as in Step 3; no revisions.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*

## Stage 4 - Sending right after the response, and the queue

**Original estimate: 0.15**
**Retrospective: 0.15**

- Changes:
  - `SqliteTaskQueueStore::PUSH_NOTIFY` (`push_notify`), an allowed type. `TaskQueueWorker` gets an optional `runPushNotify` runner: it records the counts as task progress, requeues the task (`requeueClaimed()`, as the retraction sweep does) while notices are held, and completes it otherwise. `scripts/task_queue.php` runs `PushNotifierFactory::forProject()->sendDue(now, 'queue')` there, and nothing while `FORUM_PUSH_ENABLED` is off.
  - `PushNotifierFactory::forProject()`: the notifier for this instance's private stores; read check from `InboxMarkStore::isCaughtUp()` (replies) and `PrivateMessageStore::isSeenBy()` (DMs); device filter `PushNotices::deviceMayReceive()`.
  - `RouteServices::recordPushNotices()`: when a notice is recorded, enqueues `push_notify` with the fixed key `push-notify` and calls `PushAfterResponse::request()` with its task ID.
  - `PushAfterResponse::run()`, called at the end of `FrontController::handle()`: with `fastcgi_finish_request()`, ends the response, then runs the pass in the same request under `ignore_user_abort` and a 60-second limit; without it, `DetachedTaskQueueLauncher::launchForTask()` starts a one-task worker for that task only (`run --task-id=N`). The queued task stays either way, so cron sends what this misses.
  - `tests/PushAfterResponseTest.php`, listed in `tests/run.php`.
- Verification:
  - Passed: `./v3 test PushAfterResponseTest`, 5 of 5. Nothing recorded runs nothing; with a finish function the pass runs once in the request and marks the route `after-response`; without one the worker is launched for that task only, and a failed launch is reported; two notices in a request share one queued task; the site address is remembered from `HTTP_HOST`; a recording failure is swallowed; the worker requeues while notices are held, completes when none are, and a worker without the runner fails the task with `push_notify_unavailable`.
  - Passed: `./v3 test --smoke PushAfterResponseTest PushNotifierTest PushReplyNoticeTest PushMessageNoticeTest PushApiTest TaskQueueWorkerTest TaskQueueStoreTest TaskQueueCommandTest DetachedTaskQueueLauncherTest`, 557 of 557. Full suite not run.
  - Not run: a real php-fpm request, and the CLI worker against a live push service. Tests run under the CLI, where `fastcgi_finish_request()` does not exist; Stage 5's replay on zenmemes.com covers the host.
- Notes:
  - An agent reply written by the task worker records its notice there, but no request ends to send it; the queued task sends it on the next worker run (about a minute with cron).
  - The plan's per-request response-time check is covered by construction: everything after `fastcgi_finish_request()` runs once the poster has their answer, and the detached route only starts a process.
- Estimate details: original scope as in Step 3; no revisions.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*

## Stage 5 - Docs and the iPhone replay

**Original estimate: 0.07**
**Retrospective: 0.07**

- Changes:
  - `docs/concepts/push_subscription.md`: what notifies and what is skipped, the device rule, how notices are sent, the hold and the cap; the "proposed" line now names cycles 5 and 7.
  - `docs/concepts/internal_task_queue.md`: the `push_notify` task type.
  - `docs/reference/agent_api.md`: agents' replies and DMs can notify people.
  - `docs/plans/notifications_checklist.md`: cycle 4 status, the approved choices, and the release checks.
- Verification:
  - Passed: `git diff --check`; every relative link in the touched docs and this cycle's plans resolves.
  - Passed: `./v3 test --smoke PushAfterResponseTest PushNotifierTest PushReplyNoticeTest PushMessageNoticeTest PushApiTest ApiRouteCatalogTest`, 516 of 516. Full suite not run.
  - **Pending (maintainer):** the iPhone SE replay from Step 2, after merge and deploy with the flag on and cron running. With the app closed: (1) a second account replies to one of your posts: "<name> replied" with its first line within about 10 seconds; (2) a second reply in that thread within 5 minutes: no buzz, then one replacement "2 replies" when the window ends (needs the cron minute); (3) a DM: "<name>" with "New message", no text; (4) another DM from them, read on the laptop before the window ends: no follow-up. If nothing arrives, Send a test first. A desktop or Android pass does not count.
- Notes: if iPhone ignores `tag` (notices pile up instead of replacing), record it here and open it as a cycle 5 follow-up.
- Estimate details: original scope as in Step 3; no revisions.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*

## Closeout

- Delivered: Steps 1–3 scope, plus two in-scope findings: the approved-key device rule (security) and the remembered site address for the worker.
- Commits: planning `bebea6e`; stages 1–4 `2ee6a20`, `85bd332`, `3dba9da`, `6334475`; stage 5 is the commit carrying this section.
- Dependency delta: zero. Read-model schema: unchanged. `push.sqlite3` gains `push_pending`, `push_sends`, `push_meta` and `push_lease` on first open.
- Unverified: a real php-fpm request on zenmemes.com, delivery to Apple from the worker, and iPhone's handling of `tag`; all are in the pending replay.
- Effort study: stage retrospectives 0.20, 0.15, 0.08, 0.15, 0.07 (not summed). **Cycle retrospective 0.75** (projected 0.70): the device rule and the worker's site address were new understanding, not new scope. Maintainer comfortable-fit judgment pending.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*
