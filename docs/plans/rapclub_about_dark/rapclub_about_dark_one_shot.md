# MIT Rap Club: socials-only About page and Underground Dark theme

Cycle: `v3/rapclub_about_dark`; Mode: one-shot; Record/index: this file, [plans index](../README.md). Follows [Underground theme](../underground_theme/underground_theme_one_shot.md) (merged in PR #386).

Debug mode: enabled (project marker `.railspike_debug_on`); [debug log](./rapclub_about_dark_debug_log.md)

Effort study: `railspike-effort`, protocol P2, reference R1; [cycle ledger](../effort_ledger/rapclub_about_dark.md)

Extended estimates: disabled

**Cycle estimate: 0.25 (intake)**

Status: implemented; tests pass; visual check not done (browser unavailable); PR pending; next permitted action: maintainer review.

## Original request

2026-11-23, in chat: "let's continue developing the mit rap club site. get rid of most of the about page, just leave the socials. make a dark version of the mit rap club theme."

Feedback items: none match (searched `docs/feedback/items/` for rap club, rapclub, mitrapclub, underground).

## Authorization

A direct instruction with a clear boundary: two changes on the `mitrapclub` profile, carried to an open PR. Excluded: other profiles' About pages, changes to the older dark `mitrapclub` theme's look, hosting, merge and deployment.

Interpretation: "the mit rap club theme" is read as Underground, the flyer theme that is now the club's default. The theme labelled "MIT Rap Club" is already dark, so a dark version of it would mean nothing new.

## Outcome and work type

Application change.

1. `/about/` on `mitrapclub` shows only the "Find us" section (YouTube, Instagram, Facebook). The visually hidden page heading stays.
2. New theme **Underground Dark** (`undergrounddark`): the same type, halftone, grain and streaks as Underground, on a dark ultramarine-black page. It is Underground's light/dark partner in the theme menu and is offered wherever Underground is (`mitrapclub`, `zenmemes`).

Allowed paths: `templates/pages/about.php`, `ProfilePresentationContent`, `PresentationSlotRegistry`, `SiteProfileRegistry`, `ThemeRegistry`, `public/assets/theme-underground.css`, affected tests, this folder, the plans index and the ledger.

## Approach and risks

- About: add a `socials` choice to the existing `about` presentation slot (which already switches chouse's extra section) and select it for `mitrapclub`. The template renders only the socials section for that choice. The club's now-unused About copy is removed from its editorial entry; `about()` defaults missing fields to empty.
- Dark theme: borrow Underground's stylesheet (`'stylesheet' => 'underground'`), as Gloss Dark borrows Gloss. Shared rules move to `:root:is([data-theme="underground"], [data-theme="undergrounddark"])`; the dark entry sets its own colour variables and the few rules that hard-code light colours (card shadow, nav pill fill, halftone layer).
- Risk: the halftone layer multiplies onto light paper. On a dark page multiply makes it invisible. Mitigation: for the dark theme, invert the layer's logic (dots light on dark, `screen` blend). Check by screenshot before calling it done.
- Contrast: compute for text on card and page; AA for body text.

## Reuse decisions

- About: `PresentationSlotRegistry` `about` slot, `templates/pages/about.php` socials section and `about.css` unchanged; no new template.
- Theme: `ThemeRegistry` `stylesheet` borrowing and `COUNTERPARTS` pairing (pattern: `theme-gloss.css`); no new file. Underground uses no nav icons, so `theme-nav-icons.css` is untouched.

## Completion conditions

`/about/` on `mitrapclub` renders only Find us; other profiles' About pages unchanged. Underground Dark appears next to Underground in the menu on `mitrapclub` and `zenmemes`, paints at first paint, is readable at 380px and 1280px, and keeps a visible focus ring. Release condition: an open PR.

Concepts touched: [page theme](../../concepts/page_theme.md), used unchanged.

## Verification and recovery plan

`./v3 test --smoke ProfilePresentationContentTest PresentationSlotRegistryTest SiteProfileRegistryTest ThemeRegistryTest ThemeCatalogPageTest PreferencesPageTest LocalAppSmokeTest NavIconSetsTest`; full suite not run. Local server with `FORUM_SITE_ID=mitrapclub`: Chrome screenshots of `/about/` and of `/threads/` and a thread in Underground Dark at 1280px and 380px. Recovery: set the `about` slot back to `default`; drop the registry entry.

Starting revisions and branch: `origin/main` `93295c828`; branch `feat/rapclub-about-and-dark-theme`.

Planning commit: `c6e079326` (record, debug log and index only).

## Dated execution history

- 2026-11-23: record written; planning commit `c6e079326`.
- 2026-11-23: About: `socials` added to the `about` slot and chosen for `mitrapclub`. `about()` returns `socialsOnly` and, for that variant, empty copy; the template gates the intro/community/hackable and graph/participation/portable sections, so the Find us markup stays in one place. The club's About-only copy (intro, cypher history, graph, participation, portable) was removed from its editorial entry; docs and busy-page copy stay.
- 2026-11-23: Underground Dark: registry entry borrowing `theme-underground.css`; Underground and Underground Dark are now mutual counterparts (one menu row). The older `mitrapclub` theme still points to Underground one-way and moves to the unpaired list beside Ochre. Shared rules now use `:root:is([data-theme="underground"], [data-theme="undergrounddark"])`; colours the shared rules hard-coded became `--ug-*` variables; fonts moved to one shared block. Dark-only rules: its variables, swatch, menu row, and the halftone layer flipped with `invert(1) hue-rotate(180deg)` and `screen` blend.
- 2026-11-23, limitation: headless Chrome and Chromium both hang on a screenshot of a blank `data:` page on this machine (they worked earlier the same day), so no screenshots were taken. The halftone flip in particular is unchecked by eye.

## Verification

- `./v3 test --smoke ProfilePresentationContentTest PresentationSlotRegistryTest SiteProfileRegistryTest ThemeRegistryTest ThemeCatalogPageTest PreferencesPageTest LocalAppSmokeTest NavIconSetsTest ThreadThemeTest`: 521 run, 517 passed, 4 failed. The 4 (`AgentPostingApiTest` x2, `SingleRequestHttpTest::testUnicodeRoundTripAndSymbolRejection`, `SignedPostBundleValidatorTest::testStrictSubsetAndExactBytes`) fail the same way on clean `origin/main` `93295c828`; unrelated. Full suite not run.
- Local server, `FORUM_SITE_ID=mitrapclub`: `/about/` HTML has only the `social-links` section; the board's allow-list includes `undergrounddark`, its stylesheet map points to `theme-underground.*.css`, and the menu has its option.
- Contrast (WCAG, dark): ink on card 12.51, link blue on card 6.67, blue on page 7.40, graphite meta on card 7.36, button text on blue 7.75, error on card 7.52.
- Not verified: rendering in a browser (see limitation above), 380px layout, focus ring appearance. Next action: open `/threads/` with Underground Dark on a phone and a desktop and check the halftone moon and grain.
- Recovery: set the `mitrapclub` `about` slot back to `default` (and restore the copy from git); remove the `undergrounddark` registry entry.

## Outcome

Delivered: socials-only About on `mitrapclub`; Underground Dark theme on `mitrapclub` and `zenmemes`. Not done: browser visual check. Not in scope: making Underground Dark what Auto shows on dark devices for the club (`autoThemes`), which would be a small follow-up if wanted.

## Final review

Diff checked: no duplicated markup (socials section rendered once), one stylesheet for both themes, files within the allowed paths.

## Study history

- 2026-11-23 UTC, intake: **0.25**. Claude, single exposed rater.
- 2026-11-23 UTC, closeout: scope unchanged; assessment **0.25**. Browser outage cost time but not scope. Maintainer judgment pending; measured time unknown.

Attribution: Claude · 2026-11-23 UTC · Model: claude-opus-5-5

Commit/merge/publication status: planning `c6e079326`, implementation `903fc509f`, pushed to `feat/rapclub-about-and-dark-theme`, [PR #389](https://github.com/gulkily/v3/pull/389) open; not merged or deployed.
