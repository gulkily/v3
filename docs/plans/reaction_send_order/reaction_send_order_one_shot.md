# Reaction send order

Cycle: `v3/reaction_send_order`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/reaction_send_order`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)
Debug report: [log](reaction_send_order_debug_log.md)

**Cycle estimate: 0.20**

Status: technically complete and committed; next permitted action: push and open the PR (authorized).

## Original request

Same bug report as [scoped post reaction update](../scoped_post_reaction_update/scoped_post_reaction_update_one_shot.md) (PR #260): Close, then Resolve right away, briefly showed "server is busy", and the maintainer could not tell whether Resolve was applied. This is the page half of the fix.

## Authorization

2026-11-15, maintainer, on the proposed page fix: "a user should be able to add more than one thing, so we should not override the previous click. It's okay if it ends up with both close and resolve, period. As for the outbox message fix that you suggested, I support that, period. Are you able to proceed at this point?" Authorizes a one-shot on this branch, the plan and completion commits, pushing and opening a PR. Does not authorize merging or deploying.

## Outcome and work type

Application change, `public/assets/thread_reactions.js` only, plus tests.

1. Every click is sent. The page sends its reaction requests one at a time, in click order, so a second click never competes with the first for the server's write lock. No click is dropped or replaced: Close then Resolve writes both records, and the post shows the later state (the server's existing rule for state marks).
2. A reaction saved to the Outbox keeps saying so. Its notice stays on that post's feedback line, after any later message there, until the Outbox resends it. Then the line shows the result (for example "Resolved."), or the error if the server refuses it.

Excluded: server changes (PR #260); the Outbox page; the 5-second lock wait; other tabs.

## Approach and risks

- One page-wide send queue around `submitReaction`, which covers thread and post reactions and the Outbox resend (`submitSavedReaction`). Only the network send waits; button changes and "Saving tag..." still appear at once.
- The Outbox notice is kept per feedback line and added to any later message on that line. The retry loop (`scheduleOutboxRetry`) clears it when the item is accepted or refused.
- Risk: a hung request delays the next click. Bound: each send already times out after 20 seconds (`reactionFetchTimeoutMs`), and a failed send releases the queue.
- Risk: the notice outlives its item when another tab sends it. The notice then stays until reload; acceptable, noted.

## Reuse decisions

Existing `submitReaction`, `setFeedback`, `settleReaction`, `scheduleOutboxRetry`, `applyPostResolution` and the Outbox sender's results (`outbox_sender.js`). No new files, markup or CSS.

## Completion conditions

- Normal entry: Close then Resolve quickly on a reply in a `#meta` thread; also two Likes on different posts.
- End-to-end: requests go out one after another; both records are written; the post shows Resolved.
- Recovery: when a send is queued to the Outbox, its notice stays visible until the resend finishes, then shows the result.
- Release: maintainer merges and deploys.

## Concepts touched

[Outbox](../../concepts/outbox.md): changed (notice lifetime on the page). [Reaction record](../../concepts/reaction_record.md): used unchanged.

## Verification and recovery plan

Node tests in `ReactionOutboxTest` style: two quick state clicks on one post send in order without overlap; a queued notice survives a later success and is replaced after the resend. Smoke set plus `ReactionOutboxTest`, `TagUnlocksTest`, `BrowserSigningNormalizationTest`. Full suite not run, per `AGENTS.md`. Production replay pending until deploy.

Starting revisions and branch: `fix/state-clicks-in-order` from `ed22daed`.
Planning commit: `9dcfcd5b` (plan and index link only; confirmed with `git log`).

## Dated execution history

- 2026-11-15: plan written. No matching feedback item.
- 2026-11-15: implemented the send queue (`submitReaction` → `submitReactionNow`) and the Outbox notices (`addOutboxNotice`, `settleOutboxNotices`, called after each retry pass and on `online`). `submitSavedReaction` now also returns `viewerIsApproved`, so a resent state mark can show its state.
- 2026-11-15, discovery and correction within the same outcome: a real-browser replay (Close, then Resolve 300 ms later) showed the page sending in order, but after reload the post was Closed in 2 of 6 fast runs. Cause, already on main: `PostResolution::derive` orders marks by whole-second times and then by the random record ID, so two marks on one post in the same second pick a winner at random. Fix kept on the page: a reaction on a target whose last reaction finished in the current second waits for the next second before it is signed (at most about one second). Times stay true. Afterwards 8 of 8 fast runs ended Resolved. A server tie-break by arrival order would also cover other tabs and other people; not done here (server scope excluded).
- 2026-11-15, correction: `BrowserSigningNormalizationTest::testThreadReactionDuplicatePendingClickIssuesOneFetchAndClearsAfterSuccess` assumed the first fetch starts within two microtask ticks; the queue adds a hop, so the test now waits for the fetch to start.
- 2026-11-15, correction: `tests/browser/signed_reactions.mjs` failed on the base revision (checked with the base `thread_reactions.js`). Since accept_and_stage, a skewed clock is accepted in one request with a `.received` receipt, but the test still expected the old two-request fallback. It now expects one request and checks the receipt. Fixed per the maintainer's rule for tests failing on the base.

## Study history

- Intake: **0.20**, exposed rater Claude (claude-opus-5-5), after reading `thread_reactions.js` and `outbox_sender.js` (no pristine intake).
- Execution entry: reaffirmed 0.20.
- Closeout retrospective: **0.25**. The same-second tie and two stale tests added work; the outcome did not change. Not hours or measured labor; maintainer acceptance pending.

## Outcome

Delivered as bounded. The page sends reactions one at a time, in click order; Close then Resolve writes both records and the post shows Resolved, also after reload. A reaction kept in the Outbox keeps its notice ("Like is saved in your Outbox and will be sent automatically.") after later messages on that line, until the retry sends it ("Liked.") or the server refuses it (the error, and a Like button goes back to Like).

Limitations: marks from two tabs or two people in the same second still tie at random (server tie-break not done). A resend done by another tab leaves the notice until reload. A resent state mark without a result to read shows "Sent from your Outbox. Reload to see the change." New strings were not added to `docs/plans/ui_copy_audit_checklist.md`.

## Verification

- New `ReactionSendOrderTest` (Node, real `outbox_store.js`, `outbox_sender.js` and `thread_reactions.js`): quick Close then Resolve send without overlap, in separate seconds, and end Resolved; a queued Like keeps its notice after a later Closed and shows Liked after the retry; a refused retry shows the error and restores the Like button. All 3 failed against the base `thread_reactions.js` and pass now.
- Real-browser replay (Chrome headless, `tag_unlocks` fixture, script outside the repo): Close's response held 6 s, Resolve clicked 300 ms after Close. Base: Resolve sent at 328 ms while Close was pending, page ended Closed. This branch: Resolve sent after Close's response, page and reload both Resolved. Fast variant (50 ms hold): 8 of 8 Resolved after the tie fix (base of this branch before the fix: 4 of 6).
- Browser tests: `tag_unlocks.mjs` (gloss) ok, `post_signals.mjs` ok, `signed_reactions.mjs` all modes pass after the test fix.
- `./v3 test --smoke ReactionSendOrderTest ReactionOutboxTest TagUnlocksTest BrowserSigningNormalizationTest`: 580 run, 580 passed.
- Full suite not run, per `AGENTS.md`. Not checked: iOS Safari, production after deploy.

## Final review

Diff checked: `thread_reactions.js`, the concept entry for the Outbox, one new test, two corrected tests, the runner list, this record, its debug log, the plans index and the ledger. No duplicated message logic: the state message moved into `stateMarkMessage` and both the live and resend paths use it. Touched concept [Outbox](../../concepts/outbox.md) updated for the notice lifetime.

## Commit/merge/publication status

Completion commit: `c4a50a1a` (confirmed with `git log`). Pushed with a PR; not merged or deployed.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
