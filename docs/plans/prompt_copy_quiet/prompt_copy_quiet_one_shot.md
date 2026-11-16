Cycle: `v3/prompt_copy_quiet`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); no separate log unless something comes up
Effort study: `railspike-effort` / `v3/prompt_copy_quiet` — [host ledger](../effort_estimation_trial.md#cycle-v3prompt_copy_quiet); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.06**
Status: implemented and verified locally; pushed with a PR; merge and deploy need the maintainer

## Original request

Maintainer, in a Claude session on 2026-11-16, with three screenshots of a live prompt field (Bubblegum, Gloss, Gloss Dark) after [prompt_copy_icon](../prompt_copy_icon/prompt_copy_icon_one_shot.md) (PR #272, merged and deployed): "it looks oddly styled in gloss, gloss dark, and bubblegum, please tweak it".

## Authorization

The request itself, 2026-11-16: a bounded theme tweak to one control, carried through to a pushed branch and an open PR. Merging and deploying are not covered.

## Outcome and work type

Application change, theme CSS only. In Gloss, Gloss Dark and Bubblegum the icon-only Copy button in a prompt field loses its theme button chrome (Gloss's tinted pill with an edge; Bubblegum's block with a hard offset shadow and candy stripes), which sat awkwardly inside the field's rounded corner. It becomes a quiet icon: no border, no fill, no shadow, in the theme's soft ink, with a light fill on hover and the theme's focus ring. Tap target stays 44px.

Engagify takes the Gloss rules for controls, so it gets the same button; Spearmint recolours Gloss's icons but has its own button look and is left alone.

Excluded: other themes (their bevelled or square buttons sit fine in a square-cornered field); the Like button; any markup or script change.

## Approach and risks

Rules in `public/assets/theme-gloss.css` and `public/assets/theme-bubblegum.css`, each written as the theme's own button selector plus `.copy-prompt__button`, so they outrank the theme's button rules without `!important`. Risk: Bubblegum sets its look across four rules (base, hover, active, texture); each needs its counterpart.

## Reuse decisions

Theme overrides live in the theme stylesheets, as `.thread-reaction-button` does in both files. Colours come from each theme's existing variables (`--ink-soft`, `--gloss-accent`, `--bubblegum-berry`, `--button-bg`). No new component.

## Completion conditions

In Gloss, Gloss Dark, Engagify and Bubblegum the Copy button shows as a bare icon at the field's top right, gains a fill on hover, shows the focus ring on keyboard focus, and still shows the check after a copy. Other themes are unchanged.

## Concepts touched

Copyable prompt field: used; themed look changed in four themes. No inventory change.

## Verification and recovery plan

`./v3 test --smoke PresentationProfileMatrixTest PromptLibraryTest` plus any test that reads the two theme files; `node tests/browser/prompt_card_like.mjs` with screenshots in all themes at phone and desktop widths, and hover and copied states in the changed themes. Full suite not run (maintainer runs it).

Starting revision and branch: `b9ffa6b` (origin/main after PR #272); `fix/prompt_copy_quiet`
Planning commit: `80b7a69`

## Dated execution history

- 2026-11-16T05:16Z: no matching item in `docs/feedback/items/`.
- 2026-11-16T05:18Z: scope v2 (addition), maintainer: "also a bit odd in spearmint". Spearmint gets the same bare icon in `public/assets/theme-spearmint.css`; this replaces the v1 line above that left Spearmint alone. Estimate unchanged.
- 2026-11-16T05:30Z: implemented in the three theme files as planned.
- 2026-11-16T05:35Z: correction to the two earlier records (prompt_card_like, prompt_copy_icon): the browser test asked for theme `gloss-dark`, which is not a theme id, so those "Gloss Dark" screenshots were the fallback light look and Gloss Dark had not been seen. The test's default theme list now says `glossdark`, and Gloss Dark was looked at for real in this cycle.

## Study history

- 2026-11-16T05:16Z intake: **0.06**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2), rated after reading the two themes' button rules (no pristine intake).

- 2026-11-16T05:40Z closeout: retrospective **0.08** (Spearmint added; the theme-id correction).

## Verification

- Passed: `./v3 test --smoke PresentationProfileMatrixTest PromptLibraryTest LocalAppSmokeTest`: 480/480.
- Passed: `node tests/browser/prompt_card_like.mjs` (local Chrome), which also confirms the button keeps its corner position and 44px target.
- Looked at, at 390px: rest, hover and copied states in Gloss, Gloss Dark, Engagify, Bubblegum and Spearmint (bare icon, light fill on hover, check after copying), and the unchanged Light, Word97 and Sticker for comparison.
- Not run: full suite (maintainer runs it); keyboard focus ring by eye (the themes' focus rules are untouched); the live site, which needs the deploy.

## Outcome

Delivered: v1 plus Spearmint. Next action: maintainer merges and deploys.

## Final review

Diff: `public/assets/theme-gloss.css`, `theme-bubblegum.css`, `theme-spearmint.css`, `tests/browser/prompt_card_like.mjs` (theme id fix; hover and copied screenshots), this record, the plans index and the ledger. Debug note: a screenshot labelled with a theme name proves nothing unless the theme id is real; worth asserting `data-theme` after switching.

Commit/merge/publication status: completion commit pending at time of writing; pushed with a PR; not merged or deployed.

Attribution: Claude (claude-fable-5-2), 2026-11-16 UTC.
