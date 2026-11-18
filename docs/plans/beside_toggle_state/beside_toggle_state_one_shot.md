# Beside toggle state — one-shot

Cycle: `v3/beside_toggle_state`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](beside_toggle_state_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/beside_toggle_state`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/beside_toggle_state.md))
Extended estimates: enabled (no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.04** (retrospective 0.07)

Status: technically complete on `feat/beside_toggle_state`; pushed for review, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Original request, 2026-11-18T07:40Z (maintainer, in the Claude session that built [open beside](../open_beside/open_beside_step4_implementation_summary.md), after trying it): "actually, it already does it. i think we just need a better indicator of when beside button is activated, because i could not tell."

The maintainer asked directly, so this is authorized as a one-shot through to a pushed branch and an open PR.

Feedback item: none matched (searched `docs/feedback/items/` for "beside", "toggle" and "pressed"). Created [PF-20261118-F04](../../feedback/items/PF-20261118-F04.txt) in the completion commit.

## Outcome

Application change (CSS, one test). On the reading-margins ruler, the Beside button looks clearly different when the mode is on: a filled pill in the ruler's colour with a check mark before the label, where off stays an outlined pill. Readable in every theme. No change to behaviour, to the Preferences row, or to the pane.

Excluded: renaming the mode; moving the toggle; an indicator anywhere other than the toggle.

## Approach and risk

Today on is a heavier ring and a bold label on the same background, which is too close to off. On becomes: background in the ruler's colour (`--active-line`), label in the page's background colour (`--page-bg`), and a check mark before the label so the state does not rest on colour alone.

- Risk: unreadable in some theme. The first version of the toggle swapped to `--panel` for the label and was a blank white pill in Arena and Sticker, where `--panel` equals the ruler's colour. `--page-bg` is the colour the ruler's line is drawn on, so a theme whose ruler is visible has contrast between the two. Checked, not assumed: the browser test computes the contrast of the pressed label against its fill in every theme and requires 3:1, and requires the on and off fills to differ.

## Reuse

- `public/assets/open-beside.css`: the existing `[aria-pressed="true"]` rule is replaced; no new class.
- `tests/browser/open_beside.mjs`: extended inside its existing per-theme loop.
- `templates/partials/reading_margins.php` and `open_beside.js`: unchanged; `aria-pressed` already carries the state.

Concepts touched: [open beside](../../concepts/open_beside.md), unchanged in what it says.

## Completion and verification plan

- Normal entry: on a desktop page with the ruler, press Beside; the button fills and shows a check mark; press again and it returns to the outline.
- `node tests/browser/open_beside.mjs <dir>` in Chrome and `BROWSER=firefox`: on and off differ and the pressed label has at least 3:1 contrast in every theme.
- Off and on crops of the toggle in every theme, checked by eye.
- Smoke set. The full suite is left to the maintainer.
- Recovery: none needed; a style change only.

Starting revision and branch: `07a6a652` on `main`; branch `feat/beside_toggle_state`.

## Planning commit

`2ca69c80` (confirmed with Git).

## Study history

- 2026-11-18T07:40Z intake: projection **0.04**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2). Not pristine: the rater wrote the code being changed.
- 2026-11-18T07:54Z execution entry after the planning commit: 0.04 reaffirmed.
- 2026-11-18T07:54Z closeout: retrospective **0.07**. Two themes defeated the planned colours and one theme's hover effect had to be worked around; scope v1 unchanged. Maintainer fit and acceptance pending.

## Execution history

- 2026-11-18T07:40Z: request received in session; record written.
- 2026-11-18T07:54Z: correction to the approach. The planned colours (`--active-line` fill, `--page-bg` label) failed the contrast check in Sticker, where both are white. On is now the off state's own two colours swapped: a layer of the button's ink under the label, and the label in the page's background colour. The fill is a layer (`::before`) because Sticker sets button backgrounds with a selector that outranks the toggle's.
- 2026-11-18T07:54Z: found and fixed on the way: in Sticker, hovering the toggle threw it out from under the pointer, so it could not be clicked reliably. The theme moves buttons with a transform on hover, which replaced the transform that centred the toggle. The toggle is now centred with auto margins. This was in #329.
- 2026-11-18T07:54Z: also moved the `v3/open_beside` ledger entry out of the frozen `effort_estimation_trial.md`, where #329 had appended it by mistake, into `effort_ledger/open_beside.md`, and repointed that cycle's step documents.

## Outcome

Delivered: the Beside button is an outlined pill when off and a filled pill with a check mark and a bold label when on, in all 18 themes. The label sits in a new `<span class="reading-margins__beside-label">`. No behaviour changed.

Limitations: in Chicago and Word97 the pressed label is dimmer than elsewhere (above 3:1, below the others).

## Verification

- `node tests/browser/open_beside.mjs <dir>` in Chrome and `BROWSER=firefox`: pass. New checks in every theme: on and off fills differ; the label has at least 3:1 contrast in both states; on shows a check mark and off does not; the toggle stays under the pointer when hovered.
- Off and on crops of the toggle in all 18 themes, checked by eye on one sheet.
- `node tests/browser/reading_margins.mjs` and `preferences.mjs`: pass.
- `./v3 test --smoke PreferencesPageTest PresentationProfileMatrixTest`: 488 run, 488 passed.
- Not verified: the full suite (the maintainer runs it), real Firefox outside automation, production.

## Final review

Diff limited to `open-beside.css`, the label span in `reading_margins.php`, the browser test, and this cycle's documents plus the ledger move. [Open beside concept](../../concepts/open_beside.md) still reads true.

*Claude (claude-fable-5-2), 2026-11-18*
