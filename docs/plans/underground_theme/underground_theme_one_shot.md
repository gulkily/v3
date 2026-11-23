# Underground theme: the MIT Rap Club look from "the underground concert" flyer

Cycle: `v3/underground_theme`; Mode: one-shot; Record/index: this file, [plans index](../README.md). Related: [MIT Rap Club website](../mit_rap_club_website/mit_rap_club_website_step4_implementation_summary.md), [de-genericize checklist](../mitrapclub_theme_and_features_checklist.md).

Debug mode: enabled (project marker `.railspike_debug_on`); [debug log](./underground_theme_debug_log.md)

Effort study: `railspike-effort`, protocol P2, reference R1; [cycle ledger](../effort_ledger/underground_theme.md)

Extended estimates: disabled

**Cycle estimate: 0.30 (intake)**

Status: implemented and verified locally; PR pending; next permitted action: maintainer review.

## Original request

2026-11-23, in chat, with the club's flyer for "the underground concert" (Dec 4, 6–9 pm) attached: "mit rap club needs a site and a theme asap. here's an aesthetic for the visual we should base the theme on. what is left to do on it? you may have full permissions to v3 repo"

Feedback items: none match (searched `docs/feedback/items/` for rap club, rapclub, mitrapclub).

## Authorization

"asap" plus full repo permission, with a bounded outcome: one new theme from the flyer. This record authorizes building it on a branch through an open PR. The name (Underground) is Claude's choice and easy to change. Excluded: hosting the `mitrapclub` instance (DNS, vhost, separate repository), a board hero or "what's next" callout, new fonts or other third-party assets, changes to the existing dark `mitrapclub` theme, merge and deployment.

## Outcome and work type

Application change. A new light theme, Underground: lavender paper, ultramarine ink, rounded heavy lowercase display type, grey and blue halftone smudges, film grain and scratch streaks, from the flyer. It becomes the `mitrapclub` profile's default theme (the dark `mitrapclub` theme stays selectable) and is also permitted on `zenmemes`, so `#rapclub` threads can use it with a `theme:` line before the club site is hosted.

Allowed paths: `public/assets/theme-underground.css`, `ThemeRegistry`, `SiteProfileRegistry`, theme tests, `public/assets/theme-nav-icons.css` only if a nav icon set needs it, this folder, the plans index and the ledger.

## Approach and risks

- Type without new assets: `ui-rounded` (SF Pro Rounded on Apple devices), then "Arial Rounded MT Bold", "Nunito", "Varela Round", system sans. No webfont, per the dependency policy. Impact: off Apple the display face is less rounded. Accepted for now; a self-hosted font is a follow-up needing dependency approval.
- Texture with CSS only: halftone from `radial-gradient` dot grids under a radial mask, grain from an inline SVG `feTurbulence` data URI, scratches from thin `linear-gradient` stripes, all in fixed background layers behind content. Risk: busy texture hurts reading. Mitigation: cards stay near-solid paper; texture lives in the page margins and header.
- Contrast: ultramarine on lavender must pass AA for body text; check with `scripts/theme_contrast_audit.py` if present, else compute.
- Reduced motion: no animation planned.

## Reuse decisions

Variable block, swatch and scoped selectors follow `theme-sticker.css` / `theme-mitrapclub.css` (no forked file). Registry entry uses existing fields only; no script, no flag. Profile default reuses `defaultTheme`.

## Completion conditions

Underground offered on `mitrapclub` (default) and `zenmemes`; paints at first paint; readable at 380px and 1280px; focus ring visible; other themes unchanged. Release condition: an open PR.

Concepts touched: [page theme](../../concepts/page_theme.md), unchanged.

## Verification and recovery plan

`./v3 test --smoke ThemeRegistryTest SiteProfileRegistryTest ThemeCatalogPageTest PreferencesPageTest`; local server with `FORUM_SITE_ID=mitrapclub`, Chrome screenshots at 1280px and 380px of board, a thread and about. Full suite not run. Recovery: revert the default theme line; the theme file is additive.

Starting revisions and branch: `origin/main` `2e14bcb96`; branch `feat/rapclub-underground-theme`.

Planning commit: `acc006dfd` (record, debug log and index only).

## Dated execution history

- 2026-11-23: record written; planning commit `acc006dfd`.
- 2026-11-23: `theme-underground.css` written; registry entry after `mitrapclub`; `underground` and `mitrapclub` made each other's counterparts, so the menu shows them as one light/dark row.
- 2026-11-23, discovery: the `mitrapclub` default comes from the `brandedStylesheet` slot, not `defaultTheme` (`TemplateRenderer::defaultThemeName`). Added `underground` to that slot's choices and set the profile's slot to it; `defaultTheme` changed too so the two agree. Within the recorded outcome.
- 2026-11-23: first render had solid blob cores and a smudge showing through a card; tuned blob gradients, raised card opacity to 0.95, loosened wordmark spacing. On 380px the moon covered the wordmark; added a narrow-screen layout (small moon in the top-right corner).
- 2026-11-23: halftone grey smudges print blue, not grey: one filtered layer can only threshold the blue channel the same way for every blob. Accepted (two-tone reads close to the flyer); true grey needs a second layer.
- 2026-11-23: tests updated for the zenmemes allow-list (`LocalAppSmokeTest`) and the new menu pair (`ThemeRegistryTest`).

## Verification

- `./v3 test --smoke ThemeRegistryTest SiteProfileRegistryTest PresentationSlotRegistryTest ThemeCatalogPageTest PreferencesPageTest ThreadThemeTest LocalAppSmokeTest NavIconSetsTest`: 518 run, 518 passed. Full suite not run.
- Local server, `FORUM_SITE_ID=mitrapclub`, system Chrome: `/threads/` at 1280px and 380px, a thread page at 1280px; `data-default-theme="underground"`.
- Contrast (WCAG): blue on card 6.82, blue on page 5.96, body ink on card 12.21, graphite meta on card 7.08, button text on blue 7.63.
- Limitation: this box has no rounded fonts, so screenshots show the fallback sans. iPhone/Mac get SF Pro Rounded through `ui-rounded`; Windows/Android without Nunito or Arial Rounded get the system sans.
- Recovery: set the `mitrapclub` slot and `defaultTheme` back to `mitrapclub`; the stylesheet is additive.

## Outcome

Delivered: Underground theme, default on `mitrapclub`, selectable on `zenmemes` (e.g. `theme: underground` on a `#rapclub` thread). Not done (outside this cycle): hosting the club site, board hero / next-event callout, self-hosted display font, club favicon art, real Facebook URL, enabling media-embed flags on the instance.

## Study history

- 2026-11-23 UTC, intake: **0.30**. Claude, single exposed rater.
- 2026-11-23 UTC, closeout: scope unchanged (slot wiring was a discovery inside the outcome); assessment **0.30**. Maintainer judgment pending; measured time unknown.

Attribution: Claude · 2026-11-23 UTC · Model: claude-opus-5-5
