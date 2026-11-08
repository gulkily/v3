Cycle: `v3/reply_bare_card`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/reply_bare_card` — [host ledger](../effort_estimation_trial.md#cycle-v3reply_bare_card); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.05**
Status: implemented and verified locally; next permitted action: push and PR (merge and deploy need the maintainer)

## Original request

Thread https://zenmemes.com/threads/thread-20261108165120-2cbd3bf2 (feedback item PF-20261108-B01): after posting a reply in the browser, the reply goes through three phases: "Posting...", then just the text without header and footer, then the header and footer. The middle phase should not happen.

## Authorization

The maintainer pasted the thread link in a Claude session on 2026-11-08 with no other text. Under `docs/agents/thread_to_pr.md` this authorizes a one-shot cycle for the bounded outcome below, pushing the branch, opening a PR and replying in the thread. Merging and deploying are not covered.

## Outcome and work type

Application change. Normal entry: the inline thread reply composer and the dedicated reply page (both use `data-phone-reply` and `reply_continuation.js`). Outcome: a sent reply shows as the pending card with its "Posting…" status until the full post (byline, body, actions) replaces it; it never shows as a bare body. Recovery: unchanged; a failed render still shows "Reply posted. Retry loading its full view." with Retry.

## Approach and risks

Cause: in `public/assets/reply_continuation.js`, `update()` hides the pending status as soon as the server accepts the reply, then `hydrate()` fetches the thread page and swaps in the full post. Between those two, the card is only the body.

Approach: keep the status visible in the accepted state until `hydrate()` replaces the card. One obvious approach; no alternatives worth weighing. Showing the byline early instead would duplicate the server-rendered post markup in the client, which the reuse rule argues against.

Risk: a reply restored from storage in the accepted state also reads "Posting…" while its full view loads. That is brief and accurate enough (it is still loading); acceptable.

## Reuse decisions

Reuses the existing pending card (`createPendingReplyCard` in `public/assets/browser_signing.js`) and its `[data-role="pending-reply-status"]` line; no new markup or CSS.

## Concepts touched

None.

## Verification and recovery plan

- Extend `tests/browser/reply_full_card.mjs`: while the full-view fetch is held, the accepted card's status is visible and reads "Posting…". Confirm it fails on unchanged code.
- Same test covers render-failure retry without resend.
- `tests/browser/reply_continuation.mjs`, `./v3 test --smoke BrowserSigningNormalizationTest`.
- Full suite not run (maintainer runs it).

## Starting revisions and branch

Host `main` at `d1052b3ff716c55b8d6bb53118d00aa585294b0e`; branch `fix/reply-bare-card`.

## Planning commit

`b442873` (confirmed with Git).

## Study history

- 2026-11-08T16:56Z intake: whole-cycle projection **0.05**, scope v1 as above, R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: rated after reading `reply_continuation.js`; no pristine intake.

## Dated execution history

- 2026-11-08T16:56Z: chronology disclosure: the one-line fix and test assertion were drafted in the working tree while diagnosing, before this record existed. They are held out of this planning commit and land in the completion commit.
- 2026-11-08T17:05Z: implemented as planned: removed the line in `update()` that cleared and hid the status on acceptance. Added the held-render assertion to `tests/browser/reply_full_card.mjs`; it fails on unchanged code ("pending status stays until the full card") and passes with the fix.
- 2026-11-08T17:05Z: no matching feedback item existed (searched `docs/feedback/items/` for the thread and for reply rendering); created PF-20261108-B01. PF-20261021-B04 (slow reply feedback) is related but about latency, not the bare intermediate card.

## Study closeout

Intake 0.05; execution entry reaffirmed; **retrospective 0.05**. Single exposed rater Claude (claude-opus-5-5), R1/P2. Maintainer acceptance pending; no accepted-count increment.

## Outcome

Delivered: a sent reply keeps its "Posting…" status from send until the full post replaces it, on the inline composer and the reply page. Render-failure recovery unchanged.

## Verification

- `node tests/browser/reply_full_card.mjs` (Chromium): desktop inline and dedicated pass, including the new assertion and render-only retry without resend. The later phone section fails with "full phone card above re-entry inline light"; it fails identically on unchanged `main`, so it is pre-existing and unrelated.
- `node tests/browser/reply_continuation.mjs`: times out at line 137 (image compose fields) on both this branch and unchanged `main`; pre-existing, unrelated.
- `./v3 test --smoke BrowserSigningNormalizationTest`: passes except `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, which also fails on unchanged `main`.
- Full suite not run. Not checked: Firefox, WebKit, a physical phone, production.

## Final review

Diff is one removed line plus a comment in `reply_continuation.js` and one test assertion; no duplication, within scope.

## Commit status

Planning commit `b442873`; completion commit follows on `fix/reply-bare-card`. Merge and deploy are not covered by this cycle's authority.

*— Claude · 2026-11-08 UTC · Model: claude-opus-5-5*
