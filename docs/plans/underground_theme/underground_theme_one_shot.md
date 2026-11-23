# Underground theme: the MIT Rap Club look from "the underground concert" flyer

Cycle: `v3/underground_theme`; Mode: one-shot; Record/index: this file, [plans index](../README.md). Related: [MIT Rap Club website](../mit_rap_club_website/mit_rap_club_website_step4_implementation_summary.md), [de-genericize checklist](../mitrapclub_theme_and_features_checklist.md).

Debug mode: enabled (project marker `.railspike_debug_on`); [debug log](./underground_theme_debug_log.md)

Effort study: `railspike-effort`, protocol P2, reference R1; [cycle ledger](../effort_ledger/underground_theme.md)

Extended estimates: disabled

**Cycle estimate: 0.30 (intake)**

Status: planned; next permitted action: implement on `feat/rapclub-underground-theme`.

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

Planning commit: pending.

## Dated execution history

- 2026-11-23: record written.

## Study history

- 2026-11-23 UTC, intake: **0.30**. Claude, single exposed rater.

Attribution: Claude · 2026-11-23 UTC · Model: claude-opus-5-5
