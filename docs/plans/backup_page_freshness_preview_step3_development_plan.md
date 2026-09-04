# Backup Page Freshness Preview Step 3 Development Plan

## Stage 1
- Goal: Define a single backup snapshot summary for freshness metadata and recent included content.
- Dependencies: Approved Step 2; existing repository, read-model, and activity data; no schema migration.
- Expected changes: Add a conceptual `backupSnapshotSummary(): array` contract returning generated-at/identity metadata, a bounded recent-items list, and empty-state information; reuse the existing content activity representation and establish timestamp semantics that honestly describe the downloadable snapshot.
- Verification approach: Focused tests cover populated, empty, and limited preview data plus consistency of the summary fields.
- Risks or open questions:
  - Repository archives are currently generated on request, so the displayed timestamp must not claim a prebuilt artifact time unless that identity is available.
- Canonical components/API contracts touched: `Application::renderBackup()`, `Application::fetchActivity(string $view)`, existing repository/archive and read-model download routes.

## Stage 2
- Goal: Present the snapshot summary on the canonical Backup page.
- Dependencies: Stage 1 summary contract.
- Expected changes: Extend the `instance.php` page context and markup to show generated-at metadata, a bounded recent-items preview, clear empty/limited-result messaging, and existing download links in the same page flow.
- Verification approach: Render assertions confirm metadata, preview items, empty state, links, and safe escaping; existing Backup page smoke assertions remain green.
- Risks or open questions:
  - Preview labels and links must remain understandable without suggesting that the preview is a complete archive listing.
- Canonical components/API contracts touched: `Application::renderBackup(): string`; `templates/pages/instance.php`; existing `/downloads/repository.tar.gz`, `/downloads/repository.zip`, and `/downloads/read_model.sqlite3` contracts.

## Stage 3
- Goal: Keep the freshness preview correct for static delivery and both Backup aliases.
- Dependencies: Stages 1–2; existing static artifact builder and invalidator.
- Expected changes: Include the Backup page under the `/backup/` and `/tools/backup/` route/artifact mappings as needed, and ensure writes or rebuilds invalidate the page whenever its preview or freshness source changes.
- Verification approach: Static artifact tests confirm both aliases expose the same updated summary after a source/read-model refresh and do not serve a stale page after invalidation.
- Risks or open questions:
  - Static page freshness may require aligning Backup invalidation with the existing activity/read-model invalidation boundary.
- Canonical components/API contracts touched: `StaticArtifactBuilder::build()`, `StaticArtifactBuilder::buildSingleRoute(string $route): bool`, `StaticArtifactInvalidator`, `FrontController` Backup alias resolution.

## Stage 4
- Goal: Verify the complete user-visible freshness and preview behavior without changing download semantics.
- Dependencies: Stages 1–3.
- Expected changes: Add or update focused smoke coverage for dynamic and static Backup routes, preview ordering, timestamp/preview consistency, empty state, aliases, and all existing download links.
- Verification approach: Run the focused local smoke/test suite and manually inspect the Backup page before download; confirm no unrelated route or download regressions.
- Risks or open questions:
  - If verification exposes that the current on-demand archive behavior cannot support a trustworthy shared timestamp, return to Stage 1 semantics rather than expanding scope into on-demand generation.
- Canonical components/API contracts touched: Backup page aliases, canonical `instance.php` rendering, static artifact delivery, and existing download endpoints.
