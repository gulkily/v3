Cycle: `v3/prompt_copy_icon`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); no separate log unless something comes up
Effort study: `railspike-effort` / `v3/prompt_copy_icon` — [host ledger](../effort_estimation_trial.md#cycle-v3prompt_copy_icon); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.1**
Status: implemented and verified locally; pushed with a PR; merge and deploy need the maintainer

## Original request

Maintainer, in a Claude session on 2026-11-16, about the Prompts page (`/agent/`), after [prompt_card_like](../prompt_card_like/prompt_card_like_one_shot.md) (PR #270, merged): "can you please move the copy button to the top right and make it icon only".

## Authorization

The request itself, 2026-11-16: a bounded restyle of one existing control, carried through to a pushed branch and an open PR. Merging and deploying are not covered.

## Outcome and work type

Application change, presentation only. The Copy button on a prompt field sits in the field's top right corner and shows only its icon. After a successful copy the icon becomes a check mark for 1.5 seconds (it used to change its word to "Copied"). The button keeps its accessible name ("Copy prompt", "Copied" while the check shows) and gains a "Copy" tooltip.

Scope decision: the library cards and the site's own three prompts lower on the page share one partial and one button, so both change together; two looks for the same control on one page would be worse.

Excluded: the Bookmarklets and Account copy buttons (other pages); what gets copied; the failure messages in the status line.

## Approach and risks

`templates/partials/agent_prompt.php`: drop the visible word, add a check icon beside the copy icon, add `title`. `public/assets/agent.css`: anchor the button top right, square it, swap icons on `[data-copied]`, and move the field's reserved space from the bottom to the right so text never runs under the button. `templates/pages/agent.php` script: set and clear `data-copied` and the accessible name where it set the word.

Risk: themes restyle buttons (uppercase, bevels, pills, phone stretching); check each theme at phone and desktop widths. A 44px tap target is kept.

## Reuse decisions

Same partial, same button, same copy script and fallbacks; the copy icon SVG is unchanged. The check icon is new inline SVG in the partial because no shared check icon exists in the templates (checked at implementation).

## Completion conditions

On `/agent/`, every prompt field shows an icon-only Copy button at its top right; pressing it copies the prompt and shows the check for 1.5 s; a failed copy still selects the text and explains in the status line; prompt text does not run under the button.

## Concepts touched

Copyable prompt field: changed (button position and feedback). No inventory change.

## Verification and recovery plan

`./v3 test --smoke PresentationProfileMatrixTest PromptLibraryTest`; `node tests/browser/prompt_card_like.mjs` (its copy step updated to the new feedback) with screenshots across themes at phone and desktop widths. Full suite not run (maintainer runs it).

Starting revision and branch: `0b18578` (origin/main after PR #270); `fix/prompt_copy_icon`
Planning commit: `c8f1cdf`

## Dated execution history

- 2026-11-16T05:06Z: no matching item in `docs/feedback/items/`.
- 2026-11-16T05:15Z: implemented as planned. No shared check icon exists in the templates, so the check is inline SVG in the partial. The script's hidden "Copy"/"Copied" word is gone; the button's `data-copied` attribute and accessible name carry the state.

## Study history

- 2026-11-16T05:06Z intake: **0.1**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2), rated with the partial, stylesheet and script already read in the two preceding cycles (no pristine intake).

- 2026-11-16T05:20Z closeout: retrospective **0.1**. Maintainer acceptance pending; no accepted-count increment.

## Verification

- Passed: `./v3 test --smoke PresentationProfileMatrixTest PromptLibraryTest`: 480/480.
- Passed: `node tests/browser/prompt_card_like.mjs` (local Chrome), copy step extended: the clipboard holds the prompt, the button shows the copied state and name then returns, sits within 12px of the field's top right corner and is at least 44px square.
- Looked at: the card in Gloss, Gloss Dark, Light, Dark, Word97, Chicago, Midnight, Bubblegum, Spearmint, Sticker and Engagify at 390px and 1280px, plus the check state in Gloss.
- Not run: full suite (maintainer runs it); the failed-copy path in a browser without clipboard access (script path unchanged apart from the feedback lines); the site's own three prompts were not screenshotted separately (same partial and CSS); the live site, which needs the deploy.

## Outcome

Delivered as scoped. Next action: maintainer merges and deploys.

## Final review

Diff: `templates/partials/agent_prompt.php`, `templates/pages/agent.php` (copy feedback lines), `public/assets/agent.css`, `tests/browser/prompt_card_like.mjs`, this record, the plans index and the ledger. No debug findings.

Commit/merge/publication status: completion commit pending at time of writing; pushed with a PR; not merged or deployed.

Attribution: Claude (claude-fable-5-2), 2026-11-16 UTC.
