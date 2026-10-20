> **Feature plan:** [Step 1](./docs_landing_step1_solution_assessment.md) · [Step 2](./docs_landing_step2_feature_description.md) · [Step 3](./docs_landing_step3_development_plan.md) · [Step 4](./docs_landing_step4_implementation_summary.md)

Debug mode: enabled

Follow-up cycle: predecessor [docs_discovery](../docs_discovery/docs_discovery_step4_implementation_summary.md); reused branch `feature/docs-discovery` (unmerged). Planning commit `93e98f89`.

## Requests and decisions

- Request: site-wide skip link. Disposition: separate work, recorded in `todo.txt` at the user's request (the `todo.txt` edit is left uncommitted for the user). Approval: deferred before Step 3 approval. Outcome: not part of this cycle.

## Stage 1 - Data foundation and contrast audit
- Changes:
  - `PlatformDocsCatalog::categorySlug()`: inventory `category` is now a lowercase slug, with a new `categoryLabel` (the curated label when one exists, otherwise the capitalized folder name). `architecture` and `Architecture` now share one key.
  - `PlatformDocsCatalog::topics()` (curated guides grouped in listed order) and `counts(inventory)` (collection, category, and folder counts, where folder counts include subfolders) are computed on the server.
  - Search matches categories by slug, so legacy `category=Architecture` and `category=Extending the Platform` URLs keep working. `platform_docs.js` mirrors the slug matching.
  - The controller uses `topics()` and `counts()`; the folder option list now comes from the counts. The temporary category select shows labels with slug values (Stage 4 replaces it).
  - Added `tests/theme_contrast_audit.py` (token-pair contrast for every registered theme, alpha composited over the page background).
- Verification:
  - `php tests/run.php PlatformDocsCatalogTest PlatformDocsPageTest`: passed 25/25, including the new `testCategoriesMergeCaseVariantsAndCountsMatchInventory` (no case duplicates, totals equal the inventory, legacy URLs, topic order).
  - `node --check public/assets/platform_docs.js`, `php -l` on changed PHP, and `git diff --check`: passed.
  - Contrast audit: [report](./docs_landing_contrast_audit.json). Only vapor passes every pair. Summary of failures on tokens this page relies on:
    - `--line` below 3:1 on `--panel` or `--input-bg` in 14 themes (all except vapor and lcd).
    - `--ink-soft` below 4.5:1 on `--page-bg` in lcd, chicago, sticker, arena, word97 and midnight, and on `--panel` in arena (3.66).
    - `--ink` on `--page-bg` in chicago (3.96). `--active-line` on `--panel` in chicago and qdb. `--active-ink` on `--active-bg` in mitrapclub (3.85).
- Notes:
  - Design consequences for Stages 2–7:
    - `--line` is used only for decorative dividers, which WCAG 1.4.11 exempts. Chips, badges and filter state are identified by text and shape, not by borders alone.
    - Paths in `--ink-soft` sit on `--panel`, never directly on `--page-bg`.
    - Theme token fixes are evaluated in Stage 7.
  - Audit limits: it reads CSS custom properties only. Image or gradient backgrounds (for example chicago's) and theme-scoped selector overrides are not modeled, and chicago inherits the default `--active-*` tokens. Browser measurement in Stage 8 confirms the actual results.
  - The existing `paned_users_filter_list` partial is a JS listbox, not a link list, so it can't serve the no-JS filters. Stage 4 needs a new link-list component.
  - The inventory count includes this cycle's new planning files (1,317+), so the count is not hard-coded anywhere.
