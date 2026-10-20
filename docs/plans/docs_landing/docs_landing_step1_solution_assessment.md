> **Feature plan:** [Step 1](./docs_landing_step1_solution_assessment.md) · [Step 2](./docs_landing_step2_feature_description.md) · [Step 3](./docs_landing_step3_development_plan.md) · [Step 4](./docs_landing_step4_implementation_summary.md)

Debug mode: enabled

Follow-up cycle: predecessor [docs_discovery](../docs_discovery/docs_discovery_step4_implementation_summary.md); intended reused branch `feature/docs-discovery` (unmerged).

## Original Query

Here are some suggestions from our sighted reviewer. I think we should use Railspike to work on them. Please note that the sighted agent doesn't have access to the full codebase, which you have better visibility into. These suggestions are not set in stone; we're working together to make a good product. (Port 8004 refers to `main` before this branch; port 8007 is this branch.)

Reviewer brief (verbatim, as pasted; it ends mid-sentence at "Deliverables 1."):

> You are reworking the zenmemes "Platform Docs" index page (/docs/). There are two versions: OLD (port 8004): a curated landing page… NEW (port 8007, current code): it adds search, Folder/Category/Collection filters, and a paginated list of all 1,315 docs… GOAL: combine them. The OLD page is the landing experience. The NEW search and index are a secondary tool for finding anything else.
>
> Sections: Use what you know about the system · Page behavior (landing with no params, results with any param, keep parameter names, works without JS, no dependencies/build step) · Landing view (intro + compact search + "Browse all docs →"; accessible, token-colored, stacking "How it works" diagram and ≥18rem concept columns; data-driven curated topic sections with block entries and side-by-side small sections; "All documents" footer with collection quick links and counts) · Results view (header, "← Docs home", prefilled search, Collection/Category link-list filters with counts in a sidebar or narrow `<details>`, folder tree or enhanced combobox, removable chips + "Clear all", compact rows with description/path/category badge, no repeated "Current guidance", 1px dividers, "Page N of M" pagination, empty state) · Data fixes (case-normalized category slugs with Title Case display, server-side counts) · Visual system (existing theme tokens only, new tokens added to every theme, unchanged header/nav/theme menu, no nested panels, font roles, content-width decision, reusable BEM components in the shared stylesheet) · Accessibility (WCAG 2.2 AA in every theme, 200% zoom and 320px, visible focus, skip link, landmarks and heading order, aria-live count, reduced motion/more contrast, target sizes, no color-only state) · Deliverables (truncated).

The full brief is kept in the conversation of 2026-10-20; Step 2 will restate each requirement it adopts.

## Understood Intent

Restore `main`'s curated landing page as the default `/docs/` experience. The discovery search and library added on this branch become a secondary results view, raised to the reviewer's accessibility and theme bar. Where the brief conflicts with how the system actually works, keep its intent and record the departure.

## Problem

The branch made all 1,315 documents findable, but the curated orientation that new readers need is now hidden in a collapsed `<details>`, and the results list is repetitive and hard to scan.

## System facts that shape the options

- The curation is already data: `PlatformDocsCatalog::entries()` holds the title, category, and description for 10 guides. The template simply stopped rendering descriptions and topics.
- The diagram already has `role="img"`, `<title>`, and `<desc>`. Still needed: larger text, label spacing, token colors, and a vertical layout on narrow screens.
- Folder-derived categories (`architecture`) collide with curated ones (`Architecture`) because they differ only in case.
- **The static release serves a single `/docs/index.html` for every query, and only JavaScript renders the results.** A no-JS reader on the static host would always see the landing view. Requirement: "everything works without JS".
- `.docs-page` is already widened to 76rem in `platform_docs.css`, so the docs page has its own width and doesn't share Board's. The "545px" observation needs checking per theme.

## Option A — One template, two views; static no-JS search falls back to live PHP

The template branches on whether any parameter is present. In the static release, query URLs are handed to the dynamic application instead of `index.html`, so search works without JS everywhere.
- Pro: full no-JS parity, and only one rendering path.
- Con: query requests skip the snapshot release, which reverses a Stage 4 decision (static docs served from the active snapshot). On a shared host it adds PHP load on every search.

## Option B — One template, two views; prerender finite filter pages, JS for free text

Same two views. The static build also writes the results pages for each Collection and Category, which is a small finite set. Free-text search and folder filters in the static release need JS, with a clear no-JS notice and a link to the prerendered browse pages.
- Pro: keeps snapshot serving. Quick links, filters, and pagination work without JS on both hosts.
- Con: static no-JS text search is unavailable (an explicit, recorded departure). It also adds a modest number of build artifacts, and each filter combination is paginated.

## Option C — Separate landing and results routes

The landing page stays at `/docs/`, and results move to a new route such as `/docs/search/`. `/docs/?…` redirects there.
- Pro: the simplest template split.
- Con: it breaks the "same URL, same parameters" requirement and the existing return links, and it needs a redirect on the static host. It adds risk without benefit.

## Recommendation

**Option B.** It keeps every current URL and the snapshot architecture, and it gives no-JS readers on both hosts working landing, browse, filter, and pagination pages. The one gap is free-text search on the static host without JS, which we accept and state openly. The dynamic host keeps full no-JS search. For the folder control, recommend the nested `<details>` tree (no JS) over a combobox. It's a viable vertical slice: one entry point (`/docs/`) leads to finding and opening a document and back, with empty and error recovery, likely in about 5–6 stages.

Open points for review:
- Whether to accept the static no-JS search gap (B) or the live-PHP fallback (A).
- Whether to keep the curated list in the PHP catalog (current) or move it to a repository data file.

## Enhancement Requests

- Request: reviewer brief (above). Disposition: under assessment in this cycle. Approval: pending Step 1. Outcome: —
