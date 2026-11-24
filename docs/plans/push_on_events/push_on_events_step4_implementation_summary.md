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
