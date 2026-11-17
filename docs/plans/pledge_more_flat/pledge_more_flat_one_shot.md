# Pledge More flat

Cycle: `v3/pledge_more_flat`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/pledge_more_flat`; [cycle ledger](../effort_ledger/pledge_more_flat.md). Standing P2 enrollment; parent `v3/pledge_cards`.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)

**Cycle estimate: 0.10**

Status: technically complete and committed; next permitted action: the third polish cycle, then push and open the PR (authorized).
Debug report: [log](pledge_more_flat_debug_log.md)

## Original request

2026-11-17, maintainer: "please do the small polish fixes as one-shots". This cycle is the second item: on a campaign thread a reply's "More" holds the Retract control's own "…" menu, a menu inside a menu.

## Authorization

As in [`pledge_row_byline`](../pledge_row_byline/pledge_row_byline_one_shot.md#authorization): this one-shot, its plan and completion commits, pushing and a PR, on the shared branch `feat/pledge-polish` with one PR. Does not authorize merging or deploying.

## Outcome and work type

Application change. Inside "More" on a campaign thread, the "…" menu's buttons ("Show as Markdown" or "Show as plain text", and "Retract post") sit directly in the action row. Elsewhere (other threads, post pages) the "…" menu stays.

Excluded: the buttons' wording, the confirm prompt, and the retraction flow.

## Approach and risks

- `retract_post_control.php` takes `$retractInline`; when true it draws the panel's buttons without the `<details>`. `post_card.php` passes `$compactCard`.
- `post_retraction.js` already skips closing the menu when there is none (`if (menu)`), so no script change.
- Risk: Retract becomes one tap away inside "More". The browser's confirm prompt still guards it, as before.

## Reuse decisions

Existing retract partial, its buttons, feedback and retry; existing script. No new markup classes.

## Completion conditions

On a campaign thread, open a reply's "More": Retract post and the format switch are plain buttons; Retract asks to confirm and retracts. On a non-campaign thread the "…" menu is unchanged.

## Concepts touched

[Pledge campaign](../../concepts/pledge_campaign.md): changed (Rows entry, "More").

## Verification and recovery plan

`PledgeCampaignTest` checks that a campaign reply has no `post-more-actions` and a plain thread's reply does; the pledge browser check retracts from inside "More" with the confirm prompt; `tests/browser/post_retraction.mjs` unchanged for post pages. Smoke set plus `PledgeCampaignTest`. Full suite not run, per `AGENTS.md`.

Starting revisions and branch: `feat/pledge-polish` at `2039ac65` (after `pledge_row_byline`).
Planning commit: `7e838dd5` (plan and index link only; confirmed with `git log`).

## Dated execution history

- 2026-11-17: plan written.
- 2026-11-17: `retract_post_control.php` takes `$retractInline` and drops the `<details>` and panel when true; `post_card.php` passes `$compactCard`. No script change.
- 2026-11-17: `PledgeCampaignTest` checks that a campaign pledge's "More" holds a plain Retract and no `post-more-actions`, and a plain thread's reply keeps the menu. The browser check opens "More" as the writer, finds no "…" menu there, presses Retract, sees the confirm prompt, dismisses it and finds nothing retracted.
- 2026-11-17, correction during verification: a variable name in the new browser step clashed with an existing one; renamed.

## Study history

- Intake: **0.10**, exposed rater Claude (claude-opus-5-5), after reading `retract_post_control.php`, `post_retraction.js` and its browser test (no pristine intake).

- Execution entry: reaffirmed 0.10.
- Closeout retrospective: **0.10**. Not hours or measured labor; maintainer acceptance pending.

## Outcome

Delivered as bounded. On a campaign thread, the author's "More" shows Reply, the pledge buttons, Like, Flag, "Show as Markdown" and "Retract post" in one row; Retract still asks to confirm. Post pages and other threads keep the "…" menu.

## Verification

- `./v3 test PledgeCampaignTest`: 28 passed; the new checks failed with the base `post_card.php` (27 of 28).
- `tests/browser/pledge_campaign.mjs`: whole flow with the new Retract step, and 17 themes at 375 and 1280 pixels, passed.
- `tests/browser/post_retraction.mjs` not rerun: post pages are unchanged (`retractInline` defaults to false there). Smoke set run once for the branch at the end. Full suite not run, per `AGENTS.md`.

## Final review

Diff checked: the retract partial, `post_card.php`, one PHP test, one browser step, the concept's Rows entry, this record, its debug log, the plans index and the cycle ledger.

## Commit/merge/publication status

Completion commit: `68c99378` (confirmed with `git log`). Pushed with a PR on `feat/pledge-polish`; not merged or deployed.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
