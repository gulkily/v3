Cycle: `v3/spearmint_icons`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/spearmint_icons` — [host ledger](../effort_estimation_trial.md#cycle-v3spearmint_icons); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.12**
Status: implemented and verified locally; next permitted action: push and PR (merge and deploy need the maintainer)

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

`98b927b` (confirmed with Git).

## Study history

- 2026-11-15T04:58Z intake: whole-cycle projection **0.12**, scope v1 as above, R1, P2, single exposed rater Claude (claude-opus-5-5); rater built the related cycles; no pristine intake.
- 2026-11-15T04:58Z execution entry: reaffirmed **0.12**.

## Dated execution history

- 2026-11-15T04:58Z: PR #253 is open; this is a separate branch from `origin/main` so either can merge first. No matching feedback item.
- 2026-11-15T05:05Z: added `spearmint` to all 81 smooth-line glyph selectors and all 61 eight-theme sizing lists in `theme-nav-icons.css`, the two `:not(...)` lists in `site.css` and the `:not` chain in `tools.css`; filter `hue-rotate(-88deg) saturate(1.2) brightness(0.8)` gives deep teal lines on a mint fill. Updated `NavIconSetsTest`, `tools_launcher.mjs` and AGENTS.md.
- 2026-11-15T05:10Z: addition after screenshots: icons on active teal pills (active nav, board filter, phone tab) were teal on teal; they now flip with `invert(1) hue-rotate(180deg)`, as Gloss Dark flips the set.

## Study closeout

Intake 0.12; execution entry reaffirmed 0.12; **retrospective 0.12**. Single exposed rater Claude (claude-opus-5-5), R1/P2. Maintainer acceptance pending; no accepted-count increment.

## Outcome

Delivered: Spearmint shows the smooth-line icons in teal on every icon destination, light-on-teal on active pills, and the phone tab bar's icon-over-label layout. No new glyphs.

## Verification

- `./v3 test --smoke NavIconSetsTest ThemeRegistryTest`: 466 passed; `./v3 test NavIconSetsTest` rerun after the active-state rule: passed.
- `node tests/browser/tools_launcher.mjs <dir> spearmint,gloss` (Chromium 1243): pass, icons present on tools and headings, no overflow; Gloss unchanged.
- Screenshots of board, thread, compose, Tools, More panel and theme menu at 1280px and 390px: no page errors or overflow; icons checked by eye.
- Full suite not run. Not checked: Firefox, WebKit, physical phones, production; the combination with PR #253's stylesheet (independent files; expected to compose).

## Final review

Mechanical list additions plus two filter rules; no glyph duplication. Theme menu rows untouched.

## Commit status

Planning commit `98b927b`; completion commit follows on `feat/spearmint_icons`. Merge and deploy are not covered by this cycle's authority.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
