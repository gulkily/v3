> **Feature plan:** [Step 1](./docs_landing_step1_solution_assessment.md) · [Step 2](./docs_landing_step2_feature_description.md) · [Step 3](./docs_landing_step3_development_plan.md) · [Step 4](./docs_landing_step4_implementation_summary.md)

Debug mode: enabled

Follow-up cycle: predecessor [docs_discovery](../docs_discovery/docs_discovery_step4_implementation_summary.md); reused branch `feature/docs-discovery` (unmerged).

## Completion Contract

- Normal entry: Tools → Platform Docs, which opens `/docs/` with no parameters and shows the landing view.
- End-to-end outcome: open a curated guide, or search, filter, or browse to any document, then return to the same results. This must work on the dynamic host (with and without JS) and the static host (with JS, and without JS for every browse page).
- Recovery: an empty state with Clear filters and Docs home links; an unavailable search index with Retry; a no-JS static text search showing a notice and browse links.
- Verification: a locally served dynamic host and a locally built static release. Production deployment, database changes and new dependencies are out of scope.
- Release: focused PHP suites pass; prebuilt pages match dynamic results; browser acceptance passes for the known user (Stage 8); departures from the brief are listed in the Step 4 summary.

## Key Risks

- **High risk: static and dynamic divergence.** Prebuilt and live results could differ. Both hosts render through one shared path, and a parity test compares every prebuilt page with its dynamic render. Stage 6 is blocked until that passes.
- **High risk: theme contrast.** Some of the 17 themes may fail AA on `--ink-soft` or `--line`. The Stage 1 audit lists these failures before any styling. Fixes happen only where the token change is safe; anything else is reported.
- **Category taxonomy.** Category is a curated topic when one exists, otherwise the top-level folder. It is normalized to a lowercase slug, and the existing mixed-case `category=` URLs keep matching. It stays a filter because the brief and existing URLs use it; the folder tree supplies the detail.
- **Width.** The shared `.shell` is 760px, so the current 76rem `.docs-page` rule has no effect. Plan: keep the shared width. Inside it, the concept blurbs stack (3 × 18rem doesn't fit), small topic sections pair up in 2 columns, and results filters use a `Filters` `<details>` above the results instead of a sidebar. Widening the shell would touch Board and every page.
- **Skip link.** None exists site-wide. Adding one to `layout.php` changes shared chrome on every page, so it is deferred to `todo.txt` as separate work. This cycle keeps `<main>` as a valid skip target.

## Stage 1 — Data foundation and contrast audit
- Goal: Get correct categories, counts and curated topics, and know which themes fail before styling.
- Dependencies: planning commit.
- Expected changes: `PlatformDocsCatalog` gains category slugs and labels, `counts(inventory): array{group, category, folder}` and ordered curated topics. Search matches categories by slug. A scratch contrast script reads each `theme-*.css` token pair.
- Verification approach: catalog tests for no case duplicates, totals equal to the inventory, legacy `category=Architecture` URLs and curated order. The contrast report is saved as evidence.
- Risks or open questions:
  - Impact: the slug change could break saved URLs.
  - Early warning / validation: a legacy-URL test.
  - Mitigation: case-insensitive matching.
- Canonical components/API contracts touched: `PlatformDocsCatalog::inventory/search`, `_search.json` entry fields.

## Stage 2 — Landing view
- Goal: `/docs/` without parameters restores `main`'s landing page, plus search and footer quick links.
- Dependencies: Stage 1.
- Expected changes: the template branches on parameters. Adds the intro and source-path note, compact search (label, live-count placeholder, button, Browse all), topic sections using `.docs-entry` blocks (title, description, path), paired small sections, and an "All documents" panel with collection counts and links. Removes the "Starting guides" `<details>`.
- Verification approach: page tests for both views, every curated description and path, and quick-link parameters. Check without JS.
- Risks or open questions: copy is restored from `main` only (per AGENTS.md UI copy rules).
- Canonical components/API contracts touched: `platform_docs_index.php`, `PlatformDocsController` index data, `platform_docs.css`.

## Stage 3 — How it works diagram and concepts
- Goal: an accessible, themed diagram that stacks on narrow screens, with readable concept blurbs.
- Dependencies: Stage 2.
- Expected changes: re-lay the diagram as an HTML/CSS flow (boxes and arrows as tokened elements) with a text equivalent, replacing the fixed-viewBox SVG. Labels no smaller than about 0.85rem with no overlaps. Blurbs become h3 sections that stack within the shell.
- Verification approach: screenshots at 1280 and 320px and at 200% zoom in the default, Word97, Chicago and Midnight themes. Measure computed font sizes and check no label touches an arrow.
- Risks or open questions: an HTML flow may lose the key icon. Keep the icon as decorative inline SVG.
- Canonical components/API contracts touched: `.docs-architecture-diagram`, brand title and description strings.

## Stage 4 — Results view (server-rendered)
- Goal: a scannable, no-JS results view.
- Dependencies: Stage 1.
- Expected changes: Docs home link and prefilled search. A `Filters` `<details>` holding Collection and Category link lists with counts and `aria-current`, plus a nested folder tree. Removable chips and Clear all. `.docs-result` rows (title, description or first sentence, path, category badge, collection only when unfiltered) with dividers. "Page N of M" pagination and an empty state.
- Verification approach: page tests for each filter, chip removal URLs, pagination parameters and the empty state. Manual no-JS keyboard pass.
- Risks or open questions: first-sentence extraction cost. Precompute it in the inventory and measure.
- Canonical components/API contracts touched: template, catalog inventory `summary` field, `platform_docs.css` components.

## Stage 5 — JS enhancement parity
- Goal: client-side search renders the same markup and announces the result count.
- Dependencies: Stage 4.
- Expected changes: `platform_docs.js` builds the same rows, chips, filter state and pagination from `_search.json` and updates a polite live region. It switches between the landing and results views on navigation.
- Verification approach: a browser test compares server and client DOM for sample queries, plus the index failure and retry path.
- Risks or open questions: duplicated markup logic in PHP and JS. Keep JS minimal and covered by a parity test.
- Canonical components/API contracts touched: `platform_docs.js`, `_search.json`.

## Stage 6 — Static prebuilt browse pages
- Goal: no-JS browsing on the static host.
- Dependencies: Stages 4–5.
- Expected changes: `StaticArtifactBuilder` writes the unfiltered, collection, category and folder pages (about 352). `FrontController` maps a recognized single-filter and page query to its file. Any other query serves the results shell, with a no-JS notice for text search.
- Verification approach: a parity test (prebuilt vs dynamic) for every page, plus measured build time and release size against the 58 MB baseline. Real HTTP checks.
- Risks or open questions: see Key Risks (divergence). Fall back to the landing view on unknown queries.
- Canonical components/API contracts touched: `StaticArtifactBuilder`, `FrontController` static docs routing.

## Stage 7 — Accessibility and theme hardening
- Goal: meet the brief's accessibility rules across themes.
- Dependencies: Stages 2–6.
- Expected changes: visible focus, 44px and 24px targets, `prefers-reduced-motion` and `prefers-contrast: more`, and safe token fixes from the Stage 1 audit.
- Verification approach: rerun the contrast report and an automated target-size and focus check. Check reflow at 320px and 200% zoom for no horizontal scroll.
- Risks or open questions: a token change affects other pages. Change a token only when its contrast improves and no other page regresses visibly.
- Canonical components/API contracts touched: `theme-*.css` tokens.

## Stage 8 — Known-user acceptance and docs
- Goal: replay the maintainer's conditions as evidence and close the cycle.
- Dependencies: Stages 1–7.
- Expected changes: extend `tests/browser/docs_discovery.mjs` for the dynamic and static hosts × JS on/off × 1280 and 320px × 100% and 200% zoom × the four themes. Update the renderer runbook, the UI copy checklist and the list of departures.
- Verification approach: browser suite, focused PHP suites, `git diff --check`. Inspect the Docs icon (unchanged destination).
- Risks or open questions: runtime matrix. Prioritize the known-user combinations.
- Canonical components/API contracts touched: browser harness, `docs/runbooks`, `ui_copy_audit_checklist.md`.

## Enhancement Requests

- Request: reviewer brief. Disposition: planned in Stages 1–8, with departures (static no-JS text search, prebuilt pages, kept 760px shell, HTML/CSS diagram, curated list kept in catalog). Approval: Step 2 approved; Step 3 pending. Outcome: —
- Request: site-wide skip link. Disposition: separate work (it changes shared layout, which Step 2 excluded). Approval: the user asked to defer it to `todo.txt`. Outcome: added to `todo.txt` 2026-10-20.
