> **Feature plan:** [Step 1](./docs_discovery_step1_solution_assessment.md) · [Step 2](./docs_discovery_step2_feature_description.md) · [Step 3](./docs_discovery_step3_development_plan.md) · [Step 4](./docs_discovery_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

The docs page should have all the docs accessible and easy to find. Please write a Step 1 for this.

## Understood Intent

Make the website’s existing Platform Docs page a complete, usable documentation entry point, without requiring a separate catalog edit whenever a document is added. This is a new planning cycle; no branch reuse or implementation is authorized yet.

## Problem and evidence

The page lists only 10 manually catalogued documents against 1,203 tracked Markdown files under `docs/` (1,136 in plans), so most documentation is undiscoverable despite existing direct-link support.

[Catalog](../../../src/ForumRewrite/Docs/PlatformDocsCatalog.php), [page](../../../templates/pages/platform_docs_index.php), and [static builder](../../../src/ForumRewrite/Host/StaticArtifactBuilder.php) currently share the curated list; the reader accepts safe Markdown beneath `docs/`, while non-Markdown examples/PDFs and root entry documents need an explicit viewing policy.

## Option A — Expand the manual catalog

List every document with an edited title, category and description, and require new documents to be registered.

- Pros: consistent descriptions and deliberate organization; extends the current presentation.
- Cons: substantial upkeep at this scale; omission remains possible unless completeness is enforced; a giant list still needs search and filtering.

## Option B — Automatic directory browser

Discover eligible documentation automatically and present a searchable folder tree with titles and paths.

- Pros: complete coverage without individual registration; familiar repository structure; low editorial upkeep.
- Cons: internal folder names and repeated plan titles are hard to navigate; current guidance competes with historical/workflow material; little help choosing a starting point.

## Option C — Automatic inventory with curated entry points (recommended)

Automatically discover all eligible documents, retain a small set of recommended guides, and provide a complete searchable library with category/history filters and folder browsing.

- Pros: completeness does not depend on curation; readers can start with a feature guide or find any historical document; existing descriptions can improve important results.
- Cons: more discovery/search behavior to maintain; document identity, historical labeling and deployed/static freshness must agree across views.

## Recommendation and viable slice

Choose C: from **Tools → Platform Docs**, readers can browse or search the complete library, open a document, and follow related documentation without knowing repository paths.

- Cover all eligible Markdown under `docs/`, including plans, archives, research and vendored workflow instructions. Keep history/workflow sections clearly labeled and reachable; include them in all-document search while ranking current guidance first. Do not dump every entry into the landing view.
- Search titles, paths, headings and document text; narrow by category/folder and current versus historical material. Show clear result counts, empty states and a way to clear filters. Preserve existing document URLs and provide navigation back to results.
- Explicitly inventory root entry documents such as README/CONTRIBUTING and non-Markdown documentation such as examples/PDFs in Step 2; provide safe viewing/download links for supported documentation assets. “All docs” does not authorize executing examples or exposing arbitrary repository/private instance files.
- Reuse one eligibility inventory for discovery, links and publication. Newly added eligible documents must appear automatically after the normal publication cycle; static output must remain usable and offer recovery from stale indexes or missing documents.
- Verify complete inventory coverage, representative feature/operations/history searches, mobile and keyboard use, safe rendering/path boundaries, and normal static/dynamic delivery. Broad content rewrites, archive reorganization and replacing the documentation system are outside this slice.
