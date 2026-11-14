# More panel fits one phone screen — direct work

Mode: Direct (no Railspike cycle). Branch `feat/more_panel_fit` from `origin/main`.

## Request

2026-11-14, maintainer: on phones the More panel "doesn't fit into one screenful, and that means the theme picker is off screen initially"; after Bubblegum's tile grid fixed it there, he asked for "the same treatment in other themes so that it fits all in one screen".

Feedback items: none matched in `docs/feedback/items/`.

## Outcome

On phones (`max-width: 639px`) both link groups in the More panel (`.account-menu__links`, the More destinations and the account links) show two links per row instead of one. On short screens (`max-height: 520px`, e.g. an iPhone SE with Safari's bars showing) the links and panel padding are slimmer. Every theme inherits this from `site.css`; Bubblegum keeps its own three-across tiles (PR #206).

## Reuse decisions

- Extends the existing phone rules for `.account-menu__panel` and `.account-menu__links` in `public/assets/site.css`; no markup or script change. The panel already stacks its items at the bottom and scrolls when they overflow, so a very long list still works.
- Icons come from the existing per-`href` rules in `theme-nav-icons.css`; long labels wrap (`overflow-wrap: anywhere`) instead of clipping.

## Checks

Headless Chrome, More panel opened on `/` with member-only links shown and the list padded to 14 links (more than a logged-in member sees today), every permitted ZenMemes theme except Bubblegum (gloss, glossdark, light, dark, word97, chicago, midnight, console, lcd, vapor, forge, sticker, arena, thermal, whitehot, engagify):

- 375 x 548 (iPhone SE-size with Safari bars): panel fits with no scrolling in all 16 themes; before the change it needed 839px.
- 320 x 480: fits in all 16 themes.
- No clipped labels, no horizontal overflow; screenshots of Gloss and Word 97 inspected.

Smoke set not run (CSS-only). Full suite not run. Not checked: iPhone Safari itself, the real logged-in list.
