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
- 2026-11-16T05:22Z: completion commit `63f16a4` pushed; PR #272 open.
- 2026-11-16T05:25Z: scope v2 (addition), maintainer with a screenshot of a library card whose field had a wide empty strip under the text: "also, a lot of the textareas have empty space, can we shrink them down to the text size (plus one empty line to indicate it's eof)". PR #272 was still open and the change is to the same field, so it continues this cycle and branch. Scope v1 is retained above.
  - `agent.css`: the 8rem and 5rem minimum heights are gone (the minimum is now Copy's own height); the field ends in one empty line of padding. The strip in the screenshot was the room once kept for Copy at the bottom, already removed in v1.
  - `agent.php` script: where the browser lacks `field-sizing: content`, each field is sized to its text before paint, and again on resize and after a search. Without script and without `field-sizing`, `rows="5"` remains.
  - `agent.php` template: a library prompt's body is shown without its closing newline, which otherwise read as a second empty line. The copied text loses that trailing newline too.
  - Kept: the library fields' 16rem cap with scrolling, so one long prompt does not push the rest down the page. Not asked about; flagged in the handoff.

## Study history

- 2026-11-16T05:06Z intake: **0.1**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2), rated with the partial, stylesheet and script already read in the two preceding cycles (no pristine intake).

- 2026-11-16T05:20Z closeout (scope v1): retrospective **0.1**. Maintainer acceptance pending; no accepted-count increment.
- 2026-11-16T05:25Z revision: scope v2 adds field sizing; estimate revised to **0.15**.
- 2026-11-16T05:40Z closeout (scope v2): retrospective **0.15**.

## Verification

- Passed: `./v3 test --smoke PresentationProfileMatrixTest PromptLibraryTest`: 480/480.
- Passed: `node tests/browser/prompt_card_like.mjs` (local Chrome), copy step extended: the clipboard holds the prompt, the button shows the copied state and name then returns, sits within 12px of the field's top right corner and is at least 44px square.
- Looked at: the card in Gloss, Gloss Dark, Light, Dark, Word97, Chicago, Midnight, Bubblegum, Spearmint, Sticker and Engagify at 390px and 1280px, plus the check state in Gloss.
- Scope v2, passed: the same two commands again (480/480; browser test ok). The browser test now checks that every field on the page has under 2px of unused room beyond its text unless capped, leaves between one and two lines of bottom padding, and that with `field-sizing` switched off the script sizes each field to its text. Looked at the whole page at 1280px in Gloss and the card in the 11 themes at 390px.
- Scope v2, not run: Firefox or Safari themselves (the fallback was exercised in Chrome with `field-sizing` disabled).
- Not run: full suite (maintainer runs it); the failed-copy path in a browser without clipboard access (script path unchanged apart from the feedback lines); the site's own three prompts were not screenshotted separately (same partial and CSS); the live site, which needs the deploy.

## Outcome

Delivered as scoped, v1 and v2. Next action: maintainer merges and deploys.

## Final review

Diff: `templates/partials/agent_prompt.php`, `templates/pages/agent.php` (copy feedback lines), `public/assets/agent.css`, `tests/browser/prompt_card_like.mjs`, this record, the plans index and the ledger. No debug findings.

Commit/merge/publication status: completion commit pending at time of writing; pushed with a PR; not merged or deployed.

Attribution: Claude (claude-fable-5-2), 2026-11-16 UTC.
