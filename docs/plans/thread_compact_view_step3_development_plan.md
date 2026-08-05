# Thread Compact View Step 3 Development Plan

## Scope
- Implement the approved design in `docs/plans/thread_compact_view_plan_v1.md`: a global, persistent compact-view toggle for thread-listing pages (`board.php`, `tag.php`), controlled by a header button + `localStorage`, overridable via a `?density=` query param.
- Implement on a dedicated branch after approval, with one request-scoped commit per stage and this plan updated (Status line) in each commit.

## Stage 1
- Goal: Extract a shared `thread_card.php` partial with no behavior or markup change, eliminating the duplication between `board.php:41-51` and `tag.php:9-21` before any compact-view logic is added.
- Dependencies: Approved plan; clean worktree to create the feature branch.
- Expected changes: New `templates/partials/thread_card.php` accepting `thread` and a small badge option (pinned marker for board, labels line for tag); update `board.php` and `tag.php` to call it via `$partial(...)` in place of their inline loops. Add a `thread-list` class to the wrapping `<section class="stack">` in both templates (structural prep only, no CSS yet). No visual output should change.
- Verification approach: Run the local smoke suite and manually diff rendered HTML for `/`, `/threads/?view=liked`, `/threads/?sort=top`, and `/tags/<tag>` before/after to confirm byte-for-byte equivalence aside from the added `thread-list`/`thread-card` classes.
- Risks or open questions: Keep the pinned-marker vs. labels-line difference as a partial parameter rather than two partials, to avoid re-introducing duplication.
- Canonical components/API contracts touched: `templates/pages/board.php`, `templates/pages/tag.php`, new `templates/partials/thread_card.php`; no route or data contract changes.
- Status: Implemented. Extracted `templates/partials/thread_card.php` (with `showPinnedMarker`/`showLabels` params covering the board-vs-tag differences), added the `thread-card__preview` class on the body-preview paragraph, added `thread-list` to the wrapping `<section class="stack">` in both `board.php` and `tag.php`. Full test suite (`php tests/run.php`) passes.

## Stage 2
- Goal: Wire the density preference's storage and FOUC-free application, with no user-visible control yet.
- Dependencies: Stage 1 committed.
- Expected changes: Extend the existing inline theme script in `templates/layout.php:7-18` to also read a `density` query param (applying and persisting it to `localStorage` under `zenmemes-thread-density` if present) or fall back to the stored value, setting `data-thread-density="compact"` on `<html>` when active.
- Verification approach: Manually load a page with `?density=compact`, confirm `document.documentElement.dataset.threadDensity === "compact"` via browser dev tools, reload without the param, and confirm the attribute persists from `localStorage`.
- Risks or open questions: Ensure the added logic doesn't interfere with the existing theme script sharing the same `<script>` block; keep query-param parsing defensive (invalid/missing values fall back silently, matching the theme script's `try/catch` pattern).
- Canonical components/API contracts touched: `templates/layout.php`; no new routes.
- Status: Implemented. Extended the existing inline theme script in `templates/layout.php` with a second `try/catch` block that reads a `density` query param (persisting valid values to `localStorage['zenmemes-thread-density']`) or falls back to the stored value, setting `data-thread-density="compact"` on `<html>` before paint. Full test suite passes.

## Stage 3
- Goal: Add the interactive toggle button, wired to the storage mechanism from Stage 2.
- Dependencies: Stage 2 committed.
- Expected changes: New `templates/partials/thread_density_toggle.php` (single button, `data-role="thread-density-toggle"`, `aria-pressed`); new `public/assets/thread_density_toggle.js` modeled on `public/assets/theme_toggle.js` (syncs from query param/`localStorage` on load, toggles `data-thread-density` and `aria-pressed` on click, persists to `localStorage`); wire the script path via `TemplateRenderer.php:66` (`threadDensityToggleScriptPath`) and add the partial + `<script defer>` tag in `templates/layout.php` next to `theme_menu.php` (~line 49) and the theme script tag (~line 29).
- Verification approach: Load any page, click the toggle, confirm the `data-thread-density` attribute and button `aria-pressed`/label update; reload and confirm the state persists.
- Risks or open questions: None expected; button has no visual effect on card layout until Stage 4 adds CSS, so this stage is purely mechanical and independently verifiable.
- Canonical components/API contracts touched: `src/ForumRewrite/View/TemplateRenderer.php`, `templates/layout.php`, new `templates/partials/thread_density_toggle.php`, new `public/assets/thread_density_toggle.js`.
- Status: Implemented. Added the toggle button partial and `thread_density_toggle.js` (modeled on `theme_toggle.js`), wired `threadDensityToggleScriptPath` into `TemplateRenderer.php` and the `<script defer>` tag plus partial placement (next to `theme_menu.php`) in `layout.php`. `php -l` and `node --check` pass on new files; full test suite passes.

## Stage 4
- Goal: Make compact mode visually effective by hiding body previews and tightening spacing on thread-listing pages only.
- Dependencies: Stages 1-3 committed.
- Expected changes: Append to `public/assets/site.css` (after all per-theme override blocks, so cascade order wins):
  ```css
  :root[data-thread-density="compact"] .thread-card__preview { display: none; }
  :root[data-thread-density="compact"] .thread-list .thread-card { padding: 0.45rem 0.6rem; }
  :root[data-thread-density="compact"] .thread-list > * + * { margin-top: 0.35rem; }
  ```
  Plus toggle-button styling consistent with `.theme-toggle`. Add the `thread-card__preview` class to the body-preview `<p>` inside `thread_card.php` from Stage 1.
- Verification approach: Manually toggle compact mode on `/`, `/threads/?view=liked`, `/threads/?sort=top`, and `/tags/<tag>`; confirm body previews disappear and card spacing tightens on all four, and confirm no unrelated `.stack` usage (e.g. `thread.php`'s reply list, compose forms) is affected since compact rules are scoped to `.thread-list`.
- Risks or open questions: Confirm the appended rules actually win over theme-specific `.card`/`.stack` overrides (sticker/arena/word97 blocks) in each theme; if a theme's override still wins due to more specific selectors, increase specificity rather than reordering the whole stylesheet.
- Canonical components/API contracts touched: `public/assets/site.css`, `templates/partials/thread_card.php`.
- Status: Implemented. Appended compact-mode rules (hide `.thread-card__preview`, tighten `.thread-list .thread-card` padding and `.thread-list` child spacing) at the end of `site.css`; the `:root[data-thread-density="compact"]` attribute selector combined with two classes outranks the existing single-class `.card` media-query and per-theme overrides on specificity, so no reordering of existing rules was needed. Added `.thread-density-toggle` button styling (matches `.nav-link` look) near `.theme-toggle`. Full test suite passes; visual/browser verification deferred to Stage 5.

## Stage 5
- Goal: Add automated coverage and finish verification.
- Dependencies: Stages 1-4 committed.
- Expected changes: Extend `tests/LocalAppSmokeTest.php` to assert the toggle button markup (`data-role="thread-density-toggle"`) is present on board and tag routes, and that `thread_card.php`'s output (`class="card thread-card"`, title link, reply-count text) still renders correctly on both `board.php` and `tag.php` routes post-refactor.
- Verification approach: Run `php -l` on all changed PHP files and the full local smoke/test suite (`php tests/run.php` or equivalent); manually re-run the Stage 4 browser walkthrough end to end (toggle, reload persistence, `?density=` override, clean `localStorage` first-visit behavior).
- Risks or open questions: If the full suite surfaces an unrelated pre-existing failure, note it in this plan rather than expanding scope to fix it.
- Canonical components/API contracts touched: `tests/LocalAppSmokeTest.php`; no runtime contract changes.
- Status: Implemented. Added assertions for `data-role="thread-density-toggle"`, the `thread_density_toggle.js` fingerprinted asset, and `class="card thread-card"` on both board (`/threads/?view=all&sort=newest`) and tag-page routes. Full test suite passes.
  Manual browser verification (Playwright/Chromium against `./v3 start`, screenshots taken): toggle hides body previews and tightens spacing on both board and tag pages; state persists across reload and across pages via `localStorage`; `?density=compact` applies immediately on a fresh session and then persists without the param on subsequent navigation; no console errors.
  Found and fixed one bug during manual verification: the new `.thread-density-toggle` button inherited the stylesheet's generic `button { width: 100%; margin-top: 0.35rem; }` rule (meant for form buttons), which combined with `flex: 0 0 auto` (flex-basis: auto resolves to the specified width) stretched the button to fill the header row. Fixed by adding explicit `width: auto; margin-top: 0;`, matching the existing `.theme-menu__trigger` pattern that guards against the same rule.

## Stage 6 (follow-up)
- Goal: Tighten compact mode further per user feedback after testing against real data (498 threads) — reduce padding, inter-line spacing, and font size within each compact thread card.
- Dependencies: Stages 1-5 committed; real content repository copied into `state/local_repository` for realistic manual testing.
- Expected changes: In `public/assets/site.css`, reduce `.thread-list .thread-card` padding, add an explicit smaller `font-size`/`line-height`, reset default browser margins on all direct children (`> *`) and replace with a small consistent `> * + *` gap, and explicitly shrink `.meta` text within compact cards (the site-wide `.meta` rule uses `rem`, which doesn't inherit from an ancestor's `font-size`, so it needs its own override).
- Verification approach: Manual browser check (Playwright/Chromium) against the real 498-thread dataset; confirm card height drops substantially, text stays legible, and `php tests/run.php` still passes.
- Risks or open questions: None; purely a CSS density adjustment scoped to `[data-thread-density="compact"]`.
- Canonical components/API contracts touched: `public/assets/site.css`.
- Status: Implemented. Card padding reduced to `0.35rem 0.5rem`, card `font-size: 0.85rem`/`line-height: 1.3`, child margins reset with a `0.15rem` gap, `.meta` text explicitly set to `0.78rem`. Verified against real data: card height dropped from ~130px to ~58px; full test suite passes.

## Planned Commit Cadence
- Commit 1: Add thread compact view plans (this document plus `thread_compact_view_plan_v1.md`).
- Commit 2: Extract shared `thread_card.php` partial (Stage 1).
- Commit 3: Add density preference storage and FOUC-free application in layout (Stage 2).
- Commit 4: Add interactive compact-view toggle button and script (Stage 3).
- Commit 5: Add compact-mode CSS rules (Stage 4).
- Commit 6: Add test coverage and record final verification (Stage 5).

## Approval Gate
- Pause here until the user explicitly approves this plan.
- After approval, create the feature branch, commit the approved plans first, then execute the stages above in order, updating each Stage's Status line in its own commit.
