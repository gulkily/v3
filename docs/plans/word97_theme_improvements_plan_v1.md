# Word 97 Theme Improvements (round 2)

## Progress

- [x] Slice 1 — `2267d11` Win95 combo-box theme control (sunken white field, squared cycle swatch, beveled caret with pixel arrow + pressed state; rows verified untouched, select/cycle verified via Playwright)
- [x] Slice 2 — `ad4f3a1` Cards as MDI windows (silver ring frames + navy title bars from h1/h2/byline; highlighter pinned badge pulled forward; :target navy ring; verified on board/thread/tools/tags/users, :target jump, 380px viewport, after an artifact rebuild per constraint 5) + follow-up commit unifying the site header onto the same ring frame / full-bleed title bar per user feedback
- [x] Slice 3 — Title-bar app icon (16px page-with-navy-Z pixel SVG as .eyebrow::before; eyebrow switched from space-between to margin-left:auto on the window buttons so the icon hugs the wordmark; verified at 1000px and 380px)
- [x] Slice 4 — System-feel pass (navy/white ::selection, Win95 dotted focus rects on links/buttons — skipped edit fields as Win95 doesn't draw them there, engraved disabled/dim text, silver popover menu chrome; rows verified untouched; all four verified visually)
- [ ] Slice 5 — Word detail pass (end-of-document marker, squiggles, highlighter)
- [ ] Slice 6 — Clippy easter egg (blocked on user confirmation + asset clip)
- [ ] Slice 7 — Office-suite stretch ideas (deferred; pick up only if wanted)

## Context

The `theme-word97` branch has the base theme plus three improvement commits
(icons `3c8adf3`, status bar `5fdeedb`, scrollbars `62f2f4d`). A second
comparison against the reference screenshot
(`Office_97_screenshot,_Windows_2000.png`, repo root) produced the
improvement list below. Cadence stays one slice = one commit on
`theme-word97`.

Reference details each slice is chasing:

- The Office 97 toolbars are full of combo boxes ("Normal", "Times New
  Roman", "10", "100%"): white sunken field + silver beveled drop-down
  arrow button. Our theme-menu capsule is the last modern rounded element
  in the header.
- Word's title bar starts with a 16px app icon before the window title.
- Selection (Word text, Outlook rows) is navy `#000080` with white text;
  Win95 focus is a 1px dotted black rectangle inside the control; disabled
  text is gray with a 1px white emboss.
- Word's empty document shows a short black end-of-document bar at the
  page's top-left; spell check draws red wavy underlines (green for
  grammar); the highlighter tool is black-on-`#ffff00`.
- The Office Assistant (Clippy) sits in its own little window (navy title
  bar, X button) at the bottom-right of all four apps in the reference.

## Approach

Everything except Clippy is pure CSS in `public/assets/site.css`, appended
to the existing word97 scoped section, using the conventions already on
the branch: pixel art as inline `data:image/svg+xml` URIs with
`shape-rendering='crispEdges'`, `#` encoded as `%23`, explicit
`width='16' height='16'` on the svg root (required for scrollbar-part
backgrounds, harmless elsewhere).

Constraints and gotchas established earlier on this branch — re-read
before touching anything:

1. **Blink `:root` scrollbar quirk** (commit `62f2f4d`): scrollbar
   *pseudo-class* rules (`:vertical:decrement` etc.) silently fail to
   match when `:root` appears in the selector. All
   `::-webkit-scrollbar-*` rules must use `html[data-theme="word97"]`.
   Regular rules keep `:root[data-theme="word97"]`.
2. **Theme-menu rows are representative** (see
   `docs/theme-menu-representative-options-spec.md`): each popover row
   renders in its own theme's colors. Ambient word97 rules must not leak
   into rows — keep the existing
   `:not(.theme-swatch):not(.theme-menu__trigger):not(.theme-menu__option)`
   exclusions on any new button/element rules. Styling the popover
   *container* is fine; restyling other themes' rows is not.
3. **Stale artifact**: page loads regenerate `public/index.html`, which
   breaks the busy-page smoke test and can serve stale CSS fingerprints
   during verification. `rm -f public/index.html` before every
   `php tests/run.php` and before every browser screenshot session.
4. **Scrollbar/visual verification needs headed Chrome**: headless
   Chromium (shell and full `--headless`) forces overlay scrollbars.
   Launch `/opt/google/chrome/chrome` with playwright-core,
   `headless: false`, `--ozone-platform=x11` (WSLg display available).
   Seed the theme via
   `addInitScript(() => localStorage.setItem('zenmemes-theme', 'word97'))`.
5. **Thread/profile/tag pages serve stale static artifacts** (found
   during the slice-2 page survey): `public/threads/*.html` etc. were
   baked before the word97 theme existed, so their inline anti-FOUC
   allow-list rejects `word97` and they render in the default theme with
   the old CSS fingerprint. Run `php scripts/build_static_artifacts.php`
   (or delete the specific artifact) before verifying those pages, and
   remember the production deploy needs an artifact rebuild for the theme
   to work on those routes at all.

## Steps

### Slice 1 — Win95 combo-box theme control

All in the word97 scoped CSS section:

- `.theme-menu`: kill the pill — `border-radius: 0`, white background,
  sunken field border (`border: 1px solid #808080`,
  `box-shadow: inset 1px 1px 0 0 #404040, inset -1px -1px 0 0 #dfdfdf`),
  adjust padding so the swatch sits inside the field.
- `.theme-menu .theme-toggle.theme-swatch` (the cycle dot): square it
  (`border-radius: 0`) — the word97 swatch is already a mini window
  gradient, square reads better than a circle here.
- `.theme-menu__trigger` (the caret): silver beveled button like a combo
  drop-down — `background-color: #c0c0c0`, `border-radius: 0`,
  raised bevel (`inset 1px 1px 0 0 #ffffff, inset -1px -1px 0 0 #808080`,
  border `#404040`), replace the `▾` glyph rendering with the same
  down-arrow SVG data URI used by the vertical scrollbar increment button
  (`background-image` + `color: transparent`, or keep the glyph and just
  style the box — decide by eye).
- Hover/active: pressed bevel on `:active`, matching the button rules
  already in the section.
- Verify: header close-up screenshot at deviceScaleFactor 2–3; open the
  popover and confirm rows are untouched (constraint 2); cycle + select
  still work (click through via Playwright).

### Slice 2 — Cards as MDI windows

User decision (2026-07-08): **every** card becomes a Win95 child window —
thread posts read as Word MDI document windows, utility cards as dialogs.
This matches the site header's window chrome and is why it runs before
the remaining polish slices.

All CSS in the word97 scoped section; no template changes expected:

- **Window frame**: rework the base word97 `.card, .feedback` rule.
  Interior stays white; the silver frame is stacked `box-shadow` rings
  (no wrapper element exists to paint a real frame):
  `border: 1px solid #808080` (inner edge), then
  `box-shadow: 0 0 0 3px #c0c0c0, 0 0 0 4px #404040, 4px 4px 0 4px #404040;`
  (silver ring, dark outer edge, hard offset drop shadow). The stack gap
  (`.stack > * + *`) and `.shell` padding need rechecking so the
  4px-outset rings don't clip at container edges; bump spacing as needed.
- **Title bars from headings**: card headings become the window title —
  navy gradient strip, white bold `--ui-font` text, stretched across the
  card padding with negative margins (same trick as the header eyebrow):
  - board/tools/about/tags cards: `.card > h1:first-child`,
    `.card > h2:first-child` (board thread cards start with
    `<h2><a>subject</a></h2>` — `templates/pages/board.php:45`);
    links inside get `color: #ffffff`, no underline.
  - reply cards have no heading; they start with the byline
    (`.post-card > .meta:first-child`, see
    `templates/partials/post_card.php:13`) — style that as the title bar
    ("by ilyag on May 31…" as the window title, which is exactly the MDI
    child-window vibe).
  - cards with neither (compose card is just a form) get frame only —
    a window with no title bar reads fine as a panel.
- **Interactions to check while there**:
  - `.pinned-thread-marker` sits inside the board `h2` → it will land on
    the navy bar; restyle for that context (this likely becomes the
    natural home of the highlighter-yellow treatment planned in the
    detail slice — fine to pull it forward).
  - `.tags-section-card` has a gradient background override; flatten it
    to white under word97.
  - `.post-card:target` navy border needs re-expressing on the new frame
    (e.g. swap the silver ring color instead of the border).
  - `.post-card-permalink` absolute position (`right: 0.9rem;
    bottom: 0.65rem`) against the new padding.
- **Page margins** (moved here from the detail slice): card padding
  `1.1rem` → `1.5rem`, since the title-bar negative margins must be
  written against the final padding value.
- Verify (constraint 5 first — rebuild artifacts): board, thread,
  tools, account, users, tags screenshots; pinned badge on the navy bar;
  a `:target` permalink jump; narrow viewport (~380px) for frame
  clipping.

### Slice 3 — Title-bar app icon

- `:root[data-theme="word97"] .eyebrow::before`: 16×16 pixel-art data-URI
  SVG, `flex: 0 0 auto`, `margin-right: 0.4rem`. The eyebrow is already
  `display: flex` with the window buttons as `::after`, so `::before`
  lands at the far left like Word's app icon.
- Icon design: white document page with a navy block-pixel "Z" (ZenMemes)
  — same visual family as the nav icons from `3c8adf3`. Word uses its "W"
  on a page; we use our letter.
- Verify: header screenshot; check the icon doesn't wrap on narrow
  viewports (spot-check ~380px width).

### Slice 4 — System-feel pass (one commit)

- Selection: `:root[data-theme="word97"] ::selection
  { background: #000080; color: #ffffff; }`.
- Focus rectangles: scoped `:focus-visible` for `a`, `button`, `input`,
  `textarea`, `.nav-link` → `outline: 1px dotted #000000;
  outline-offset: -4px;` (negative offset = inside the control, the Win95
  look). Exclude `.theme-menu__option` (rows keep their own
  focus indicator per the representative-options spec). Sunken fields
  (`input`/`textarea`) may read better with `outline-offset: 1px` —
  decide by eye.
- Engraved disabled text: `.site-status-bar__panel--dim` and
  `button[disabled]` (with the usual theme-menu exclusions) →
  `color: #808080; text-shadow: 1px 1px 0 #ffffff;`.
- Popover chrome: `:root[data-theme="word97"] .theme-menu__popover` →
  Win95 menu box: `background: #c0c0c0`, `border: 1px solid #404040`,
  raised bevel inset shadows, plus a hard drop shadow
  (`2px 2px 0 rgba(0,0,0,0.35)` or solid `#404040`). Container only —
  rows untouched.
- Verify: keyboard-tab through the header and a card (focus rectangles),
  select text in a post body (navy selection), open popover under word97
  ambient and compare rows against the pre-slice screenshot
  (`scratchpad/current-menu.png` equivalent) to prove no leakage.

### Slice 5 — Word detail pass (one commit)

- End-of-document marker: `:root[data-theme="word97"] .main >
  .stack::after { content: ""; display: block; width: 26px; height: 3px;
  margin: 0.9rem 0 0 0.1rem; background: #000000; }` — the short black
  bar after the last page. Confirm `.stack` is the direct board/thread
  card container in `templates/pages/board.php` / thread page before
  wiring; adjust the selector to whichever container is the "document".
- Spell-check squiggle: `:root[data-theme="word97"] .feedback-error
  { text-decoration: underline wavy #cc0000 1px;
  text-underline-offset: 3px; }`. If a warning-level element with a
  stable class exists (grep for `--status-warning` consumers), give it
  the green grammar squiggle (`#008000`); if not, skip — don't invent
  markup for it.
- Highlighter pinned badge: override the navy
  `:root[data-theme="word97"] .pinned-thread-marker` rule from the base
  commit → `background: #ffff00; color: #000000; border-color: #000000;`.
- Page margins: word97 `.card` padding `1.1rem` → `1.5rem` (check
  `.inline-reply-composer { padding: 0 }` children and
  `.post-card-permalink` absolute offsets still line up on a thread
  page).
- Verify: board + thread page screenshots; eyeball the pinned badge and
  the marker under the last card.

### Slice 6 — Clippy easter egg (ask first)

Blocked on user confirmation (clipping a Microsoft character from their
screenshot into the repo is their call).

- Clip the Office Assistant window from the Word quadrant of
  `Office_97_screenshot,_Windows_2000.png` (bottom-right of the Word
  window, roughly x 870–1005, y 590–705 in the 2048×1536 original —
  crop by eye with ImageMagick, include the little window's navy title
  bar and X button). Save as `public/assets/clippy.png`.
- CSS: `:root[data-theme="word97"] body::after { content: "";
  position: fixed; right: 20px; bottom: 2.6rem; width: <clip w>px;
  height: <clip h>px; background: url("clippy.png") no-repeat;
  z-index: 19; pointer-events: none; }` — url is relative to
  `/assets/site.<hash>.css` so it resolves to `/assets/clippy.png`
  unfingerprinted, which the router serves directly. Sits above the
  status bar (`z-index: 20` — keep Clippy under it or bump; decide by
  eye), hidden on small screens
  (`@media (max-width: 640px) { display: none; }`).
- Note: purely decorative (background image + `pointer-events: none`), no
  dismissal — CSS-only theme can't persist a dismissed state.
- Verify: board screenshot at desktop and ~500px widths; confirm no
  overlap with the New Post control or the status bar.

### Slice 7 — Office-suite stretch (deferred)

Only if the user wants to lean from "Word 97" into "Office 97 suite":

- Board sort row (`.board-controls-nav` links) as Excel sheet tabs:
  white trapezoid-ish tabs on a silver strip, active tab white + bold,
  inactive silver.
- Pending cards (`.pending-reply-card`, `.pending-thread-shell`) tuned to
  PowerPoint placeholder style: fine 1px dashed `#808080` on white.

Rejected during review (don't revisit without new information): ruler
numbers (text-in-SVG layering for marginal payoff), yellow tooltips
(native `title` unstylable), splitting the fused menu/toolbar row.

## Verification checklist (every slice)

1. `rm -f public/index.html && php tests/run.php` — all green.
2. Headed-Chrome screenshot pass per the slice's Verify bullet
   (constraint 4 recipe); look at the screenshots, don't just take them.
3. Popover-leak spot check whenever a slice touches `button`, `a`, or
   theme-menu selectors (constraints 2).
4. Commit with the branch's message style; note any new Blink/browser
   quirks in the commit body like `62f2f4d` did.
