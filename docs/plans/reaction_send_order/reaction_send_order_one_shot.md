# Reaction send order

Cycle: `v3/reaction_send_order`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/reaction_send_order`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)
Debug report: [log](reaction_send_order_debug_log.md)

**Cycle estimate: 0.20**

Status: planned; next permitted action: implement on this branch.

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
Planning commit: pending.

## Dated execution history

- 2026-11-15: plan written. No matching feedback item.

## Study history

- Intake: **0.20**, exposed rater Claude (claude-opus-5-5), after reading `thread_reactions.js` and `outbox_sender.js` (no pristine intake).

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
