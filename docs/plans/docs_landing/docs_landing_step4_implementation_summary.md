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

## Stage 2 - Landing view
- Changes:
  - `/docs/` with no parameters renders the landing view. Any of `q`, `folder`, `category`, `group` or `page` (even empty) renders the results view. Both views are in the same document, with server-side `hidden`, so the static `index.html` can switch views with JS (`showView()` in `platform_docs.js`). The search index is fetched only when results are shown.
  - The intro card holds the H1 and the restored intro paragraphs from `main` (including the source-path note), a compact labeled search (placeholder uses the live count, prefilled with `q`) and "Browse all docs →" (`?page=1`).
  - "How it works" is an open section again (Stage 3 re-lays it). The "Starting guides" `<details>` is removed.
  - Curated topic sections use the `.docs-entry` blocks: a title link in `--body-font` with a quiet `--line` underline that becomes solid on hover or focus, the description, and the path in `--code-font`/`--ink-soft` on `--panel`. Topics with several guides span the row, and adjacent single-guide topics pair up (`auto-fit`, `minmax(min(100%, 20rem), 1fr)`). The curated list order now puts CLI Reference after Trust and Identity so pairs read left to right in source order, without dense reordering.
  - "All documents" panel: live count sentence, Browse all, and collection quick links with counts (`?group=…`) as a list with non-announced separators.
  - Links and controls have at least 2.75rem targets. `.docs-page [hidden]` overrides author `display` rules.
- Verification:
  - `php tests/run.php PlatformDocsCatalogTest PlatformDocsPageTest`: passed 25/25. The landing test now checks section order, every curated description and path, quick-link and browse URLs, the live-count placeholder, the hidden library on landing, the hidden landing on results, and the prefilled query.
  - `node --check`, `php -l` and `git diff --check`: passed.
  - Chromium on an isolated dev server (port 8191): landing at 1280 and 320px in default, 1280 in Word97, and 320 CSS px at 200% in Midnight, all with no horizontal page overflow. No-JS `?group=Workflow` renders the server results.
- Notes:
  - Restoring the path note and descriptions reverses the earlier UI-copy cleanup at the user's approved request. A new UI-copy checklist entry records this, with verification pending Stage 8.
  - The local server on port 8004 runs from this checkout and no longer shows `main`. Port 8007 serves the `v3-codex-3` checkout. Both were left running and untouched.
  - Known interim state: the results view still contains the Stage 1 select form, so it shows two search inputs. Stage 4 replaces it. The diagram still scrolls horizontally inside its frame on narrow screens (Stage 3).
