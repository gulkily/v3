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

## Stage 3 - How it works diagram and concepts
- Changes:
  - Replaced the fixed-viewBox SVG with an ordered list (`.docs-flow`) labeled by the profile's architecture title. The four boxes and three labeled edges (signed write, builds, serves) are real text, so the list is the text equivalent and no hidden duplicate is needed. Arrows are decorative CSS (`aria-hidden`). The key icon stays as a decorative inline SVG drawn in `currentColor`.
  - Vertical by default, with labels set beside the arrows so they never touch. It switches to a row only when the panel is at least 52rem wide (container query), which the shared 760px shell never reaches. Boxes are capped at 22rem and centered.
  - All colors come from `--ink`, `--ink-soft`, `--line`, `--panel` and `--input-bg`. Box backgrounds use `--input-bg` because chicago's `--page-bg` is the teal desktop. Diagram text is at least 0.875rem.
  - The concept blurbs stack, and become three columns only when each column gets at least 18rem (a 58rem container).
  - Removed the now-unused `architectureDescription` profile string.
- Verification:
  - `php tests/run.php PlatformDocsCatalogTest PlatformDocsPageTest ProfilePresentationContentTest`: passed 28/28. Diagram assertions now check the labeled list for every site profile, 4 nodes and 3 decorative arrows, and the absence of command text.
  - `php -l`, `git diff --check`: passed.
  - Chromium screenshots: default at 1280 and 320px, chicago at 1280, and midnight at 200% zoom (320 CSS px). No horizontal overflow, no label overlapping an arrow, and no diagram text below 0.875rem (the only text under 0.85rem on the page is the site's existing inline `code` at 13px in the blurbs).
- Notes:
  - Departure: the brief allowed either an SVG with `<title>`/`<desc>` or HTML/CSS. HTML/CSS was chosen because a fixed SVG can only shrink or scroll, not stack.
  - Inside the 760px shell the diagram is always vertical. A horizontal layout would have to break code paths mid-word to fit.

## Stage 4 - Results view (server-rendered)
- Changes:
  - Removed the select-based library form. The intro card's search now serves both views: prefilled with `q`, carrying active non-search filters as hidden inputs, with "← Docs home" shown only in the results view.
  - Results section ("Documents"):
    - Removable chips for each active filter (accessible names like "Remove Category: Architecture"), plus Clear all.
    - A polite status count and the existing index-error/Retry.
    - A closed "Filters" `<details>` holding Collection and Category link lists with server counts, and a nested `<details>` folder tree. Branches open along the active folder, and each branch starts with an "All in …" link because a summary can't contain a link. The active link carries `aria-current="page"`, bold text and a bar (`--active-*` tokens), so state doesn't rely on color.
  - Choosing a filter replaces the other filters and keeps the search, so every filter link is a single-filter URL that Stage 6 can prebuild. Legacy combined URLs still work.
  - Result rows are an ordered list with 1px `--line` dividers. Each row shows the title, a one-line summary (curated description, otherwise the first prose sentence via `PlatformDocsCatalog::summary()`; 409 mostly list-only plans have none), the path, and a category badge. The collection badge appears only when no collection filter is active and the row isn't Current guidance.
  - Pagination reads "← Previous · Page N of M · Next →", with `aria-current` on the page indicator and the active filters kept. The empty state offers Clear filters and Docs home.
  - The landing view sends an empty results shell (no rows), and the controller no longer builds the unused folder option list.
  - `platform_docs.js` got the minimum needed to keep working against the new markup. Rendering parity is Stage 5.
- Verification:
  - `php tests/run.php PlatformDocsCatalogTest PlatformDocsPageTest ProfilePresentationContentTest`: passed 29/29. The new `testResultsViewFiltersWithLinksChipsAndPages` covers pagination links and the page indicator, filter `aria-current`, chip names, Clear all, no repeated collection badge, the legacy mixed-case category URL, hidden filter inputs, summaries, folder-tree open state and nested "All in" links, and the empty state.
  - `node --check`, `git diff --check`: passed.
  - Chromium with JS disabled: results at 1280px, 320px, and 200% zoom (320 CSS px), with filters opened by clicking the summary. No horizontal overflow; the active filter and tree markers are visible.
- Notes:
  - Measured the search index at 8,692,028 bytes uncompressed with summaries (+~0.2 MB versus Stage 4 of the predecessor cycle).
  - Filter counts are library-wide, not narrowed by the current search. This keeps prebuilt pages identical to their dynamic versions.
  - Departure: the brief's sidebar on wide screens isn't used, because the shared 760px shell (kept per Step 3) leaves no room. Filters stay in the `<details>` at every width.

## Stage 5 - JS enhancement parity
- Changes:
  - `platform_docs.js` renders the same chips, hidden filter inputs, filter `aria-current` and links (keeping the search), folder-tree open state, rows (summary, path, category badge, collection badge rule), page indicator and Previous/Next, empty state and count as the server does. The count stays in the server's `role="status"` element, so changes are announced politely.
  - Same-page `/docs/?…` links (landing quick links, Browse all, filters, chips, pages) and the search form update in place with history entries. After a link navigation, focus moves to the "Documents" heading (`tabindex="-1"`). Back and forward switch between the landing and results views.
  - Return-link parameters keep the URL's own order, matching the server.
  - Template data hooks: `data-docs-chips` (always rendered, hidden when empty), `data-docs-filter`/`-value`/`-label` on filter links, `data-docs-folder-branch` on tree branches.
- Verification:
  - New `tests/browser/docs_landing.mjs` (Chromium, isolated dev server): for 6 queries (paged collection, legacy mixed-case category with search, nested folder, empty search, browse all, search) it navigates in place from the landing page, as the static host does, and compares the result with the JS-off server render (chips, count, rows HTML, page text, empty state, `aria-current` targets, open branches, hidden inputs, search value): all identical. Also passed: a landing quick link moves to results with focus on the heading, Back restores the landing view, and an injected index failure shows Retry and recovers.
  - `php tests/run.php PlatformDocsCatalogTest PlatformDocsPageTest ProfilePresentationContentTest`: passed 29/29. `node --check`, `git diff --check`: passed.
- Notes:
  - Rendering logic is duplicated between PHP and JS. The parity test is the guard, and Stage 8 runs it on the static host too.
  - The predecessor harness `tests/browser/docs_discovery.mjs` still drives the removed select form and `data-docs-clear`. Stage 8 updates it.

## Stage 6 - Static prebuilt browse pages
- Changes:
  - `PlatformDocsCatalog::browsePath(query)` maps a query with no search text and at most one filter (empty parameters ignored, category matched by slug, page 1–99999) to `/docs/_browse/{all|group/…|category/…|folder/…}/{page}.html`. `browseQueries(inventory, pageSize)` lists every such page. `PAGE_SIZE` replaces the hard-coded 40.
  - `StaticArtifactBuilder` writes all browse pages (390 for the current inventory: all, 3 collections, 14 categories, 204 folders, by page), plus `/docs/_search.html`, a results shell rendered with no rows. Its `<noscript>` notice says search needs JavaScript in this copy and points to the filters.
  - `FrontController` (static release path): `/docs/` with any results parameter serves the matching browse page, otherwise the search shell (search text, combined filters, out-of-range pages, unknown folders). `/docs/` without parameters still serves the landing page. `Application` serves `/docs/_search.html` like `/docs/_missing.html`.
  - Size work: server-rendered results pages no longer include the hidden landing sections (the browser only switches views in place from the landing page). Filter links carry their filter only in the `href` (no per-link classes or data attributes), and the JS reads it from there.
- Verification:
  - `php tests/run.php PlatformDocsStaticTest`: passed 2/2 (183.5 s). It covers every browse page existing and matching a live `Application` render of the same query, real `FrontController` routing for collection, legacy-case category, nested folder and page 2, and the shell with its notice for search, combined filters, page 999 and an unknown folder. The landing page has no notice.
  - Real HTTP against a built release (isolated host on port 8192, fixture repository so the image flag matches): routes above confirmed over HTTP.
  - `tests/browser/docs_landing.mjs` with `DOCS_CLIENT_BASE_URL` on the static host: all six JS-vs-server parity cases, landing → results focus and Back, and index failure/Retry passed.
  - `php tests/run.php PlatformDocsCatalogTest PlatformDocsPageTest ProfilePresentationContentTest`: passed 29/29. `node --check`, `git diff --check`: passed.
- Measurements (fixture build, scratch directory):
  - Full build 80.7 s (the predecessor recorded 77.93 s for 1,314 files).
  - Release 87.9 MB against the predecessor's ~58 MB. Browse pages account for 27.4 MB (390 pages at ~70 KB; ~15 KB gzipped). Before trimming they were 43.5 MB at ~115 KB each.
  - Search index 8.7 MB.
- Notes:
  - Decision for the user: folder pages are 241 of 390 and about 17 MB, mostly the plans tree repeated on every page. Dropping folder prebuilds would send static no-JS folder links to the search shell instead.
  - The static test now takes about 3 minutes (it was about 2.6 minutes in the predecessor) because of the per-page live comparison.
