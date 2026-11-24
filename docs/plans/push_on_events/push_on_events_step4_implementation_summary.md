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
