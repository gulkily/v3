> **Feature plan:** [Step 1](./docs_discovery_step1_solution_assessment.md) · [Step 2](./docs_discovery_step2_feature_description.md) · [Step 3](./docs_discovery_step3_development_plan.md) · [Step 4](./docs_discovery_step4_implementation_summary.md)

Debug mode: enabled

## Status

All five stages completed on `feature/docs-discovery`; planning commit `00a1511a`. Production publication is outside scope. Focused verification passed; the full repository suite was not run.

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

## Stage 3 - Safe supporting assets and related-document recovery
- Changes: discovered supported text/configuration, TXT/CSV/JSON/HTML/PDF and vendored LICENSE; escaped non-Markdown previews and attachment-only downloads with nosniff; root/asset/directory links, inherited folder browsing, oversized previews and missing-document library/retry actions. Hidden/control files and unrelated executable files are excluded.
- Verification: focused catalog/page suite passed 24/24. New coverage checks supporting formats, escaped HTML/PHP examples, PDF preview recovery, exact download bytes, root/directory links and rejected downloads. JavaScript syntax and diff checks passed. An old Markdown-only rejection fixture was updated to an unsupported PHP file because TXT is now approved.
- Notes: Stage 2 commit `b42ca8d3`. PDF bodies are not extracted for search. Real HTTP headers and browser recovery remain acceptance checks; static asset copies follow in Stage 4.

## Stage 4 - Publish the complete library
- Changes: one scoped inventory snapshot drives static enumeration/search; publish every reader and byte-verified attachment, root routes and a static missing-document page. Shared refresh removes stale readers/downloads. Static host serves search JSON and attachment headers from the active release and preserves query-based browser navigation.
- Verification: `php tests/run.php PlatformDocsStaticTest` passed complete 1,314-file inventory parity, every reader/download hash, root/workflow/static-query routes, inert HTML, missing recovery and stale-file removal on refresh (156.59 s including full build and refresh). Initial full build passed in 77.93 s. Captured release has 57,924,528 bytes; search JSON 8,471,559 bytes. Real HTTP checks passed for index/root/HTML wrapper, JSON content type, attachment disposition and missing 404 recovery. PHP lint and diff checks passed.
- Notes: Stage 3 commit `d4ed4ee5`. Full publication exposed prose example fingerprints being treated as runtime dependencies; asset checks now inspect actual markup/runtime configuration. Chromium acceptance is still pending Stage 5: an initial Tools journey timed out waiting for a visible result count and needs diagnosis. Prior-release activation/failure recovery will be exercised there. No production deployment.

## Stage 5 - Reader acceptance and maintenance guidance
- Changes: library controls precede the architecture disclosure; starting guides retain titles/paths without repeated descriptions. Removed duplicate empty-state reset links. Added keyboard focus for inert asset previews, corrected root document titles, rejected hidden-file symlink aliases, sanitized search-text encoding and avoided full-inventory scans for unrelated on-demand routes. Updated the renderer/library runbook, browser harnesses and UI copy checklist.
- Verification — passed: 39 distinct focused PHP checks across catalog, page, static, navigation, profile and selected host/fingerprint regressions. Final full inventory/download parity plus refresh/removal regression passed in 129.19 s; on-demand root/workflow generation passed. PHP lint, JavaScript syntax and `git diff --check` passed.
- Verification — passed: 16 Chromium discovery scenarios (dynamic/static × 1280/390px × default/Word97/Chicago/Midnight), exercising Tools entry, keyboard search/read/return, history filters, empty/reset, inert assets, HTTP download headers and missing/retry recovery. Injected search-index failure/retry passed. Existing eight no-JavaScript Markdown reader/theme/scroll/fragment scenarios passed. Both Docs icon surfaces were measured and visually inspected in all three icon themes; mapping changes were unnecessary.
- Verification — passed: real shared refresh, injected failed-refresh retention, previous-release activation and restoration in isolated local storage. Final index-only regeneration also exercised the existing on-demand builder. Full static publication contains the same eligible paths and download hashes as its source inventory; additions are tested at inventory level and all discovered files are published automatically. Stale readers/downloads are removed on refresh.
- Notes: Stage 4 commit `4ddb6812`. Initial browser failure was a fixture server's image-root configuration, corrected without product changes. Anonymous static scenarios suppress theme preference cookies; existing personalized dynamic fallback remains intact. PDF/oversized bodies are not full-text indexed, but remain discoverable and downloadable. Index size is approximately 8.5 MB uncompressed; the captured 1,314-file release was approximately 58 MB. The final regression includes the subsequently added acceptance evidence file (1,315 eligible files).
- Evidence: [Acceptance measurements](./docs_discovery_acceptance_evidence.json); screenshots and isolated releases retained under `/tmp/docs-discovery-acceptance/`.
- Not run: full repository suite and the copied-package dependency-removal harness; verification focused on changed library/host behavior, and renderer dependencies were unchanged.
- Inapplicable: production deployment, database migration and external-service verification; none is part of the approved cycle.
