> **Feature plan:** [Step 1](./docs_landing_step1_solution_assessment.md) · [Step 2](./docs_landing_step2_feature_description.md) · [Step 3](./docs_landing_step3_development_plan.md) · [Step 4](./docs_landing_step4_implementation_summary.md)

Debug mode: enabled

Follow-up cycle: predecessor [docs_discovery](../docs_discovery/docs_discovery_step4_implementation_summary.md); intended reused branch `feature/docs-discovery` (unmerged). Direction: Step 1 Option B (approved).

## Problem

`/docs/` now leads with a full-library tool and hides the curated orientation (intro, how-it-works, topic guides with descriptions) that `main` offered. The results list repeats the same metadata on every row and is hard to scan, especially for a low-vision reader.

## User stories

- As a new reader, I want a curated landing page so that I understand the platform and reach the key guides first.
- As a contributor, I want to search or browse every document from that page so that I can find anything else.
- As the low-vision maintainer, I want every view readable and operable at 200% zoom, at 320px width, by keyboard and in every theme so that I can use and review the docs myself.

## Core requirements

- `/docs/` with no parameters shows the landing view: the restored intro and source-path note, a compact search form with a live document count, a "How it works" diagram and concept blurbs (accessible, theme-token colors, stacked on narrow screens), the curated topic sections with titles, descriptions and paths, and an "All documents" panel with collection quick links and counts.
- `/docs/` with any existing parameter (`q`, `folder`, `category`, `group`, `page`) shows the results view. It has a home link, a prefilled search, Collection, Category and Folder filters with counts and an active state, removable filter chips and Clear all, compact rows (title, description when available, path, category; collection only when it differs from the filter), "Page N of M" pagination and an empty state. Every current URL keeps working.
- No-JS behavior: on the dynamic host, every view works without JS. On the static host, the landing page and the unfiltered, Collection, Category and Folder result pages are prebuilt and work without JS. Static free-text search needs JS; without JS, the reader sees a notice linking to the prebuilt browse pages. Only JS adds the live result-count announcement.
- Category values differing only in case collapse into one key with one display label, and the server computes all counts. Curated guides stay defined in one data source, so adding a guide needs no template change.
- The page uses only existing theme tokens and the shared header, nav and theme menu, with no nested panels. It meets WCAG 2.2 AA contrast for every theme on the tokens it uses, provides a skip link, landmarks and a logical heading order, shows visible focus, meets target sizes, respects reduced-motion and more-contrast preferences, and never signals state by color alone.

## Delivery scope and completion boundary

Work type: **application change**. It covers the docs index template, catalog data and counts, the docs stylesheet and script, static build and static routing for prebuilt result pages, tests, the renderer runbook and `ui_copy_audit_checklist.md`. Out of scope: the document reader page, the shared header and nav, content rewrites, new dependencies and a build step. Whether to widen the shared site content width is decided in Step 3. The docs page already has its own width.

Complete when a reader opens **Tools → Platform Docs**, reads the landing view, opens a curated guide, then searches or browses to any other document and returns to the same results. This must work on the dynamic and static hosts, with and without JS (within the static text-search limit above). Empty results and an unavailable search index each offer a working way back. Release requires focused tests, inventory and count parity, and browser checks across themes, widths and zoom.

## Known user and environment

The low-vision maintainer browses the local dynamic (port 8007 equivalent) and static hosts in their chosen theme at their default font size. The main failure risks are text that is too small or low in contrast, horizontal scrolling and lost focus. They must be able to zoom to 200%, tab through every control with a visible focus indicator, and clear filters or return home from any state. Evidence: screenshots and measurements at 1280px and 320px, at 100% and 200% zoom, for each affected theme, plus a per-theme contrast report naming any failing token.

## Risks

- **Static and dynamic divergence:** prebuilt results could drift from PHP results or miss a parameter combination. Validate early by comparing prebuilt and dynamic output for every filter value. Mitigation: one shared rendering path, with the static host mapping filter parameters to prebuilt files and falling back to the landing view.
- **Category taxonomy:** categories mostly duplicate top-level folders (`plans` = 1,239 = Plans and history), and curated topics override 10 of them. The filter may stay redundant even after normalization. Validate by listing normalized categories against collections before UI work. Mitigation: decide in Step 3 whether Category shows curated topics with folder fallbacks or is merged into the folder tree.
- **Theme contrast failures:** some themes may fail AA on tokens the page relies on (`--ink-soft`, `--line` for UI). Validate with an automated token contrast audit first. Mitigation: report failures, and fix them only where a theme token change is safe; otherwise record them.
- **Build and size growth:** about 350 extra prebuilt pages. Validate by measuring build time and release size against the 58 MB baseline in an early stage.

## Shared component inventory

The work extends the canonical `PlatformDocsCatalog` (inventory, curated entries, search, adding normalization and counts), `PlatformDocsController` (index), `templates/pages/platform_docs_index.php`, `public/assets/platform_docs.css` and `.js`, the static-host docs routing in `StaticArtifactBuilder` and `FrontController`, and the theme token files in `public/assets/theme-*.css` (audit only unless a fix is approved). It reuses the existing card, active and focus styles, the Tools launcher and subnav entry (no icon changes) and the reader return links. New reusable classes cover the doc entry, result row, filter list and chip. These patterns don't exist yet in the shared styles.

## Simple user flow

1. Open Platform Docs and read the landing page or pick a curated guide.
2. Search, or choose a collection quick link or "Browse all".
3. Narrow results with filters or chips, page through them and open a document.
4. Return to the same results; clear filters or go back to the docs home page.

## Success criteria

- Both hosts render the landing view for `/docs/`. Every existing parameter URL renders matching results with or without JS, except static free-text search without JS, which shows its notice.
- Category counts contain no case duplicates. Collection and category counts equal the inventory totals.
- At 320px and at 200% zoom, no view scrolls horizontally in default, Word97, Chicago and Midnight. All diagram text measures at least about 0.85rem.
- Keyboard-only journeys reach every control with visible focus. The token contrast report covers every theme.

## Enhancement Requests

- Request: reviewer brief (Step 1 Original Query). Disposition: adopted as above, with recorded departures (static no-JS text search; prebuilt result pages; existing SVG diagram retained and improved). Approval: Step 1 approved; Step 2 pending. Outcome: —
- Request: data-driven curation via front matter or a list file. Disposition: keep the existing single curated list in `PlatformDocsCatalog`, adding topic order. It is already one data source, and front matter would touch many documents. Approval: pending Step 2.
