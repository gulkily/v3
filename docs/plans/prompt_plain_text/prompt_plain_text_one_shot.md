Cycle: `v3/prompt_plain_text`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); no separate log unless something comes up
Effort study: `railspike-effort` / `v3/prompt_plain_text` — [host ledger](../effort_estimation_trial.md#cycle-v3prompt_plain_text); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.15**
Status: implemented and verified locally; pushed with a PR; merge and deploy need the maintainer

## Original request

Maintainer, in a Claude session on 2026-11-16: "let us adjust how the prompts page works. in the listing page, there are too many textareas, there should be not this many textareas, they are distracting. i'm brainstorming still, but i don't even know where we need a textarea, except when replying. it should just be selectable text and a copy button in the corner. for the threads page, there should be an extra button to edit the prompt, and that should turn the reply form into an editor for the prompt (is that any different than just editing) and it should pre-populate it with the version of the prompt i clicked the button on."

A staged cycle, `v3/prompt_plain_text_and_revise`, was started for all of it (Step 1 approved with "3: yes to \"show all\""; Step 2 drafted). Then: "let us split off the communal editing part into a cycle and one-shot the visual improvements?"

## Authorization

The split request, 2026-11-16: the visual part as a one-shot, carried through to a pushed branch and an open PR. Merging and deploying are not covered. The editing part stays in the staged cycle, whose planning documents are still uncommitted drafts awaiting Step 2 approval.

## Outcome and work type

Application change, presentation only. On the Prompts page (`/agent/`):

- Every prompt, the site's own three included, is selectable text in a quiet box, with Copy in the box's top right corner. The page has no text field except the search box.
- A shared prompt much taller than the old 16rem cap is cut off, fading at the bottom, with a "Show all" button under it that becomes "Show less". This replaces the scroll box. Copy always copies the whole prompt. Without script the prompt shows in full and can be selected by hand.
- Copy, its check mark, its tooltip and its failure messages are unchanged. When a copy fails, the prompt's text is selected, as the field's was.

Excluded: versions and Edit on the thread page (the staged cycle); the thread page itself; any change to which prompts are listed.

## Approach and risks

`templates/partials/agent_prompt.php`: the read-only field becomes a `pre` holding the same text; the label becomes a caption tied to it. `templates/pages/agent.php` script: copy from the text, select it on a failed copy, cut off tall shared prompts before paint; the field-sizing and select-on-focus code goes. `public/assets/agent.css`: the box, the cut and the button; field rules go.

Risks: themes styled the field as an input for free, so the box needs a look in every theme; "Show all" is a new button and takes each theme's button look; a box only slightly over the cap should not be cut for the sake of a few lines.

## Reuse decisions

Same partial, Copy button, icons, copy fallbacks and theme rules for the button. The box takes the site's existing `pre` text styling (`site.css`, plus Gloss's `pre` rule) with `--panel` and `--line`, the colours other inset boxes use. New: the cut-and-expand control. No existing one fits: `line-clamp` in `site.css` is a fixed two-line preview with no control, and `usage_log.php`'s "Show all" reveals list rows.

## Completion conditions

`/agent/` has no `textarea`; each prompt can be selected and copied; a long shared prompt is cut with "Show all" and expands and collapses; Copy on a cut prompt copies all of it; search, Like and the site's own prompts still work.

## Concepts touched

Copyable prompt field: changed (text, not a field). No inventory change.

## Verification and recovery plan

`./v3 test --smoke PresentationProfileMatrixTest PromptLibraryTest` (the matrix test's label assertion updated); `node tests/browser/prompt_card_like.mjs`, updated for text and extended with a long prompt (cut, expand, full copy), with screenshots in every theme at phone and desktop widths. Full suite not run (maintainer runs it).

Starting revision and branch: `6685625` (origin/main); `feat/prompt_plain_text`
Planning commit: `2b865fa`

## Dated execution history

- 2026-11-16T09:55Z: no matching item in `docs/feedback/items/`.
- 2026-11-16T10:10Z: implemented as planned. A prompt is cut only when it is more than a quarter over the 16rem cap, so a few extra lines never cost a press. The empty line that marked the end of a field went with the field; the box's edge shows the end.
- 2026-11-16T10:15Z: a full-page screenshot showed two of the site's own prompts blank. The DOM had them at full size and a screenshot of that section showed all three, so it was the capture, not the page. The test now also captures the section on its own.

## Study history

- 2026-11-16T09:55Z intake: **0.15**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2), rated with the partial, script and stylesheet already read (no pristine intake).

- 2026-11-16T10:25Z closeout: retrospective **0.15**. Maintainer acceptance pending; no accepted-count increment.

## Verification

- Passed: `./v3 test --smoke PresentationProfileMatrixTest PromptLibraryTest`: 480/480. The matrix test now asserts the caption and text markup and that `/agent/` has no `<textarea`.
- Passed: `node tests/browser/prompt_card_like.mjs` (local Chrome): no text field in the page; a short prompt is whole with no "Show all"; a 40-line prompt is cut, Copy copies all 40 lines, "Show all" expands and "Show less" cuts again; a copy that fails leaves the prompt selected with the by-hand message; Like, search markup and the Copy button's position and size as before.
- Looked at: the card in all 11 themes at 390px and 1280px, the cut prompt and the whole page in Gloss, and the site's own three prompts.
- Not run: full suite (maintainer runs it); Firefox and Safari (the fade is a CSS mask, widely supported; without it the cut is a plain edge); the page without script by eye (the markup is the full text with Copy and "Show all" hidden); the live site, which needs the deploy.

## Outcome

Delivered as scoped. "Show all" takes each theme's ordinary button look. Next action: maintainer merges and deploys; the staged cycle continues with versions and Edit.

## Final review

Diff: `templates/partials/agent_prompt.php`, `templates/pages/agent.php` (script), `public/assets/agent.css`, `tests/PresentationProfileMatrixTest.php`, the browser test and its fixture (a long prompt), this record, the plans index and the ledger. The field-sizing fallback and select-on-focus code are removed with the field. No debug findings beyond the screenshot note.

Commit/merge/publication status: completion commit pending at time of writing; pushed with a PR; not merged or deployed.

Attribution: Claude (claude-fable-5-2), 2026-11-16 UTC.
