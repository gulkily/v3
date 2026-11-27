# Notifications Turn on: time limits and details — one-shot

Cycle: `v3/push_turn_on_diagnostics`; Mode: one-shot; Record/index: this file, [plans index](../README.md), [notifications checklist](../notifications_checklist.md)
Debug mode: enabled ([log](push_turn_on_diagnostics_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/push_turn_on_diagnostics`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/push_turn_on_diagnostics.md))
Extended estimates: enabled (no alternatives or work units, so only the cycle estimate applies)

**Cycle estimate: 0.25**

Status: implemented on `fix/push-turn-on-diagnostics`; PR open, not merged or deployed. Next action: the maintainer merges and deploys; the next stalled Turn on on any phone shows Details and lands at `/tools/push-reports/`.

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
Planning commit: `fd05b8a54` (confirmed with Git before any implementation edit).

## Execution history

- 2026-11-27: plan written.
- 2026-11-27: maintainer confirmed the friend's phone had ZenMemes open from its home-screen icon (not a Safari tab), so the unsupported-tab explanation is ruled out.
- 2026-11-27: implemented. `push_preferences.js`: `within(step, work, ms)` names every step (service worker 10 s; push key, existing subscription, drop old subscription, push subscribe, save subscription, unsubscribe, send test 15 s); `fail()` shows the note, fills Details and posts the report (not after a 401). Request errors without server text now say `HTTP <status>` instead of "Request failed". Turn off and Send a test use the same steps and Details. `preferences.php`: closed `details` under the row with a Copy button; `preferences.css` styles it. `PushSubscriptionStore`: `push_reports` table, `addReport()` (10 an hour per member, 500 kept), `reports()`. `PushApiController`: `report()` and `reportsPage()`. `Application`: routes; `ApiRouteCatalog`: entry. `docs/concepts/push_subscription.md` updated.
- 2026-11-27: decision: `.account-key-advanced` styling lives in `identity.css`, which the Preferences page doesn't load, so a small `.preferences-page__details` rule in `preferences.css` was added instead of loading another stylesheet.
- 2026-11-27: decision: no Tools launcher button for `/tools/push-reports/` (it would need icons in every icon set); the address is in the API catalog and this record. Possible follow-up.

## Outcome

Delivered as planned. Not delivered: a fix for the stall itself, which waits on a report from a real phone. Limitations: a report needs a session (a 401 failure is diagnosed by its own message); the reports page is plain text and has no Tools button.

## Verification

- `./v3 test --smoke PushApiTest ApiRouteCatalogTest LocalAppSmokeTest UsageReportsPageTest LlmsTxtRendererTest`: all passed (503, 510 and 498 run across three invocations). New: `PushApiTest::testAMemberReportsAFailedTurnOnAndOperatorsReadIt` (guest 401, missing header 403, GET 405, empty 400, oversize 413, stored with CRLF normalized, 11th in an hour 429, next hour accepted, non-operator 403, operator text newest first) and `testOnlyTheNewestReportsAreKept`; `testRoutesAnswer404WithTheFlagOff` covers `/api/push/report`. The full suite was not run.
- `node tests/browser/push_preferences.mjs` (local Chrome, `playwright-core` linked from outside the repository): ok. New cases: `pushManager.subscribe()` never answering (the reported condition, stubbed, 300 ms limit) ends Off, "Didn’t respond", Details closed and naming `push subscribe`, no endpoint or key in the text, report posted with the same text and the ForumPush header, Copy copies it; a 503 on save shows "Try again" with `HTTP status: 503` and the server's text.
- Looked at the stalled state at 380 px wide (screenshot): note in the value, Details below, wraps without overflow.
- Not verified: a real iPhone. The reported stall is replayed only by stub; which step stalls on the friend's phone is still unknown.

## Final review

Diff checked: helpers reused (`guardedInput`, `error`, store pattern, `profileIsActiveOperator`), no duplicate disclosure component, file scope matches the plan plus the test files. Commit/merge status: completion commit pending at time of writing; PR to be opened; not merged or deployed.

## Study history

- 2026-11-27 intake **0.25**, single Claude rater, no prior exposure. Execution entry: reaffirmed 0.25. Closeout retrospective **0.25**; maintainer acceptance pending.

*— Claude · 2026-11-27 UTC*
