# Pledge More flat

Cycle: `v3/pledge_more_flat`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/pledge_more_flat`; [cycle ledger](../effort_ledger/pledge_more_flat.md). Standing P2 enrollment; parent `v3/pledge_cards`.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)

**Cycle estimate: 0.10**

Status: planned; next permitted action: implement after the planning commit.

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
Planning commit: pending.

## Dated execution history

- 2026-11-17: plan written.

## Study history

- Intake: **0.10**, exposed rater Claude (claude-opus-5-5), after reading `retract_post_control.php`, `post_retraction.js` and its browser test (no pristine intake).

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
