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

After the merged theme was live, the maintainer asked for "a little texture to the dialogues". A first pass (a faint pink pixel dither) clashed with the font and hurt readability; he asked instead for large, soft, wavy lines, plus a little sparkle, and to reflect that the inspiration is God-oriented.

- Windows: one large, pale pink wave every 120px (12px stroke, 8% opacity) with a few soft four-point sparkles, over a white-to-blush fade, and a warm butter-yellow light falling from the top of each window. The tile is 960px wide and repeats only vertically, so no window is wide enough to show a seam; an earlier 320px tile left hairline seams where the stroke crossed tile edges.
- Faith touches: pixel crosses join the hearts in the page pattern, and big pixel sparkles twinkle between them. A small gold sparkle sits after the wordmark (decorative `::after`, no text).
- `--panel` stays white, so the contrast audit is unchanged (`bubblegum OK`).

Checked in headless Chrome at 1280px and 390px on board and about: text stays clear, no seams, no errors or overflow. Smoke set not rerun: CSS-only change to one theme's stylesheet.

Same day, the maintainer asked for more subtlety: keep the background crosses, drop the cross from the title bars. Removed; the title bar is stripes and squares again.

## Follow-up: texture on the other white areas (2026-11-13)

From an iPhone screenshot of the board, the maintainer asked for "some texture to the other white areas". The windows already had it; the plain white boxes did not.

- Lists (`.thread-rows`: board, pinned, Unread, Messages, search) get the windows' waves and blush fade. The wave tile moved into one token, `--bubblegum-waves`, shared by windows, lists and the phone tab bar. Lists start half a wave offset so stacked boxes don't line up into one stripe.
- The phone bottom tab bar gets one wave over a white-to-blush fade.
- Inputs and text areas get a faint blush fade only, with no pattern behind typed text.
- `--panel` is still white, so the contrast audit is unchanged.

Checked in headless Chrome at 390px against a static copy of the live board, before and after. Not checked: desktop width, Safari/iPhone, production.

## Follow-up: cute icons (2026-11-14)

The maintainer asked for "some cute icons" in Bubblegum. Bubblegum becomes an icon theme with its own third set, the "Cute set" at the end of `public/assets/theme-nav-icons.css`: 16px pixel art in Bubblegum's palette (berry outlines, pinks, butter, lavender, mint) with little hearts, sparkles and faces, covering every destination the other two sets cover (main nav, board filters, Tools bar and launcher, group headings, bookmarklet kinds, account menu and phone More).

Reuse: the shared sizing and phone tab-bar rules gained `bubblegum` in their theme lists; the glyph rules were generated from the smooth-line set's selectors so the destinations match one for one. The icon-theme lists in `site.css` (account menu) and `tools.css` (bookmarklet fallback) gained it too. `NavIconSetsTest` now checks the cute set against the pixel-art set; `tests/browser/tools_launcher.mjs` includes Bubblegum among its icon themes. AGENTS.md, the theme navigation icons concept and the theme guide name the third set.

Checks: smoke set (includes `NavIconSetsTest`) 451/451; `tests/browser/tools_launcher.mjs` passes with Bubblegum (icons on every tool, bar link and heading, no overflow); headless Chrome at 1280px and 390px on board, Tools and Bookmarklets, no page errors; a 4x preview sheet of every glyph inspected. Full suite not run. Not checked: iPhone Safari, production.

## Follow-up: control panel, button texture, board row (2026-11-14)

Maintainer asks after the icons shipped: the board's All / Liked / Newest / Tags row wrapped to two lines on his iPhone; buttons could use texture; Tools could look like "a cute little control panel"; the phone More menu could look better.

- Board row: on phones Bubblegum tightens the toolbar gaps and pill padding. Measured at 375px: 340px of controls in a 343px row (was 359px), one line at 375 and 390.
- Texture: pills, New post, the account trigger and buttons carry faint candy stripes under a soft top shine; active and submit (hot pink) use white stripes and a dimmer shine.
- Tools: a grid of chunky keypad tiles (32px icon over the label, 16px corners, pressable bottom shadow, a mint status light in the corner), pastel per tool from the existing `data-tone`, dotted pink rules under group headings. Two or more columns on phones.
- Phone More panel: the same tiles, three to a row, cycling pink / lavender / butter / mint, the current page in hot pink with a butter light. This also makes the panel short enough that the theme picker is in view without scrolling, in Bubblegum only; other themes still have the long list (a general fix is separate).

Reuse: Bubblegum-scoped rules only, in `theme-bubblegum.css`; icons are the cute set's glyphs scaled 2x with `image-rendering: pixelated`. Checks: `tests/browser/tools_launcher.mjs` passes (whole labels, no overflow, icons) across its widths; contrast audit OK; headless Chrome at 375 and 1280 on board, Tools and the open More panel. Smoke set not rerun (CSS-only, one theme). Not checked: logged-in More panel with its full item list, iPhone Safari.
