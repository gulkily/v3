# MIT Rap Club: socials-only About page and Underground Dark theme

Cycle: `v3/rapclub_about_dark`; Mode: one-shot; Record/index: this file, [plans index](../README.md). Follows [Underground theme](../underground_theme/underground_theme_one_shot.md) (merged in PR #386).

Debug mode: enabled (project marker `.railspike_debug_on`); [debug log](./rapclub_about_dark_debug_log.md)

Effort study: `railspike-effort`, protocol P2, reference R1; [cycle ledger](../effort_ledger/rapclub_about_dark.md)

Extended estimates: disabled

**Cycle estimate: 0.25 (intake)**

Status: planned; next permitted action: implement.

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

Planning commit: pending.

## Dated execution history

- 2026-11-23: record written.

## Study history

- 2026-11-23 UTC, intake: **0.25**. Claude, single exposed rater.

Attribution: Claude · 2026-11-23 UTC · Model: claude-opus-5-5
