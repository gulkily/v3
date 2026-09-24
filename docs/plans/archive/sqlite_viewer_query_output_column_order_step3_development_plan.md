# SQLite Viewer Query Output Column Order Step 3 Development Plan

- Scope boundary: reorder columns in maintained SQLite viewer example queries and regenerate their browser and downloadable catalog outputs.
- Out of scope: changing query fields, joins, filters, grouping, ordering, limits, result rendering, ad hoc SQL behavior, database schema, or viewer layout/CSS.

## Stage 1
- Goal: Establish and record the presentation-order review for every existing preset.
- Dependencies: Approved Steps 1–2; current `queries/sqlite/*.sql` catalog.
- Expected changes: Inspect each preset’s selected fields and classify them as primary readable content, context/date/metrics, identifiers, opaque data, or internal metadata; identify any query-specific exceptions.
- Verification approach: Confirm every selected field has an intentional position and no field is slated for removal.
- Risks or open questions:
  - Some activity and board fields serve both user-facing and diagnostic purposes; retain them while placing them after readable context.
- Canonical components/API contracts touched: Existing query-file metadata and result column names.

## Stage 2
- Goal: Reorder the simple content, people, and statistics preset queries.
- Dependencies: Stage 1 ordering review.
- Expected changes: Update `recent-posts`, `threads-by-reply-count`, `approved-profiles`, `recent-activity`, `activity-counts-by-kind`, and `content-totals` `SELECT` lists without changing expressions, aliases, predicates, grouping, ordering, or limits.
- Verification approach: Compare before/after selected-column sets and execute each query against the current read model.
- Risks or open questions:
  - Compound `UNION ALL` output must retain compatible column positions while presenting the useful summary fields first.
- Canonical components/API contracts touched: `queries/sqlite/*.sql`; SQLite result column names and aliases.

## Stage 3
- Goal: Reorder board and activity-detail preset query outputs.
- Dependencies: Stage 1 ordering review.
- Expected changes: Update the three board presets and the `activity-all`/`activity-content` presets so subjects, labels, authors, timestamps, and useful counts precede IDs, JSON, source, commit, and approval metadata.
- Verification approach: Compare selected-column sets and unchanged query clauses; execute each preset and inspect the first visible columns in the result headers.
- Risks or open questions:
  - Joined fields and aliases must remain unambiguous and preserve the existing browser catalog contract.
- Canonical components/API contracts touched: Board/activity query sources; generated preset IDs, labels, descriptions, and SQL payloads.

## Stage 4
- Goal: Regenerate the browser catalog and downloadable query pack from canonical sources.
- Dependencies: Stages 2–3; existing catalog generator.
- Expected changes: Run `scripts/build_sqlite_query_catalog.php` and refresh only the generated query SQL/catalog payloads required by the source changes.
- Verification approach: Confirm generated browser entries and `public/assets/sqlite_query_catalog.sql` match source order and remain deterministic.
- Risks or open questions:
  - Generated artifacts can drift if regeneration is skipped or an unrelated generated change is introduced.
- Canonical components/API contracts touched: `scripts/build_sqlite_query_catalog.php`; `public/assets/sqlite_viewer.js`; `public/assets/sqlite_query_catalog.sql`.

## Stage 5
- Goal: Complete focused regression and manual viewer verification.
- Dependencies: Stage 4.
- Expected changes: Add no new behavior; verify the existing query-catalog and SQLite viewer checks cover the reordered outputs, adding only focused assertions if a gap is found.
- Verification approach: Run catalog tests and relevant PHP smoke tests, validate generated/source synchronization, execute all presets, and manually confirm readable columns appear first in the browser and local query pack.
- Risks or open questions:
  - Existing unrelated worktree changes or baseline failures must remain separate from this feature’s verification results.
- Canonical components/API contracts touched: SQLite query catalog tests; viewer smoke coverage; browser query selector and existing read-only execution path.

