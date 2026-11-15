Cycle: `v3/spearmint_bold`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/spearmint_bold` — [host ledger](../effort_estimation_trial.md#cycle-v3spearmint_bold); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.20**
Status: implemented and verified locally; next permitted action: push and PR (merge and deploy need the maintainer)

Predecessor: [`v3/spearmint_theme`](../spearmint_theme/spearmint_theme_one_shot.md), merged in PR #252.

## Original request

"Right now this theme is at a 3.5, can you turn it up to 8 please" (maintainer, chat, 2026-11-14, about Spearmint after PR #252 merged).

## Authorization

The request above, following the maintainer's earlier "please pr" for the same theme, authorizes a one-shot follow-up restyling Spearmint, pushing a new branch and opening a new PR. Excluded: other themes, shared CSS, markup, scripts, nav icon sets, other sites, merging and deploying.

## Outcome and work type

Application change (one theme stylesheet). Normal entry: choosing Spearmint in zenmemes's theme menu. Outcome: Spearmint reads as bold and playful rather than restrained, while text stays readable (WCAG AA for body text and labels):

- a deeper, more saturated mint page with larger leaf sprigs and fizz bubbles;
- cards with thicker teal outlines, a teal-and-white diagonal "gum wrapper" stripe across the top, a white frost halo and a deeper shadow;
- a bigger wordmark with a coral drop under the teal outline, and a larger leaf;
- a double wave (teal over coral) under the header;
- coral primary buttons (Post, submit) with dark ink, glossy candy shine on buttons and pills, bouncier hover and press;
- coral-tinted heading shadows, thread-row hover tint, pastel tiles in Tools and the phone More panel, a teal scrollbar.

Recovery: unchanged; a reader leaves through the theme menu.

## Approach and risks

Edit `public/assets/theme-spearmint.css` only.

- Risk: contrast on the darker page (labels like PINNED, empty-state text use `--ink-soft`). Mitigation: darken `--ink-soft` to `#1d5a55` and keep the page's lightest-to-darkest stops between `#c9f3e2` and `#8fe0c4` (ink-soft on the darkest stop 5.15:1); white on active teal 5.19:1; dark ink on coral 5.95:1.
- Risk: the wrapper stripe colliding with card content. Mitigation: add the stripe's height to the card's top padding.
- Risk: hover motion. Keep `prefers-reduced-motion` handling.

## Reuse decisions

Same selectors and variable contract as the current `theme-spearmint.css`; Bubblegum's pastel tile pattern (`nth-child` background colours on `.account-menu__links .nav-link`) reused for the More panel and Tools; the scrollbar block follows the theme guide's `html[data-theme]` rule (`docs/runbooks/theme_development_guide.md`, as in `theme-word97.css`). No new components.

## Completion conditions

Choosing Spearmint shows the bolder look on board, thread, compose, Tools, the phone More panel and the theme menu at desktop and phone widths, with no overflow, no page errors and readable text.

## Concepts touched

Theme (used unchanged). No inventory change.

## Verification and recovery plan

- `./v3 test --smoke ThemeRegistryTest NavIconSetsTest`; `node tests/browser/tools_launcher.mjs <dir> spearmint`; full suite not run.
- Screenshots of board, thread list, a thread, compose, More panel and theme menu at 1280px and 390px, checked by eye; contrast of the changed colour pairs computed.
- Not checked: Firefox, WebKit, physical phones, production.

## Starting revisions and branch

Host `origin/main` at `6908aa420cf0db95ab67b1fa8de60202ac89adf0` (includes PR #252); branch `feat/spearmint_bold`.

## Planning commit

`dc2b916` (confirmed with Git).

## Study history

- 2026-11-15T04:53Z intake: whole-cycle projection **0.20**, scope v1 as above, R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: rater built the predecessor cycle; no pristine intake.
- 2026-11-15T04:53Z execution entry: reaffirmed **0.20**.

## Dated execution history

- 2026-11-15T04:53Z: PR #252 is merged, so per AGENTS.md this is a new branch from fresh `origin/main` and a new PR, not a push to `feat/spearmint_theme`. No matching feedback item.

## Study closeout

Intake 0.20; execution entry reaffirmed 0.20; **retrospective 0.20**. Single exposed rater Claude (claude-opus-5-5), R1/P2. Maintainer acceptance pending; no accepted-count increment.

## Outcome

Delivered: deeper mint page with leaf sprigs and fizz bubbles; 3px-outlined cards with a teal-and-white wrapper stripe, frost halo and deep shadow; thread lists to match, with dotted dividers and a hover tint; a bigger outlined wordmark with a coral drop and a leaf that tips on hover; a teal-over-coral double wave under the header; glossy pills and buttons on a solid teal base that bounce and squish; coral Post, submit and New post buttons with dark ink; coral pinned marker and selection; pastel Tools and phone More tiles; a wrapper stripe on the phone tab bar; teal scrollbars. Only `theme-spearmint.css` changed.

## Verification

- `./v3 test --smoke ThemeRegistryTest NavIconSetsTest`: 466 run, 466 passed.
- `node tests/browser/tools_launcher.mjs <dir> spearmint` (Chromium 1243): pass, no overflow at 1280px and 390px.
- Ad hoc screenshots (private-message browser fixture) of board, thread list, a thread, compose, More panel and theme menu at 1280px and 390px: no page errors, no horizontal overflow, checked by eye. Computed contrast: ink-soft on the darkest page stop 5.15:1, white on active teal 5.19:1, dark ink on coral 5.95:1.
- Full suite not run. Not checked: Firefox, WebKit, physical phones, production.

## Final review

One stylesheet changed; no markup, scripts or shared CSS. Theme-menu `:not(...)` guard kept on bare button rules.

## Commit status

Planning commit `dc2b916`; completion commit follows on `feat/spearmint_bold`. Merge and deploy are not covered by this cycle's authority.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
