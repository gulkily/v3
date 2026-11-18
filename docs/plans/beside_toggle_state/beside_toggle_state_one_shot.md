# Beside toggle state — one-shot

Cycle: `v3/beside_toggle_state`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](beside_toggle_state_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/beside_toggle_state`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/beside_toggle_state.md))
Extended estimates: enabled (no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.04**

Status: planned; next permitted action: implement and verify, then push and open a PR. Merge and deployment need the maintainer.

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

Pending.

## Study history

- 2026-11-18T07:40Z intake: projection **0.04**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2). Not pristine: the rater wrote the code being changed.

## Execution history

- 2026-11-18T07:40Z: request received in session; record written.
