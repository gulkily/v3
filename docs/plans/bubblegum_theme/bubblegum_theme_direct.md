# Bubblegum theme — direct work

Mode: Direct (no Railspike cycle). Branch `feat/bubblegum_theme` from `origin/main` at `1936c60f3`.

## Request

2026-11-13, maintainer in a Claude chat session, with four screenshots of a pink pixel-desktop Instagram story: "Can we please make a theme based on this aesthetic … for v3 and zenmemes."

Feedback items: none matched (searched `docs/feedback/items/` for pink, pixel and theme requests).

## Outcome

A new explicit light theme, Bubblegum (`bubblegum`), in the theme menu and cycle after Sticker, permitted on every site profile like the other shared themes. It takes the screenshots' look, not their characters or branding:

- a bubblegum gradient page tiled with faint white pixel hearts, sparkles and window shapes;
- every card drawn as a little retro window: berry outline, hot-pink striped title bar with three square buttons, hard offset shadow (the visitor intro's Hide button becomes the window's close box);
- pill nav links and inputs, chunky square uppercase monospace buttons with a press-in shadow, primary (submit) buttons in hot pink;
- a white bubble-letter wordmark with a berry outline, butter-yellow accents for unread, pinned, target and focus;
- on phones the bottom tab bar is one flat white strip with a berry top edge.

## Reuse decisions

- Registry and permission lists: `ThemeRegistry::all()` and each profile's `permittedThemes`, as Sticker. No counterpart in the other mode yet.
- Stylesheet: new `public/assets/theme-bubblegum.css`, structured like `theme-sticker.css` (token block, swatch, theme-menu option, then element rules with the same button exclusions). No shared stylesheet fits: Gloss's rounded-card rules are the opposite shape.
- Page background: the shared `--body-background` token painted by `body::before`; the theme overrides only that layer's repeat and size so the pattern tiles.
- Icons: none. Bubblegum stays out of both nav icon sets (text nav, like Sticker), so `theme-nav-icons.css`, `site.css` and `tools.css` theme lists are unchanged.
- Fonts: system only (`ui-rounded` first); no new third-party assets.

## Checks

- `./v3 test --smoke ThemeRegistryTest SiteProfileRegistryTest NavIconSetsTest LocalAppSmokeTest`: 460 run, 460 passed. Full suite not run.
- `python3 -I tests/theme_contrast_audit.py`: `bubblegum OK` (lowest pair `--active-ink` on `--active-bg` 5.9).
- Headless Chrome at 1280px and 390px under Bubblegum: board, composer, users, about, Tools. No page errors, no horizontal overflow; screenshots inspected.
- Not checked: a thread page with posts (the fixture has none), the open theme menu, Safari/iPhone rendering of `ui-rounded`, production.

## Deployment note

Baked thread, profile and tag pages carry the pre-paint theme allow-list. After deploy, run `php scripts/build_static_artifacts.php` or those routes fall back to the default theme for Bubblegum readers.

## Follow-up: window texture (2026-11-13)

After the merged theme was live, the maintainer asked for "a little texture to the dialogues". Cards now carry a faint pink pixel dither (2px dots on a 6px diagonal grid, 13% opacity) over a white-to-blush fade, layered in the same `.card` rule; `--panel` stays white, so the contrast audit is unchanged (`bubblegum OK`). Checked in headless Chrome at 1280px and 390px on board and about: text stays clear, no errors or overflow. Smoke set not rerun: CSS-only change to one theme's stylesheet.
