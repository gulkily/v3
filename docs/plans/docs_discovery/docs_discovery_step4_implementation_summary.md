> **Feature plan:** [Step 1](./docs_discovery_step1_solution_assessment.md) · [Step 2](./docs_discovery_step2_feature_description.md) · [Step 3](./docs_discovery_step3_development_plan.md) · [Step 4](./docs_discovery_step4_implementation_summary.md)

Debug mode: enabled

## Status

Implementation in progress on `feature/docs-discovery`; planning commit `00a1511a`. Production publication is outside scope.

## Requests and decisions

- Request: follow the Git repository or copy its structure? Disposition: use repository folders as the source of truth; static publication generates a build snapshot. Approval: clarified before Step 3 approval. Outcome: no separately maintained directory catalog.

## Stage 1 - Browse and open automatically discovered Markdown
- Changes: discovered repository Markdown and four root documents, retained curated metadata, added folder browsing with 40-result pages and oversized-document states. No separate folder catalog.
- Verification: `php tests/run.php PlatformDocsCatalogTest PlatformDocsPageTest` passed 20/20; coverage includes complete unique inventory, root routes, automatic addition/removal, oversized states and symlink/traversal rejection. Repository inventory measured separately; `git diff --check` passed.
- Notes: inspected the three configuration examples: placeholder credentials and example paths. Asset publication remains deferred to Stage 3; static parity remains deferred to Stage 4. No schema changes.

## Stage 2 - Search, narrow and return
- Changes: full-text search with folder/category/collection filters, bounded results, current-guidance-first ordering, counts, reset/empty/error states, shareable query state and reader return links. A shared inventory powers PHP fallback and the browser search index; browser related-document links carry return state.
- Verification: `php tests/run.php PlatformDocsCatalogTest PlatformDocsPageTest` passed 22/22, including body-only matches, combined filters, history/workflow searches, empty/reset behavior and server-rendered return state. `node --check public/assets/platform_docs.js` and `git diff --check` passed. Inventory plus search measured 108.8 ms; full JSON index measured 7,814,573 bytes uncompressed.
- Notes: Stage 1 commit `e69cac54`. Search data loads once per library visit, with retry on failure; static publication/serving is Stage 4 and browser acceptance is Stage 5. Supporting PDFs will be searchable by path/title, not extracted binary text.
