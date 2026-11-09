# Theme Development Guide

How to add a new theme or improve an existing one, based on what we learned
building the Word 97 theme (`b21b525..cb45bd7`, July 2026). Word 97 is the
most heavily customized theme in the codebase — read
`public/assets/theme-word97.css` as the worked example for everything below.

## Architecture: where a theme lives

A theme is one registry entry plus one CSS file. No JS or template changes
are needed for a basic theme. (This changed from the original single-`site.css`
layout described in earlier revisions of this guide — themes were later split
into their own per-theme stylesheets, loaded on demand instead of shipping
every theme's CSS on every page.)

1. **`src/ForumRewrite/View/ThemeRegistry.php`** — single source of truth.
   Add `['name' => 'x', 'label' => 'X', 'mode' => 'light'|'dark']`. Order
   here is menu order and cycle order. `ThemeRegistry::stylesheetPaths()`
   derives each theme's stylesheet path from its `name` automatically
   (`/assets/theme-<name>.css`) - you don't wire that path anywhere else.
   Two optional fields change that (see [Shared stylesheets and the cycle
   button](#shared-stylesheets-and-the-cycle-button)): `'stylesheet' =>
   '<other>'` and `'cycle' => false`. A third, `'palettes'`, adds a colour
   picker (see [Colour palettes](#colour-palettes)).
   The registry feeds the popover markup, the cycle button, the anti-FOUC
   allow-list, and `templates/layout.php`'s `themeStylesheetPaths` JS map
   (which swaps the `<link id="theme-stylesheet">` `href` on theme change) -
   you never edit those directly.
2. **`public/assets/theme-<name>.css`** — one new file, containing (in this
   order, all in the same file - copy an existing theme file like
   `theme-word97.css` as the template):
   - **Variable block** at the top:
     `:root[data-theme="x"], .theme-menu__option[data-theme-option="x"]`
     setting `color-scheme` and the custom properties (`--page-bg`,
     `--ink`, `--panel`, `--button-bg`, fonts, etc.). Copy an existing
     theme file's block as the checklist of properties.
   - **Swatch rule**: `.theme-swatch[data-theme="x"]` - the little
     circle/square that represents the theme in the header and menu. Make
     it unmistakable at 1rem.
   - **Menu-row garnish** (optional): `.theme-menu__option[data-theme-option="x"]`
     - how the theme's row looks in the popover.
   - **Scoped section**: `:root[data-theme="x"] .selector` overrides for
     chrome the variables can't express.
3. **`tests/LocalAppSmokeTest.php`** — two assertions to update: the
   `var allowed = [...]` allow-list string and (if you copy the pattern)
   a `data-theme-option="x"` presence check.

Theme choice persists in localStorage key `zenmemes-theme`; an inline
script in `layout.php` applies it pre-paint against the allow-list.

## Theme scripts

A theme may also ship a script. Set `'script' => true` on its registry entry
and add `public/assets/theme-<name>.js`. `theme_toggle.js` loads it the first
time the theme becomes active and keeps it loaded.

The script registers itself:
`window.forumThemeScripts.register('<name>', { start: fn, stop: fn })`.
Only the active theme is started. Every theme change calls the old theme's
`stop` before the new one starts, without a reload, so `stop` must remove every
element, listener and timer that `start` added. `theme-engagify.js` shows one
pattern: a context object that records each element and timer, so `stop` is
one call. Scripts must keep the same promises as stylesheets: never rename or
reuse the hooks other scripts select on, keep controls reachable, and work at
~380px. A theme that adds controls should give the reader a visible way out:
`window.forumExitTheme('<target>')` applies the target theme like a menu
choice, saving it and leaving a page theme. Engagify passes `'gloss'`. A target
the site does not offer falls back to the site default.

Theme script UI appears after first paint, an exception to the first-paint
rule in `CONTRIBUTING.md`. Keep it to themes whose point is that behavior.
`tests/browser/theme_scripts.cjs` covers loading, start and stop.

## Shared stylesheets and the cycle button

Two themes with one look should share one stylesheet rather than copy it. Set
`'stylesheet' => '<owner>'` on the borrowing entry; it then loads
`/assets/theme-<owner>.css`. The owner must be an explicit theme that does not
itself borrow. In the shared file, write shared rules for both names
(`:root:is([data-theme="gloss"], [data-theme="engagify"]) .card`) and the
borrower's extra rules for its name alone. Start the file with a `Theme:` line
for each name. `theme_toggle.js` tracks loaded stylesheets by path, so
switching between the two never fetches the file twice. Gloss and Engagify
are the worked example: `theme-gloss.css` holds Gloss's look and, below it,
Engagify's parody layer.

`'cycle' => false` keeps a theme in the menu but out of the cycle button, for
themes a reader should only choose on purpose, such as Engagify. The menu
option carries `data-theme-cycle="skip"`.

## Colour palettes

A theme may offer colours, as LCD, Console and Gloss do. Set `'palettes' =>
['key' => 'Label', ...]` on its registry entry; the first key is the default.
The menu then renders a colour button beside the theme switcher, the pre-paint
script restores the saved choice as `data-<theme>-palette` on `<html>` (stored
under `<theme storage key>-<theme>-palette`), and `theme_toggle.js` wires the
picker. No template or script changes are needed.

The theme's stylesheet does the rest: show the picker with
`:root[data-theme="x"] [data-role="x-palette"] { display: block; }`, reserve
room with `.theme-menu { margin-left: 2.5rem; }`, colour the chips with
`[data-palette-theme="x"][data-palette-chip="<key>"]`, and override variables
under `:root[data-theme="x"][data-x-palette="<key>"]` for every key but the
first. Scope palette rules to the theme's own name, so a theme that shares the
stylesheet keeps its colours (Engagify stays violet whatever Gloss uses). Only
LCD's colours are cycle-button stops. `ThemeRegistryTest` checks the picker and
palette rules exist; `tests/browser/gloss_palettes.mjs` checks Gloss's colours,
including contrast.

## Page themes

A site profile in `SiteProfileRegistry.php` may map page paths to themes in
`pageThemes`, such as the cookie notice thread to `engagify`. Paths carry no
query and no trailing slash, and the theme must be permitted on that site. The
page shows its theme to every reader before paint. Exit, or any theme choice
on that page, leaves it there; the browser remembers that per page. Exit saves
its target as the reader's theme, as a menu choice does.

Thread pages are baked, so after changing `pageThemes`, rebuild artifacts
(`php scripts/build_static_artifacts.php`) or the page keeps its old theme.

A thread can also set its own page theme from inside the forum, which beats
`pageThemes` for that thread. Its author or a current operator posts a line
`theme: <name>` in the opening post or any reply, or picks a theme under the
composer's Options, which writes a hidden `Theme:` header instead of a line.
The latest setting wins; `theme: none` (or "Clear thread theme") clears it,
and retracting the post withdraws it. `ThreadTheme::forThread()` decides this
when the page is read. These pages need no manual rebuild: a reply or a
retraction already drops the thread's baked page.

## Scoped-CSS rules of the road

- **Never restyle the theme menu's rows from ambient rules.** Popover rows
  are *representative* — each renders in its own theme's colors (spec:
  `docs/specs/theme_menu_representative_options_spec_v1.md`). Any scoped rule that
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
- **Smooth-line icons (Gloss and Engagify):** the second navigation icon set in
  `theme-nav-icons.css`: `viewBox='0 0 16 16'`, 1.5px round strokes in
  `#6d28d9` with a `#ede9fe` fill, no crispEdges. Both sets share the sizing
  rules and key glyphs to the same selectors; `NavIconSetsTest` fails when a
  destination is in one set and not the other.
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

- **Be bold about appearance; be conservative about function.** A theme is
  not a palette swap — the good ones reshape the page until it reads as a
  different artifact entirely (Word 97 turned cards into MDI windows with
  title bars, a ruler, and a status bar; Thermal colors every card by
  activity; Chicago widens the shell and re-chromes every control). Freely
  override layout, spacing, shell width, borders, shadows, typography, and
  per-element structure illusions; add decorative pseudo-elements; make
  the base look unrecognizable. What must survive, exactly:
  - every interactive element remains visible, reachable, and clickable
    (don't cover controls with decoration; `pointer-events` stays sane);
  - JS hooks keep working — never rename or repurpose `data-*` attributes,
    roles, or classes that scripts select on; decorative buttons that do
    nothing must be pseudo-elements, not real elements;
  - keyboard focus, text selection, and error/status states stay visibly
    distinct in the new palette;
  - text stays readable (contrast, line length) and the page still works
    at ~380px;
  - other themes are untouched — boldness lives inside the scoped section
    and theme-neutral markup changes.
  When torn between a tasteful tweak and the committed bit, do the
  committed bit and verify function afterward; the sweep exists so you
  can take risks.
- Work in slices, one commit each, with a plan doc in `docs/plans/`
  (`word97_theme_improvements_plan_v1.md` is the template: progress
  checklist with commit hashes, context, constraints, per-slice steps,
  and a rejected-ideas list so decisions don't get relitigated).
- Keep the reference screenshot(s) in the repo root; re-compare against
  them after each few slices — the second look always finds another tier
  of improvements.
- Record browser quirks in the commit message that works around them
  (see `62f2f4d` for the `:root` scrollbar bug) so they survive cleanup.
