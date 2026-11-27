# Notifications Turn on: time limits and details — one-shot

Cycle: `v3/push_turn_on_diagnostics`; Mode: one-shot; Record/index: this file, [plans index](../README.md), [notifications checklist](../notifications_checklist.md)
Debug mode: enabled ([log](push_turn_on_diagnostics_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/push_turn_on_diagnostics`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/push_turn_on_diagnostics.md))
Extended estimates: enabled (no alternatives or work units, so only the cycle estimate applies)

**Cycle estimate: 0.25**

Status: plan written; next permitted action: implement.

## Request and authorization

The maintainer, 2026-11-27 ET, in a Claude session. On a friend's newer iPhone, with ZenMemes opened from its home-screen icon, a fresh account, Preferences → Notifications → Turn on showed the system prompt, Allow was tapped, and the row stayed on "Working…"; reopened, it said Off. Tapping Turn on again stuck the same way. The friend has left, so the phone can't be inspected. Claude proposed per-step time limits plus a report to the server naming the stalled step; the maintainer: "oh i see, we're building out the diagnostics. that's good". Same session, he asked for the project guideline this cycle follows ([error_details_guideline](../error_details_guideline/error_details_guideline_one_shot.md), PR #450).

Authorizes a one-shot through an open PR. Excludes: a fix for the stall itself (unknown until a report arrives), a Tools launcher button for the reports page, merge, deployment and phone checks.

Feedback item: none matched in `docs/feedback/items/` (searched "Working", "Turn on", "notification", "push").

## Outcome and work type

Application change, following [Error messages and diagnostics](../../../CONTRIBUTING.md#error-messages-and-diagnostics):

1. Every step of Turn on that can stall has a time limit: push key request, reading or dropping an old subscription, `pushManager.subscribe()`, saving the subscription. The service-worker wait keeps its 10 s. The permission prompt has none (the person is deciding).
2. A failure leaves the row Off with a short message ("Sign in first", "Not supported here", "Didn't respond", "Try again") and, under the row, a closed **Details** disclosure with a Copy button: step, error, HTTP status and server text, waited time, permission, installed app or tab, online, browser, page, time. Key: value text.
3. The same text is sent best-effort to `POST /api/push/report` (signed-in members, as the other push routes), stored in the private push database (rate-limited, size-capped, oldest pruned), and readable by operators at `/tools/push-reports/` as plain text.

## Approach and risks

- Risk: a step that is slow but would finish is cut off. Limit 15 s per step; a later tap retries from the start.
- Risk: a subscribe that finishes after its time limit leaves a subscription the server never saved. The on-load check already re-saves a held subscription, so the next visit to Preferences repairs it and shows On.
- Risk: the report is abused. Members only, X-Requested-With guard (reused), 4 KB cap, 10 reports per hour per member, 500 kept.
- Risk: details leak secrets. They carry no endpoint, key, cookie or token; the server-supplied error text is already public copy.
- Risk: the failure is a 401 (no session), so the report can't be sent. Then the message itself ("Sign in first") is the diagnosis.

## Reuse decisions

- `public/assets/push_preferences.js`: extended in place; `registration()`'s existing race becomes the shared time-limit helper.
- `templates/pages/preferences.php` `showNotifications()`: unchanged contract; the details element sits under the row in the same `account-group`.
- Disclosure: native `details`/`summary` per `docs/reference/progressive_disclosure.md`, `.account-key-advanced` styling reused if it fits.
- Copy: `navigator.clipboard.writeText` with selectable `<pre>` fallback, as `public_key.js` does.
- Server: `PushApiController::guardedInput()` and `error()`; `PushSubscriptionStore` gains a `push_reports` table (same private file, same CREATE IF NOT EXISTS pattern); the rate-limit shape follows `UsageReportStore::retryAfter()` but keys on username, not a client hash.
- Operator check: `profileIsActiveOperator()`, as the Usage reports page.
- Tests extend `PushApiTest` and `tests/browser/push_preferences.mjs`.

## Concepts touched

`docs/concepts/push_subscription.md`: adds the Turn on failure report; updated in place.

## Completion conditions

- Each Turn on step stalling (stubbed) ends Off with "Didn't respond" and Details naming that step; a server 503 shows "Try again" and Details with status and the server's text; Copy copies the text.
- A report is posted with that text; the server stores it for a member and refuses guests, wrong headers, oversize bodies and the 11th report in an hour.
- `/tools/push-reports/` shows reports to operators, newest first, and refuses others.
- Release: a real iPhone tap produces a report on zenmemes.com (maintainer, after deploy).

## Verification and recovery plan

`./v3 test --smoke PushApiTest`; `node tests/browser/push_preferences.mjs` with local Chrome. Replay of the reported condition (stall after Allow) is by stub only; the real iPhone is unverified until a report arrives. Full suite not run (AGENTS.md). Recovery for a member: tap Turn on again.

Starting revision `1fc53a90c` on `main`; branch `fix/push-turn-on-diagnostics`.
Planning commit: pending.

## Execution history

- 2026-11-27: plan written.

## Study history

- 2026-11-27 intake **0.25**, single Claude rater, no prior exposure. Execution entry: reaffirmed 0.25.

*— Claude · 2026-11-27 UTC*
