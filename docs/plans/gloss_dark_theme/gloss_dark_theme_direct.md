# Gloss Dark theme — direct work

Mode: Direct (no Railspike cycle). Branch `feat/gloss_dark_theme` from `origin/main` at `7afe0d6a0`.

Status: implemented and verified locally, uncommitted, awaiting the maintainer's review.

## Request

2026-11-09, maintainer in a Claude Code session: "Can we please build a theme to mirror Gloss but for dark mode?"

Feedback items: none matched (searched `docs/feedback/items/` for Gloss and dark requests; PF-20261030-F46 and PF-20261104-F13 cover Gloss itself).

## Outcome

A new explicit theme, Gloss Dark (`glossdark`, mode `dark`), in the theme menu and cycle right after Gloss, permitted on ZenMemes. It has Gloss's rounded cards, pill controls, sans-serif face and smooth-line icons on a deep violet page, and the same four colour choices (Violet, Blue, Teal, Rose) in dark colours, with its own saved choice.

## Reuse decisions

- **Stylesheet.** `'stylesheet' => 'gloss'` on the registry entry, the pattern Engagify uses, instead of a copied `theme-glossdark.css`. Every shared Gloss rule in `public/assets/theme-gloss.css` now lists `glossdark`; the Gloss Dark section sets only variables, the swatch, the palette picker and the icon filter. A future fix to Gloss's shapes reaches Gloss Dark automatically.
- **Palettes.** The existing palette mechanism (`'palettes' => self::GLOSS_PALETTES`): picker markup, pre-paint restore and storage come from `templates/partials/theme_menu.php`, `templates/layout.php` and `theme_toggle.js` unchanged.
- **Icons.** The smooth-line set in `public/assets/theme-nav-icons.css` lists `glossdark` alongside Gloss and Engagify; no new SVGs. The glyphs are drawn for a light page (violet stroke, lavender fill), so Gloss Dark applies `filter: invert(1) hue-rotate(180deg)` (light stroke, dark fill, same hue), combined with each palette's hue turn, to the same elements Gloss's palettes already filter.
- **Other theme lists.** `public/assets/site.css` (app-shell account icon and More label) and `public/assets/tools.css` (Bookmarklets launcher fallback icon) exclude icon themes by name; `glossdark` added.

## Checks

- `./v3 test --smoke ThemeRegistryTest NavIconSetsTest LocalAppSmokeTest`: 442 run, 438 passed, 4 failed — the four long-standing failures (`AgentPostingApiTest` ×2, `SingleRequestHttpTest`, `SignedPostBundleValidatorTest`, failing 25 runs since 2026-11-07, local PCRE `\p` property issue). Full suite not run.
- `python3 tests/theme_contrast_audit.py`: `glossdark OK`.
- `node tests/browser/gloss_palettes.mjs`, extended to Gloss Dark: each palette at 390px and 1280px applies, survives reload and navigation, passes the contrast pairs (it caught `--ink-soft` on teal `--active-bg` at 3.92, fixed by darkening the three non-violet highlight colours), sets `color-scheme: dark`, filters the icons, has no horizontal scroll, and Escape returns focus; Gloss and Engagify behaviour unchanged. PASS.
- Headless Chrome sweep at 1280px and 390px under Gloss Dark: board, composer, about, users, account key, Tools, open theme menu. No page errors, no horizontal overflow, Gloss's font applied; screenshots inspected. The fixture has no threads, so a thread page was not looked at.
- Not checked: headed Chrome (scrollbars; Gloss sets none), production.

## Deployment note

Baked thread, profile and tag pages carry the pre-paint theme allow-list. After deploy, run `php scripts/build_static_artifacts.php` or those routes fall back to the default theme for Gloss Dark readers.
