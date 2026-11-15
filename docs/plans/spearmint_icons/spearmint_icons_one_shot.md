Cycle: `v3/spearmint_icons`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/spearmint_icons` — [host ledger](../effort_estimation_trial.md#cycle-v3spearmint_icons); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.12**
Status: planned; next permitted action: implement

Related: [`v3/spearmint_theme`](../spearmint_theme/spearmint_theme_one_shot.md) (PR #252, merged); [`v3/spearmint_bold`](../spearmint_bold/spearmint_bold_one_shot.md) (PR #253, open). Independent of #253: this branch does not touch `theme-spearmint.css`.

## Original request

"Amazing, can you please add icons" (maintainer, chat, 2026-11-14, after PR #253 for the bolder Spearmint).

## Authorization

The request above authorizes a one-shot cycle giving Spearmint navigation icons, pushing a new branch and opening a PR. Excluded: new glyphs, other themes' icons, merging and deploying.

## Outcome and work type

Application change. Normal entry: choosing Spearmint in zenmemes's theme menu. Outcome: Spearmint shows a 16px icon on every destination the other icon themes cover (main nav, board filters, New post, account/More, Tools launcher buttons and group headings, the Tools bar, phone tab bar icons above labels), in teal instead of Gloss violet.

## Approach and risks

Spearmint joins the existing smooth-line set (Gloss, Gloss Dark, Engagify): add `spearmint` to the set's glyph selectors and to the shared sizing lists for icon themes in `theme-nav-icons.css`, `site.css` and `tools.css`, and recolour the glyphs with a `hue-rotate` filter, as Gloss's teal palette already does (`theme-gloss.css`). The rounded line glyphs suit Spearmint's soft shapes.

- Risk: hue-rotate alone gives Gloss-teal, lighter than Spearmint's deep teal. Mitigation: tune the filter (hue, saturation, brightness) against screenshots.
- Risk: a missed sizing list leaves an empty or unsized slot. Mitigation: replace every list mechanically; `NavIconSetsTest` and the Tools browser check cover glyphs and icon presence.
- Risk: merge conflict with PR #253. Mitigation: the filter lives in `theme-nav-icons.css`, not `theme-spearmint.css`.

## Reuse decisions

Reuses the smooth-line glyphs and the Gloss palette filter approach; no new SVGs. Updates: `public/assets/theme-nav-icons.css`, `public/assets/site.css`, `public/assets/tools.css`, `tests/NavIconSetsTest.php` (set constant and comment), `tests/browser/tools_launcher.mjs` (icon themes), AGENTS.md's icon-set list.

## Completion conditions

With Spearmint chosen: icons on desktop nav, New post, You, board filters, Tools launcher and headings, Tools bar; phone tab bar shows icons above labels; More panel links show icons; no overflow.

## Concepts touched

Theme, navigation icon set (used unchanged). No inventory change.

## Verification and recovery plan

- `./v3 test --smoke NavIconSetsTest ThemeRegistryTest`; `node tests/browser/tools_launcher.mjs <dir> spearmint,gloss` (gloss as the unchanged-set regression check); full suite not run.
- Screenshots of board, Tools and the phone More panel at 1280px and 390px.
- Not checked: Firefox, WebKit, physical phones, production.

## Starting revisions and branch

Host `origin/main` at `6908aa420cf0db95ab67b1fa8de60202ac89adf0`; branch `feat/spearmint_icons`.

## Planning commit

Pending (this commit).

## Study history

- 2026-11-15T04:58Z intake: whole-cycle projection **0.12**, scope v1 as above, R1, P2, single exposed rater Claude (claude-opus-5-5); rater built the related cycles; no pristine intake.
- 2026-11-15T04:58Z execution entry: reaffirmed **0.12**.

## Dated execution history

- 2026-11-15T04:58Z: PR #253 is open; this is a separate branch from `origin/main` so either can merge first. No matching feedback item.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
