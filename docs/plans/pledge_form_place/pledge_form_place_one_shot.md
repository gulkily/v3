# Pledge form place

Cycle: `v3/pledge_form_place`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/pledge_form_place`; [cycle ledger](../effort_ledger/pledge_form_place.md). Standing P2 enrollment; parent `v3/pledge_cards`.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)

**Cycle estimate: 0.15**

Status: technically complete and committed; next permitted action: push and open the PR (authorized).
Debug report: [log](pledge_form_place_debug_log.md)

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
Planning commit: `6df322ab` (plan and index link only; confirmed with `git log`).

## Dated execution history

- 2026-11-17: plan written.
- 2026-11-17: `pledge_totals.php` takes `$pledgePart` (`totals` by default, or `form`); the root card renders the form after the body and event block, before the byline. QDB roots render totals then form after the quote. `.pledge-form` has a 1px `var(--line)` border, `var(--panel)` background and 0.75rem padding.
- 2026-11-17: tests moved with the form: the PHP position check now also requires the form after the pitch and outside the totals; the browser check expects the form outside `[data-pledge-totals]`, and in every theme checks it starts below the opening post's text and has a border on all four sides.

## Study history

- Intake: **0.15**, exposed rater Claude (claude-opus-5-5), after reading `pledge_totals.php`, `thread_root_card.php`, the `.pledge-form` CSS and the tests that find the form (no pristine intake).

- Execution entry: reaffirmed 0.15.
- Closeout retrospective: **0.15**. No surprises; not hours or measured labor; maintainer acceptance pending.

## Outcome

Delivered as bounded. A campaign thread reads: title, totals, the opening post's text, then the pledge form in a bordered panel, then the byline and the pledge rows. Posting from the form works as before.

Limitations: the panel border uses `--line`, which is faint in some light themes by design; Word97 draws the essay in its own sunken box, so the form panel sits under a second box there. The fixed phone bar can cover the Post pledge button until the reader scrolls, as before.

## Verification

- `tests/browser/pledge_campaign.mjs` (Chrome headless): whole flow, and every one of 17 themes at 375 and 1280 pixels, with the new position and border checks. Screenshots of light, dark, Word97, Arena, Bubblegum and Console looked at by the agent.
- `./v3 test --smoke PledgeCampaignTest`: 506 run, 506 passed. Full suite not run, per `AGENTS.md`. Not checked: a real iPhone, production after deploy.

## Final review

Diff checked: two templates, one CSS rule, two tests, the concept entry, the checklist, this record, its debug log, the plans index and the cycle ledger. No new partial or duplicated form markup: the same partial renders both parts. Touched concept [Pledge campaign](../../concepts/pledge_campaign.md) updated for the form's place.

## Commit/merge/publication status

Completion commit: `14fd3ba7` (confirmed with `git log`). Pushed with a PR; not merged or deployed.

*— Claude · 2026-11-17 UTC · Model: claude-opus-5-5*
