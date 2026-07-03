# Theme-Representative Options in the Theme Menu — Spec

Status: in progress on branch `theme-menu-representative-options`.

## Progress

- [x] Slice 1: re-scope the 8 theme variable blocks onto
  `.theme-menu__option[data-theme-option=...]`, rework row base/state rules
  (row = its theme's background/ink/font; hover/focus/checked use the row's
  own tokens), bump popover row gap to 4px. Auto row stays ambient via
  `:not([data-theme-option="auto"])`. `php tests/run.php` green.
- [x] Slice 2: garnish rules (console/vapor text glow, chicago silver chrome,
  sticker offset shadow). Placed *before* the hover/focus/checked rules so the
  sticker `border` shorthand cannot mask the equal-specificity state
  indicators — state visibility wins per spec. `php tests/run.php` green.
- [x] Slice 3 (found by the Playwright sweep): the "cannot leak" safety note
  below was wrong — ambient scoped rules like
  `:root[data-theme=vapor] button:...` are descendant selectors and *do* match
  the option rows, overriding their backgrounds/borders/shadows. Fixed by
  adding `:not(.theme-menu__option)` to the ambient button rules, matching the
  existing `.theme-swatch`/`.theme-menu__trigger` exclusions. Also gave rows
  `background-color: var(--page-bg)` under the `--body-background` gradient:
  Forge's gradient starts near-transparent with no solid layer, so the ambient
  popover bled through it.
- [x] Playwright sweep per Verification section: all 35 checks pass — per-row
  computed styles under contrasting ambients, garnish, checked/hover/focus
  indicators (including sticker hover vs. its border garnish), selection +
  persistence + focus return, Escape, no ambient bleed. Screenshots of the
  popover under all 9 ambient themes reviewed by eye; 11rem min-width reads
  fine (open question 4: keep). `php tests/run.php` fully green (note: a
  stale generated `public/index.html` artifact makes the busy-page smoke test
  fail spuriously — delete it before running).

## Context

The header theme control (branch `theme-selector-popover`) is a capsule with a
swatch dot that cycles themes and a caret that opens a popover menu
(`templates/partials/theme_menu.php`). Each menu option is a row rendered in
the **ambient** (currently active) theme: the ambient `--ui-font`, `--ink`,
and backgrounds, plus a 1rem swatch dot as the only hint of what the target
theme looks like.

With 9 themes whose identities differ far beyond a dot — Console is glowing
green monospace, Chicago is beveled silver on teal, Sticker is black-on-red
zine, Vapor is neon on purple — a dot undersells them. The goal is for each
option row to *look like the theme it selects*: its own page background, ink
color, and font, so the menu reads as a strip of live previews.

## Key insight / mechanism

Every theme is already fully described by CSS custom properties in one
`:root[data-theme="<name>"]` block in `public/assets/site.css` (lines ~1–215):
`--page-bg`, `--body-background`, `--ink`, `--ink-soft`, `--line`, `--panel`,
`--ui-font`, etc.

Custom properties set **on an element** override inherited ones. So if each
option row receives its theme's variable block, the row and everything inside
it can be styled with plain `var(...)` references and will automatically render
in that theme's palette and font — **no color value is duplicated anywhere**.

The rows already carry a stable hook: `data-theme-option="<name>"`
(server-rendered from `ThemeRegistry`). The change is purely additive
selectors:

```css
/* before */
:root[data-theme="console"] { ...vars... }

/* after */
:root[data-theme="console"],
.theme-menu__option[data-theme-option="console"] { ...vars... }
```

One per theme. For **light**, whose values are the `:root` defaults (its
`:root[data-theme="light"]` block only sets `color-scheme`), extend the base
defaults block instead:

```css
:root,
.theme-menu__option[data-theme-option="light"] { ...defaults... }
```

Notes on why this is safe:

- The scoped structural overrides (`:root[data-theme=X] .card`,
  `:root[data-theme=X] button...`) all require `:root[data-theme]` and cannot
  leak into the popover rows; only the variable blocks are re-scoped.
- Element-level custom properties beat inherited ones regardless of the
  ambient theme, so a Console row inside a Vapor page still gets Console vars.
- Each row matches exactly one theme block (names are unique per
  `ThemeRegistry`), so no ordering hazards between blocks.
- `color-scheme` riding along in the blocks is harmless on a styled button.
- The swatch dots (`.theme-swatch[data-theme=X]`) already use fixed literal
  colors, not vars, so they are unaffected and stay correct inside the rows.

## Row design

Each option row becomes a miniature page strip:

```css
.theme-menu__option {
  /* existing: flex row, gap 0.55rem, text-align left, cursor pointer */
  background: var(--body-background);   /* the theme's real page background,
                                           gradients included */
  color: var(--ink);
  font-family: var(--ui-font);
  font-size: 0.78rem;                   /* fixed size so Verdana/Georgia/
                                           monospace rows align */
  min-height: 2rem;                     /* mixed font metrics don't jiggle
                                           the column */
  border: 1px solid var(--line);        /* the theme's own hairline */
}
```

- `--body-background` (not just `--page-bg`) is deliberate: it carries the
  signature gradients — Vapor's purple wash, Console's dark green fade,
  Chicago's teal. Compressed into a 2rem row they still read.
- The label (`.theme-menu__label`) inherits row color/font — no change needed.
- The checkmark `::after` inherits the row's ink — visible on every row by
  construction, since each theme's `--ink` contrasts with its own background.
- **Keep the swatch dot.** It still carries identity (Forge's ember, Auto's
  split), gives the rows a consistent left rhythm, and the smoke tests and JS
  already reference the markup. Markup (`theme_menu.php`) is unchanged.

### Interaction states

The current states (`hover`/`focus-visible`/`checked` → ambient
`--input-bg` + `--line`) stop working once rows own their backgrounds.
Replace with indicators that use **the row's own tokens**, guaranteeing
contrast on every row:

```css
.theme-menu__option:hover {
  border-color: var(--ink-soft);
}

.theme-menu__option:focus-visible {
  outline: 2px solid var(--ink);
  outline-offset: -2px;
}

.theme-menu__option[aria-checked="true"] {
  border-color: var(--ink);        /* plus the existing ::after checkmark */
}
```

(Do **not** use `--active-line`/`--active-bg` here: Chicago's block doesn't
define them, so a Chicago row would inherit the *ambient* theme's values —
`--ink` is defined by every block.)

### Popover container

Stays in the ambient theme (it is chrome, not a preview): keep the opaque
`--page-bg` + `--panel` backing added for Vapor. Two tweaks so the differently
colored rows read as tiles rather than a broken gradient:

- bump row gap from `2px` to `4px`;
- keep rows squared (no radius) to match the site's aesthetic.

## The Auto row

Auto is a *mode*, not a palette. Recommended: **leave the Auto row in ambient
styling** with its existing split light/dark swatch — its "not one of the
themes" look is informative, and it costs nothing.

Alternative (only if the ambient-styled row looks out of place in review):
have JS mirror the resolved system scheme onto the row
(`autoOption.dataset.resolved = systemTheme()`, updated in the existing
matchMedia listener) and add `.theme-menu__option[data-resolved="dark"]` /
`[data-resolved="light"]` to the dark/defaults selector lists. Small, but
touches `theme_toggle.js`; skip it for v1.

To keep Auto ambient while using the generic row rule above, either exclude it
(`.theme-menu__option:not([data-theme-option="auto"]) { ... }`) or give the
generic rule ambient-var fallbacks and let the eight re-scoped blocks
override; the `:not()` exclusion is simpler and self-documenting.

## Optional garnish (slice 2, cuttable)

Fonts and palettes come free via the variables, but four themes have
identities carried by *effects* the variables can't express. Four tiny scoped
rules complete the previews:

```css
.theme-menu__option[data-theme-option="console"] { text-shadow: 0 0 6px rgba(155, 255, 138, 0.55); }
.theme-menu__option[data-theme-option="vapor"]   { text-shadow: 0 0 8px rgba(122, 252, 255, 0.6); }
.theme-menu__option[data-theme-option="chicago"] {
  background: #c0c0c0;                    /* silver chrome, not the teal desktop */
  box-shadow: inset -1px -1px 0 #7b7b7b, inset 1px 1px 0 #ffffff;
}
.theme-menu__option[data-theme-option="sticker"] {
  border: 2px solid #0a0a0a;
  box-shadow: 2px 2px 0 #0a0a0a;          /* the sticker offset */
}
```

Sticker's offset shadow needs ~2px breathing room — covered by the 4px row
gap. If any of these fight the checked/hover border indicators in practice,
cut the garnish before weakening the indicators: state visibility wins.

## Accessibility

- Contrast inside each row is guaranteed by construction (each theme's
  `--ink` vs its own background is the contrast the whole site runs on).
- Checked/hover/focus indicators use the row's own ink — no state relies on a
  color that might blend with a particular row's background.
- Fixed `font-size`/`min-height` keeps 9 rows visually scannable despite
  five different font stacks.
- No ARIA changes: `role="menuitemradio"`, `aria-checked`, focus order, and
  keyboard behavior are untouched.
- The menu is a redundant path (the cycle dot still works), so even in
  forced-colors/high-contrast modes where the previews flatten, nothing is
  lost functionally.

## Non-goals

- No markup or PHP changes (`theme_menu.php`, `ThemeRegistry`,
  `TemplateRenderer`, `layout.php` untouched).
- No JS changes in v1 (Auto stays ambient).
- No change to the header capsule, cycle button, or swatch dots.
- No mini "screenshot" tiles (panel-in-page compositions per row) — the row
  strip is the 90% version; tiles can be a later iteration if wanted.
- The forge `@media (max-width: 520px)` gradient suppression is not extended
  to the row (a 2rem gradient is negligible on OLED).

## Files

- `public/assets/site.css` — the only file that changes:
  1. Extend 8 selector lists (7 theme blocks + the `:root` defaults block for
     light) with `.theme-menu__option[data-theme-option="<name>"]`.
  2. Rework `.theme-menu__option` base + state rules as above.
  3. (Slice 2) 4 garnish rules.
- `tests/LocalAppSmokeTest.php` — existing theme-menu assertions still pass
  unchanged (markup is identical). Optionally assert nothing; CSS-only.

## Verification

1. `php tests/run.php` (should be untouched-green; delete any stale
   `public/index.html` artifact first).
2. Playwright sweep, ambient theme = each of the 9 in turn:
   - open the popover; screenshot; every row shows its own colors/font
     regardless of ambient theme;
   - computed-style spot checks under a *contrasting* ambient (e.g. ambient
     vapor): console row `background-color` ≈ `rgb(8, 17, 10)` and
     `font-family` contains `Lucida Console`; sticker row background
     `#e0493a`; light row white;
   - checked row shows checkmark + ink border; hover and focus-visible
     visible on light, dark, and sticker rows;
   - select from a preview row → theme applies, persists, popover closes,
     focus returns (existing behavior, must not regress);
   - keyboard: arrows/Escape unchanged.
3. Confirm no bleed: ambient page styling (cards, nav, buttons) unchanged
   after the selector-list edits — the re-scoped blocks contain only custom
   properties.

## Open questions to resolve before shipping

1. **Swatch dot redundancy** — with full-row previews, is the dot per row
   still wanted, or should rows drop it (label-only previews)? Spec keeps it;
   cheap to remove later (CSS `display: none`, or drop the span from the
   partial).
2. **Auto row treatment** — ambient (recommended) vs. resolved-scheme preview
   (needs the small JS attribute sync).
3. **Chicago row background** — teal `--body-background` (true to the page)
   vs. silver chrome garnish (true to the UI). Spec recommends silver via the
   garnish rule; if garnish is cut, teal is what you get and it's fine.
4. **Popover width** — mixed fonts may want `min-width` bumped from 11rem to
   ~12rem so Verdana/monospace labels don't feel cramped. Decide by eye in
   the Playwright sweep.
