# Pledge form place

Cycle: `v3/pledge_form_place`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/pledge_form_place`; [cycle ledger](../effort_ledger/pledge_form_place.md). Standing P2 enrollment; parent `v3/pledge_cards`.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)

**Cycle estimate: 0.15**

Status: planned; next permitted action: implement after the planning commit.

## Original request

2026-11-17, maintainer, after cycle 10 (`pledge_cards`): "can you please move the pledge form below the essay." Then, with a screenshot of the Pledge field, Note box and Post pledge button sitting above the essay with nothing around them: "also, please some kind of boundary around the pledge form? it looks loose."

## Authorization

The two requests above are direct, bounded instructions; earlier in the session the maintainer authorized making PRs. Authorizes a one-shot on a new branch from `main`, the plan and completion commits, pushing and opening a PR. Does not authorize merging or deploying.

## Outcome and work type

Application change: templates, CSS, tests.

1. The totals (sum, bar, count) stay straight under the title. The pledge form moves below the opening post's text (the essay), before the byline.
2. The form sits in a bordered panel, drawn with theme variables so every theme frames it.

Excluded: updating "Waiting for approval" rows without a reload (asked as a question, answered with costs, not chosen); the form's fields and wording; QDB quote roots, whose totals already follow the body (the form stays with them there).

## Approach and risks

- Split `pledge_totals.php` into two parts by a `$pledgePart` value: `totals` (default) and `form`. `thread_root_card.php` renders the totals where they are and the form after the body. QDB roots render both parts after the body, in order.
- `.pledge-form` gets `border: 1px solid var(--line)`, `background: var(--panel)` and padding, like other panels in `site.css`.
- Risk: themes that redraw panels (Word97, Arena, Bubblegum) may draw the border oddly. Checked in the browser check across all 17 themes with screenshots.
- Risk: tests and the browser check that expect the form inside `[data-pledge-totals]`. Those checks change to the new place.

## Reuse decisions

Existing `pledge_totals.php` partial and `reply_form.php` pledge placement; existing `--line` and `--panel` variables. No new partial, script or flag.

## Completion conditions

- Normal entry: open a campaign thread as visitor, author and member, with and without JavaScript.
- End-to-end: title, totals, essay, then the framed form; posting a pledge from it still works.
- Release: maintainer merges and deploys.

## Concepts touched

[Pledge campaign](../../concepts/pledge_campaign.md): changed (where the form sits).

## Verification and recovery plan

`PledgeCampaignTest` position check (title < totals < essay < form); `tests/browser/pledge_campaign.mjs` in all 17 themes at 375 and 1280 pixels, with screenshots looked at. Smoke set plus `PledgeCampaignTest`. Full suite not run, per `AGENTS.md`.

Starting revisions and branch: `feat/pledge-form-place` from `cc9f7d0a`.
Planning commit: pending.

## Dated execution history

- 2026-11-17: plan written.

## Study history

- Intake: **0.15**, exposed rater Claude (claude-opus-5-5), after reading `pledge_totals.php`, `thread_root_card.php`, the `.pledge-form` CSS and the tests that find the form (no pristine intake).

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
