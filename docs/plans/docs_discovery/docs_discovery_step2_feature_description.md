> **Feature plan:** [Step 1](./docs_discovery_step1_solution_assessment.md) · [Step 2](./docs_discovery_step2_feature_description.md) · [Step 3](./docs_discovery_step3_development_plan.md) · [Step 4](./docs_discovery_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Platform Docs exposes only a small curated list, leaving most repository documentation difficult to find. Readers need a complete library that stays current without registering each new document.

## User stories

- As a reader, I want useful starting guides and searchable documentation so that I can find answers without knowing repository paths.
- As a contributor or operator, I want historical plans, workflow instructions and supporting assets to be discoverable so that I can follow evidence and related material.

## Core requirements

- Retain curated starting guides alongside an automatically discovered library. Include all repository documentation Markdown under `docs/` and root `README.md`, `CONTRIBUTING.md`, `AGENTS.md` and `BLESSING.md`. Display title and path; distinguish current guidance, plans/history and vendored workflow material without treating every plan as obsolete.
- Search document titles, paths, headings and body text; support category/folder and current-versus-history filters, with current guidance ranked first. Include historical/workflow material in unfiltered search; provide result counts, clear filters and useful empty states without presenting the entire library at once.
- Discover supporting assets under `docs/`: plain-text examples/configuration, TXT, CSV, JSON, HTML and PDF, plus the vendored LICENSE. Offer inert text viewing or safe downloads; never execute examples or render HTML as active content. Exclude repository-control files such as `.gitignore`, private instance files and paths outside these boundaries. Files exceeding supported viewing limits remain discoverable with a download or explicit unavailable state.
- Preserve existing document URLs, safely resolve related local documentation links and anchors, and return readers to their search/filter position. Apply the same eligibility boundary to discovery, reading, downloads and publication; reject traversal and escaping symlinks.
- Keep dynamic and published static libraries usable, searchable and complete for the same source revision. New eligible files appear after normal publication without catalog edits; missing/stale content offers a visible route back to the library and a retry/refresh action.

## Delivery scope and completion boundary

Work type: **application change**. Extend the existing documentation experience, shared discovery and publication behavior, with focused verification and documentation; track UI copy cleanup in `ui_copy_audit_checklist.md`. Content rewrites, archive moves, a replacement documentation platform and a new navigation destination are outside scope.

Complete when a reader enters **Tools → Platform Docs**, finds a current guide or historical document, opens it and related material, and returns to results on dynamic and static delivery. Missing content must allow recovery to a working library. Release requires inventory parity, safe file access and representative browser journeys to pass.

## Risks

- **Exposure or execution:** broader formats could publish private data or active content. Validate eligible paths, example contents, traversal and download handling first; enforce repository documentation boundaries and inert delivery before expansion.
- **Scale and freshness:** full-text search and publication across the large plans tree could become slow or incomplete. Measure the full inventory and publication path early; use bounded results and a shared revision-consistent inventory before dependent UI work.
- **Misleading classification or broken links:** repeated plan titles and historical references can obscure useful results. Sample current guides, completed/active plans, root files and vendored links early; retain paths, explicit grouping and recoverable missing-target handling.

## Shared component inventory

Extend the canonical PlatformDocsCatalog inventory/link policy, PlatformDocsController index/reader routes, platform_docs_index and platform_docs_document templates, shared Markdown rendering, and StaticArtifactBuilder document enumeration/publication. Reuse the existing Tools launcher/subnavigation entry. Supporting asset delivery needs an extension because the current reader supports only Markdown; it must share the inventory boundary.

## Simple user flow

1. Open Platform Docs and choose a starting guide, browse a folder or search all documents.
2. Narrow results, open a document or supporting asset, and follow related links.
3. Return to the preserved results; clear filters or recover from missing content through the library.

## Success criteria

Every eligible file is represented exactly once in the library for a given revision, with dynamic/static inventory parity and automatic inclusion after publication. Representative feature, operations, history, root-document and asset searches reach the expected content. Mobile and keyboard journeys complete the flow; unsafe paths and active content are blocked, and missing-document recovery remains usable.
