Cycle: `v3/prompt_card_meta_corner`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); no separate log unless something comes up
Effort study: `railspike-effort` / `v3/prompt_card_meta_corner` — [host ledger](../effort_estimation_trial.md#cycle-v3prompt_card_meta_corner); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.05**
Status: implemented and verified locally; pushed with a PR; merge and deploy need the maintainer

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
Planning commit: `9c52c3aa9053ceb6b2d299960e3fda9e38464d59`

## Dated execution history

- 2026-11-16T04:10Z: no matching item in `docs/feedback/items/`.
- 2026-11-16T04:12Z: moved the line in `templates/pages/agent.php` into a new `prompt-card__footer` row with the tags; `public/assets/agent.css` lays the row out and gives the title the bottom margin the line used to provide. No theme stylesheet mentions the prompt card classes.
- 2026-11-16T04:15Z: maintainer: "thank you, you can pr when done".

## Study history

- 2026-11-16T04:10Z intake: **0.05**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2), rated after reading `agent.php` and `agent.css` (no pristine intake).

- 2026-11-16T04:18Z closeout: retrospective **0.05**. Maintainer acceptance pending; no accepted-count increment.

## Verification

- Passed: `./v3 test --smoke PromptLibraryTest PresentationProfileMatrixTest`: 480/480.
- Looked at in headless Chrome at 1100px and 375px, Gloss theme: the live `/agent/` HTML with the template change applied by hand and the branch's `agent.css`. Tags left and the line right on one row at desktop width; at phone width the line wraps under the tags, right-aligned; a card with no tags shows the line at the bottom right.
- Not run: full suite (maintainer runs it); a local server render of the template itself; other themes (none override these classes); the live site, which needs the deploy.

## Outcome

Delivered as scoped. Next action: maintainer merges and deploys.

## Final review

Diff is `templates/pages/agent.php`, `public/assets/agent.css`, this record, the plans index and the ledger. No debug findings.

Commit/merge/publication status: completion commit pending at time of writing; pushed with a PR; not merged or deployed.

Attribution: Claude (claude-fable-5-2), 2026-11-16 UTC.
