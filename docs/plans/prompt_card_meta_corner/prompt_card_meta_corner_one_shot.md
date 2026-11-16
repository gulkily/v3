Cycle: `v3/prompt_card_meta_corner`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); no separate log unless something comes up
Effort study: `railspike-effort` / `v3/prompt_card_meta_corner` — [host ledger](../effort_estimation_trial.md#cycle-v3prompt_card_meta_corner); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.05**
Status: planned; next permitted action: implement

## Original request

Maintainer, in a Claude session on 2026-11-15, about https://zenmemes.com/agent/: "for this page, the prompts list, can you please move the byline to the bottom right corner (instead of under the title)". A minute later: "by byline i mean teh whole secodn line".

## Authorization

The request itself, 2026-11-15: a bounded layout change with one evident approach, carried through to a pushed branch and an open PR. Merging and deploying are not covered.

## Outcome and work type

Application change, presentation only. On each prompt card in the `/agent/` library, the whole second line (author, date, like count, Discuss/replies link) moves from under the title to the bottom right corner of the card. It shares the card's last row with the tags: tags left, this line right. On a narrow screen where both do not fit, the line wraps below the tags and stays right-aligned. A card with no tags still shows the line at the bottom right.

Excluded: any change to what the line says, the Waiting list, the site's own prompts below the library, other pages.

## Approach and risks

Move the existing `<p class="meta prompt-card__meta">` after the copy field in `templates/pages/agent.php` and wrap it with the tags paragraph in one flex row. Low risk: the copy field's status line sits between the field and the footer and is empty until a copy fails, so spacing needs a look in a browser.

## Reuse decisions

Same markup and classes (`meta`, `prompt-card__meta`, `prompt-card__tags`, `chip`) and the same `$rowMeta` output, only moved. One new wrapper class, `prompt-card__footer`, in `public/assets/agent.css`, because the card has no bottom row today. No theme overrides exist for the prompt card classes to carry along (checked at implementation).

## Completion conditions

`/agent/` shows each prompt's second line at the card's bottom right at phone and desktop widths, with and without tags; search filter and copy still work.

## Concepts touched

Prompt library card: used, layout changed. No inventory change.

## Verification and recovery plan

`./v3 test --smoke` plus the tests that render `/agent/` (`PromptLibraryTest`, `PresentationProfileMatrixTest`). Look at the page in a browser at phone and desktop widths in the default themes if a local server can be run; say so if not. Full suite not run (maintainer runs it).

Starting revision and branch: `9e5aa010ddff888248ca670c932e3b5f7ec69274`; `fix/prompt_card_meta_corner`
Planning commit: pending

## Dated execution history

- 2026-11-16T04:10Z: no matching item in `docs/feedback/items/`.

## Study history

- 2026-11-16T04:10Z intake: **0.05**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2), rated after reading `agent.php` and `agent.css` (no pristine intake).

Attribution: Claude (claude-fable-5-2), 2026-11-16 UTC.
