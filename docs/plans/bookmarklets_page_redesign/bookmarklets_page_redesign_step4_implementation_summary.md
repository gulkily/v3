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
