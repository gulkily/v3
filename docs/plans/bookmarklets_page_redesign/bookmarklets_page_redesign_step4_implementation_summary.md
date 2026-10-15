> **Feature plan:** [Step 1](./bookmarklets_page_redesign_step1_solution_assessment.md) · [Step 2](./bookmarklets_page_redesign_step2_feature_description.md) · [Step 3](./bookmarklets_page_redesign_step3_development_plan.md) · [Step 4](./bookmarklets_page_redesign_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Audit
- Changes:
  - None to application files; selector audit and baseline screenshots only.
- Verification:
  - Shared selectors found in `public/assets/tools.css`: `.tool-launcher-list`, `-item`, `-button`, `-description`, `-description .meta`, `.bookmarklet-item`, `.bookmarklet-actions`, `.bookmarklet-copy` (+ `:focus-visible`), `.bookmarklet-feedback` (+ `:empty`), and a `max-width: 560px` media block.
  - Theme overrides: `theme-word97.css` (`.tool-launcher-button`, `:active`), `theme-qdb.css` (`.tool-launcher-button` color), `theme-nav-icons.css` (`.tool-launcher-button::before` icons for word97 and chicago, including `data-bookmarklet-kind` url/clip/selection/tweet).
  - Pages using the classes: `templates/pages/tools.php` (loads `tools.css`) and `templates/pages/bookmarklets.php` (loads `tools.css`, `tools_bookmarklets.js`). No other templates use them.
  - Baseline screenshots of `/tools/bookmarklets/` and `/tools/` captured for 12 themes (dark, light, console, chicago, word97, sticker, vapor, lcd, forge, arena, thermal, whitehot) at 1280 px and 375 px (48 images, kept outside the repo in the session scratchpad).
  - Theme-specific rules for other listed themes are not in these files; they inherit `tools.css` and theme tokens.
- Notes:
  - Chicago and word97 already render a leading icon through `.tool-launcher-button::before`; the redesign must reuse it rather than add a second glyph.
  - Because `tools.php` shares the base rules, all new rules must be scoped to a bookmarklets page class (Stage 2).

## Stage 2 - Template structure
- Changes:
  - `templates/pages/bookmarklets.php`: added `bookmarklets-page` class on the card; shortened intro to a lead line plus a separate drag hint; wrapped each variant in `section.bookmarklet-group` with heading, note, and list.
  - All `data-bookmarklet*` attributes, copy buttons, and feedback elements unchanged.
- Verification:
  - `php -l` on the template: no errors.
  - Playwright against the dev server: 7 bookmarklet links have `javascript:` hrefs and all 7 copy buttons are enabled.
  - `php tests/LocalAppSmokeTest.php`: exit 0.
- Notes:
  - No styling added yet; new elements render with existing styles until Stage 3.

## Stage 3 - Scoped type, spacing, and chip styling
- Changes:
  - `public/assets/tools.css`: appended rules scoped to `.bookmarklets-page`: lead and hint typography, divider and spacing between variant groups, larger gap between items and a small gap between button row and description, content-sized chip (min-width, centered label, grab cursor, focus ring), copy button matched to chip height with its stray top margin reset, and a mask-based drag glyph (`currentColor`, no hard-coded colors) for themes that do not already draw a leading icon.
- Verification:
  - Playwright: chip and copy button have identical top and height in all 12 themes.
  - Screenshot review of the dark theme at 1280 px: hierarchy, grouping, and drag glyph read as intended.
  - `/tools/` screenshots in all 12 themes at 1280 px and 375 px match the Stage 1 baseline except console at 1280 px, where a 3x13 px region at (993,73) in the header theme-menu differs. It is outside the launcher list and stable across repeat captures; correcting the earlier draft claim of byte-identical output.
- Notes:
  - Chicago and word97 keep their existing icon; the new glyph is excluded there.
  - Row layout and 375 px behavior are Stage 4; theme-specific fixes are Stage 5.

## Stage 4 - Responsive layout
- Changes:
  - `public/assets/tools.css`: at 640 px and wider, each bookmarklet is a row (button and copy in a 12rem column, description beside it); below 640 px items stack with the description under its button.
- Verification:
  - Playwright: no horizontal overflow at 375, 640, and 1280 px (scrollWidth equals viewport).
  - Screenshot review: light theme at 1280 px (row layout) and 375 px (stacked).
  - Copy feedback "Copied" renders fully inside the viewport at 375 and 1280 px.
- Notes:
  - On the row layout the feedback bubble briefly overlays the start of the description; acceptable since it is transient.

## Stage 5 - Theme fidelity and contrast
- Changes:
  - `public/assets/tools.css`: arena-only override for the page's hint and group-note text, derived from `--ink` at reduced opacity, because arena's `--ink-soft` (#666 on black) measured 3.66:1.
  - No other theme needed an override; chicago and word97 keep their existing icon treatment and button styling.
- Verification:
  - Screenshots of all 12 themes (dark, light, console, chicago, word97, sticker, vapor, lcd, forge, arena, thermal, whitehot) at 1280 px and 375 px captured; dark, light, chicago, vapor, and arena reviewed visually.
  - Scripted contrast probe over note, hint, chip, copy, and description text: all themes at or above 4.5:1 for text except arena notes (fixed above). The probe's chip and copy readings for vapor and arena (1 to 2) are artifacts of gradient and transparent backgrounds; the screenshots show readable labels.
  - Chip and copy button top and height match in every theme.
  - `/tools/` screenshots match baseline (only the unrelated 3x13 px theme-menu difference noted in Stage 3).
  - `php tests/LocalAppSmokeTest.php`: exit 0.
- Notes:
  - Remaining themes (console, sticker, lcd, forge, thermal, whitehot) were checked by probe and not individually reviewed by eye; Stage 6 covers a final pass.

## Stage 6 - Regression pass and release readiness
- Changes:
  - None to application files. No smoke assertions needed updating: existing checks for the nav, `tools_bookmarklets.js` fingerprint, and `data-bookmarklet-kind` still hold, and no fingerprinted asset files were left uncommitted.
- Verification:
  - `php tests/LocalAppSmokeTest.php`: exit 0.
  - Contact sheet at 375 px for console, sticker, lcd, forge, thermal, whitehot, word97, and chicago reviewed: chips read as draggable, copy buttons align, descriptions sit under their own button, no overflow.
  - Playwright: all 7 links keep `javascript:` hrefs and all 7 copy buttons enable; copy shows "Copied" inside the viewport at 375 and 1280 px.
  - `/tools/` matches the Stage 1 baseline in all 12 themes at both widths, apart from the unrelated 3x13 px theme-menu pixels in console at 1280 px. It is the only other page using `.tool-launcher-*`.
  - Actual drag onto a browser bookmarks bar was not performed (not scriptable here); the link `href` and `data-bookmarklet` hooks are unchanged from before.
  - Deployment, migration, and external-system checks: not applicable (template and CSS only).
- Notes:
  - Final commit count on the branch: 1 planning commit plus 6 stages, as planned.

## Follow-up - Copy as plain one-line JavaScript
- Changes:
  - `public/assets/tools_bookmarklets.js`: the copy button now copies `javascript:` plus the unencoded script collapsed to one physical line, instead of the percent-encoded string. The draggable link's `href` is unchanged (still percent-encoded).
  - Browser tests updated to expect the new copy format; the qutebrowser acceptance binding uses plain `jseval` rather than `jseval --url`.
- Verification:
  - `node tests/browser/bookmarklet_copy.cjs`: passes all seven variants; copied text has no newlines, no `%XX` escapes, no `;;`, equals the decoded link collapsed to one line, and runs correctly.
  - `bookmarklet_copy_qutebrowser.mjs` under Xvfb with qutebrowser: all seven entries passed with the real clipboard bound via `jseval <paste>`.
  - `bookmarklet_copy_ui.mjs` (Chromium, real clipboard, keyboard, fallbacks, themes): passed. Smoke test: exit 0.
- Notes:
  - Reported by the user: percent-encoded copy is not valid for `:jseval`, which takes raw JavaScript. A leading `javascript:` parses there as a label, so one form also works for `:open` and `jseval --url`.
  - Newline collapsing is safe only while the generated source has no line comments or template literals; the unit test compiles and runs each copied variant to catch regressions.
