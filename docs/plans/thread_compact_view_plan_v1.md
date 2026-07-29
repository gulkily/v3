# Compact view for thread listings

## Context

Users currently see thread lists (board/tag pages) as full cards: title, meta line, full body-preview text, and reply count, with generous spacing between cards. This makes it hard to scan many threads at once. We're adding a "compact view" that hides the body preview and tightens spacing, so more threads fit on screen at once. It should be a global, persistent preference (like the existing theme setting), apply to every page that lists threads as cards, and also be overridable via a URL query parameter for shareable/explicit links.

**Scope confirmed with user:** applies to all thread-listing pages — the board's all/liked views and newest/oldest/top sorts (`templates/pages/board.php`), and per-tag thread lists (`templates/pages/tag.php`). The tags index (`templates/pages/tags.php`) already shows a compact preview list with no body text, so it needs no changes. Thread detail pages (`templates/pages/thread.php`) are out of scope — this is about scanning the list, not reading an open thread.

**Persistence confirmed with user:** primarily a toggle button + `localStorage`, mirroring the existing theme toggle exactly, with a `?density=` URL query param as an override that also updates the saved preference.

## Existing patterns being reused

- **FOUC-free preference toggle**: `templates/layout.php:7-18` inline script reads `zenmemes-theme` from `localStorage` and sets `data-theme` on `<html>` before first paint; `public/assets/theme_toggle.js` handles the interactive toggle and writes back to `localStorage`. We replicate this exactly for density instead of theme.
- **Script/asset wiring**: `src/ForumRewrite/View/TemplateRenderer.php:65-67` passes script paths (e.g. `themeToggleScriptPath`) into `layout.php`, which emits `<script defer>` tags (`layout.php:29`) and loads partials like `theme_menu.php` into `site-header-actions` (`layout.php:49`).
- **Duplicate thread-card markup**: `board.php:41-51` and `tag.php:9-21` render nearly identical thread cards (title/meta/body-preview/reply-count, differing only in a pinned marker vs. a labels line). Extracting a shared partial avoids implementing compact-mode markup twice.

## Implementation

### 1. New shared partial: `templates/partials/thread_card.php`
Extract the thread-card markup currently duplicated in `board.php:41-51` and `tag.php:9-21` into one partial, parameterized with `thread` and a small `badges` option (pinned marker for board, labels line for tag). Structure it as:
```html
<article class="card thread-card" data-heat="...">
  <h2>...</h2>
  <p class="meta">...</p>
  <p class="thread-card__preview">body preview</p>
  <p class="meta">reply count</p>
</article>
```
The `thread-card__preview` class is the hook compact mode hides. Update `board.php` and `tag.php` to call `$partial('partials/thread_card.php', [...])` in place of their inline loops. Wrap the `<section class="stack">` in both templates with an added `thread-list` class (e.g. `class="stack thread-list"`) so compact spacing rules don't leak into unrelated `.stack` usages elsewhere (forms, `thread.php`'s reply stack, etc.).

### 2. Preference storage & FOUC prevention: `templates/layout.php`
Extend the existing inline script (lines 7-18) to also:
- Read a `density` query param from `location.search`; if it's `compact` or `comfortable`, apply it immediately and write it to `localStorage` under a new key `zenmemes-thread-density`.
- Otherwise, read `zenmemes-thread-density` from `localStorage`.
- Set `data-thread-density="compact"` on `<html>` when compact is active (mirrors how `data-theme` is set); omit the attribute for the default/comfortable state.

### 3. Toggle control
- New partial `templates/partials/thread_density_toggle.php`: a single button (simpler than the theme popover since there are only two states), e.g. `<button data-role="thread-density-toggle" data-action="thread-density-toggle" aria-pressed="false">Compact</button>`, styled similarly to `.theme-menu__trigger`.
- Add it into `site-header-actions` in `layout.php:49`, next to `theme_menu.php`, so it's globally visible (consistent with the theme toggle's placement).
- New script `public/assets/thread_density_toggle.js`, modeled directly on `theme_toggle.js`: on load, sync from the `density` query param (if present) or `localStorage`; on click, toggle `data-thread-density`, update `aria-pressed`/label, and persist to `localStorage`.
- Wire it up in `TemplateRenderer.php:66` (`'threadDensityToggleScriptPath' => $this->assetPath('/assets/thread_density_toggle.js'),`) and add the corresponding `<script defer>` tag in `layout.php` near line 29.

### 4. CSS: `public/assets/site.css`
Append near the end of the stylesheet (after the per-theme override blocks, so cascade order wins over theme-specific `.card`/`.stack` rules):
```css
:root[data-thread-density="compact"] .thread-card__preview { display: none; }
:root[data-thread-density="compact"] .thread-list .thread-card { padding: 0.45rem 0.6rem; }
:root[data-thread-density="compact"] .thread-list > * + * { margin-top: 0.35rem; }
```
Plus small button styling for the new toggle, consistent with existing `.theme-toggle` styling.

## Files touched
- `templates/partials/thread_card.php` (new)
- `templates/partials/thread_density_toggle.php` (new)
- `public/assets/thread_density_toggle.js` (new, modeled on `public/assets/theme_toggle.js`)
- `templates/pages/board.php` (use shared partial, add `thread-list` class)
- `templates/pages/tag.php` (use shared partial, add `thread-list` class)
- `templates/layout.php` (extend FOUC script, add toggle partial + script tag)
- `src/ForumRewrite/View/TemplateRenderer.php` (wire new script path)
- `public/assets/site.css` (compact-mode rules + toggle button styling)
- `tests/LocalAppSmokeTest.php` (extend)

## Verification
- Extend `tests/LocalAppSmokeTest.php`'s route-rendering assertions to check the toggle button markup (`data-role="thread-density-toggle"`) is present on board/tag pages, and that the extracted `thread_card.php` partial still renders the expected `class="card thread-card"`, title link, and reply-count text on both `board.php` and `tag.php` routes.
- Manual browser check via the `run` skill: load `/`, `/threads/?view=liked`, `/threads/?sort=top`, and `/tags/<some-tag>`; click the compact toggle and confirm body previews hide and spacing tightens on all of them; reload and confirm the preference persists; clear `localStorage`, visit `/?density=compact` fresh, and confirm it applies immediately and then persists on subsequent navigation without the param.
