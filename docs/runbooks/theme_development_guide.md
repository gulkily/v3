# Theme Development Guide

How to add a new theme or improve an existing one, based on what we learned
building the Word 97 theme (`b21b525..cb45bd7`, July 2026). Word 97 is the
most heavily customized theme in the codebase — read its scoped section in
`public/assets/site.css` as the worked example for everything below.

## Architecture: where a theme lives

A theme is one registry entry plus CSS. No JS or template changes are needed
for a basic theme.

1. **`src/ForumRewrite/View/ThemeRegistry.php`** — single source of truth.
   Add `['name' => 'x', 'label' => 'X', 'mode' => 'light'|'dark']`. Order
   here is menu order and cycle order. The registry feeds the popover
   markup, the cycle button, and the anti-FOUC allow-list in
   `templates/layout.php` — you never edit those directly.
2. **`public/assets/site.css`** — three blocks, in this order:
   - **Variable block** at the top with the other themes:
     `:root[data-theme="x"], .theme-menu__option[data-theme-option="x"]`
     setting `color-scheme` and the custom properties (`--page-bg`,
     `--ink`, `--panel`, `--button-bg`, fonts, etc.). Copy a similar theme's
     block as the checklist of properties.
   - **Swatch rule** with the other `.theme-swatch[data-theme=...]` rules:
     the little circle/square that represents the theme in the header and
     menu. Make it unmistakable at 1rem.
   - **Menu-row garnish** (optional) with the other
     `.theme-menu__option[data-theme-option=...]` rules: how the theme's
     row looks in the popover.
   - **Scoped section** at the bottom: `:root[data-theme="x"] .selector`
     overrides for chrome the variables can't express.
3. **`tests/LocalAppSmokeTest.php`** — two assertions to update: the
   `var allowed = [...]` allow-list string and (if you copy the pattern)
   a `data-theme-option="x"` presence check.

Theme choice persists in localStorage key `zenmemes-theme`; an inline
script in `layout.php` applies it pre-paint against the allow-list.

## Scoped-CSS rules of the road

- **Never restyle the theme menu's rows from ambient rules.** Popover rows
  are *representative* — each renders in its own theme's colors (spec:
  `docs/theme-menu-representative-options-spec.md`). Any scoped rule that
  targets bare `button` (or anything inside `.theme-menu`) must carry
  `:not(.theme-swatch):not(.theme-menu__trigger):not(.theme-menu__option)`.
  Styling the popover *container* is fine.
- **Scrollbar rules must use `html[data-theme="x"]`, not `:root[...]`.**
  Blink (verified Chrome 145) silently fails to match scrollbar
  pseudo-classes like `:vertical:decrement` when `:root` appears anywhere
  in the selector. Plain part rules (`::-webkit-scrollbar-thumb`) match
  either way, but keep the whole scrollbar block on the `html` form.
  Also: styling `::-webkit-scrollbar-button` renders *doubled* buttons at
  each end — hide the `:vertical:start:increment`, `:vertical:end:decrement`
  (and horizontal equivalents) pieces. Firefox only gets
  `scrollbar-color`, behind `@supports not selector(::-webkit-scrollbar)`.
- **Check what base rules assume before overriding.** Example: `.post-card`
  relies on `padding-bottom: 2.25rem` for its absolutely-positioned
  permalink; a theme that overrides `.card` padding must restate it.

## Graphics: two techniques, no image files

Everything is inline `data:image/svg+xml;utf8,...` URIs in the CSS
(`#` encoded as `%23`). A strict no-asset habit keeps themes portable.

- **Pixel art (theme-specific icons):** `viewBox='0 0 16 16'` with
  `shape-rendering='crispEdges'` and `<rect>` cells, applied as
  `background-image` on `::before`/`::after`. Give the svg root explicit
  `width='16' height='16'` — required when used as a scrollbar-button
  background, harmless elsewhere. Key icons off *stable attributes*, not
  position: `href` for links, data attributes (`data-bookmarklet-kind`)
  when hrefs collide.
- **Theme-neutral glyphs (all themes):** draw smooth shapes (no
  crispEdges), apply via `mask` + `background-color: currentColor` so the
  glyph inherits each theme's ink automatically:
  ```css
  .thing::before {
    background-color: currentColor;
    -webkit-mask: var(--glyph) center / contain no-repeat;
    mask: var(--glyph) center / contain no-repeat;
  }
  ```
  Use this when the improvement is a *site* feature (e.g. About-page
  section glyphs) rather than theme garnish.

## Synthesizing chrome from existing markup

Prefer CSS-only illusions over template changes:

- **Title bars from headings:** style `.card > h1:first-child` /
  `h2:first-child` with negative margins equal to the card padding so the
  strip runs full-bleed. Cards whose first child is a `.meta` byline can
  use that instead (reads naturally as a window title).
- **Headingless cards:** synthesize with `::before` (title strip content)
  and `::after` (decorative buttons), e.g. the board composer's
  "New Thread" bar. Pseudo-element buttons are decoration only — wiring
  them up would need real elements + JS.
- **Window frames without wrapper elements:** stacked `box-shadow` rings
  (`0 0 0 3px silver, 0 0 0 4px dark, 7px 7px 0 4px dark`). Recheck stack
  gaps and shell padding so outset rings don't clip.
- When a template change genuinely improves all themes (Tools/Bookmarklets
  launcher, About sections), make the *markup* theme-neutral, style the
  base with custom properties, and put theme garnish in the scoped section
  keyed off classes/attributes.

## Verification workflow (every change)

The three stale-state traps, in order:

1. `rm -f public/index.html` — page loads regenerate it; a stale copy
   spuriously fails the busy-page smoke test and serves old CSS
   fingerprints.
2. `php scripts/build_static_artifacts.php` — thread/profile/tag routes
   are served from baked artifacts whose inline anti-FOUC allow-list
   predates your theme; until rebuilt, those routes silently fall back to
   the default theme. **Production needs this rebuild too, or the new
   theme won't work on those routes at all.**
3. `php tests/run.php` — must be green before every commit.

Visual verification (look at the screenshots, don't just take them):

- Headless Chromium **cannot render custom scrollbars** (forces overlay
  scrollbars). Use headed Chrome under WSLg:
  `playwright-core` + `chromium.launch({ executablePath:
  '/opt/google/chrome/chrome', headless: false,
  args: ['--no-sandbox', '--ozone-platform=x11'] })`.
- Seed the theme with
  `ctx.addInitScript(() => localStorage.setItem('zenmemes-theme', 'x'))`.
  **This re-runs on every navigation** — comparing themes needs a fresh
  context per theme, not a reload.
- `deviceScaleFactor: 2–3` for chrome details (bevels, pixel icons).
- Standard sweep: board, a thread, tools, tags, users, about; ~380px
  viewport; `:target` permalink jump; keyboard focus; text selection;
  open theme popover under the new theme (leak check).
- Verify behavior, not just looks: theme select + cycle still work,
  bookmarklet links get armed, forms expand.

## Process

- Work in slices, one commit each, with a plan doc in `docs/plans/`
  (`word97_theme_improvements_plan_v1.md` is the template: progress
  checklist with commit hashes, context, constraints, per-slice steps,
  and a rejected-ideas list so decisions don't get relitigated).
- Keep the reference screenshot(s) in the repo root; re-compare against
  them after each few slices — the second look always finds another tier
  of improvements.
- Record browser quirks in the commit message that works around them
  (see `62f2f4d` for the `:root` scrollbar bug) so they survive cleanup.
