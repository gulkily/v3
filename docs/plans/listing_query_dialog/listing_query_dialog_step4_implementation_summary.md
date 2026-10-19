> **Feature plan:** [Step 1](./listing_query_dialog_step1_solution_assessment.md) · [Step 2](./listing_query_dialog_step2_feature_description.md) · [Step 3](./listing_query_dialog_step3_development_plan.md) · [Step 4](./listing_query_dialog_step4_implementation_summary.md)

Debug mode: enabled

## Scope and coverage checklist
- Planning commit: `201343c9`; branch `feature/listing-query-dialog`.
- Codebase: admin SQL, all metadata SQL sources, and all read-model row-count SQL. File/shell sources remain explanatory text.
- Standard boards: All/Liked × Newest/Oldest/Top, sort fallback and route aliases; empty results, pinned rows, tie-breaking, root-score filtering, hidden/retracted/identity content. No standard-board pagination currently exists.
- Follow-ups: QDB/Forte variants, activity/tags/details, directory/profile lists, offline reader and unavailable-source explanations, as recorded in the approved delivery split.
- External deployment is outside scope. No schema/public export changes. Unrelated effort-estimation planning files are excluded from commits.

## Stage 1 - Codebase dialog slice
- Changes: shared native dialog reuses the canonical SQLite viewer; codebase SQL opens editable automatically executed queries; warm close/reopen preserves the mounted viewer. File/shell displays remain intact. Standalone viewer retained.
- Verification: `node tests/browser/listing_query_dialog.mjs` passed load retry, auto-run, editing, reopen, background and standalone checks with real SQL.js/WASM; `php tests/run.php SqliteQueryCatalogTest` passed 4/4. PHP syntax, JS syntax, and whitespace checks passed.
- Notes: an initial browser check exposed a load-failure button label stuck at Loading; corrected before stage completion. Persistence and per-context recovery belong to Stages 2–3.
