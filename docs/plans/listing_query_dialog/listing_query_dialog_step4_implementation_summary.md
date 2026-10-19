> **Feature plan:** [Step 1](./listing_query_dialog_step1_solution_assessment.md) · [Step 2](./listing_query_dialog_step2_feature_description.md) · [Step 3](./listing_query_dialog_step3_development_plan.md) · [Step 4](./listing_query_dialog_step4_implementation_summary.md)

Debug mode: enabled

## Follow-up UI refinement — header and board action
- Request: fix the oversized dialog close button and squeezed title; move the page-level query action to the bottom.
- Disposition: small direct UI correction under AGENTS.md; keep codebase's individual SQL actions attached to their source values and move the standard board action below the listing.
- Approval status: authorized by the user's direct request; no scope expansion.
- Outcome/verification: fixed compact close-button sizing/title flex and moved the board query action into a footer after the listing. Browser replay passed at 390px/1280px with the real shared stylesheet and explicit header dimensions; application route/footer coverage and core-route smoke checks passed. PHP syntax and whitespace checks passed. Earlier browser fixture omitted the shared site stylesheet, so it did not expose the inherited full-width button rule.

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

## Stage 2 - Saved database reuse
- Changes: reuse a validated saved published database; share pending database/runtime loads; display revision and stale-data caveat; retain in-memory operation when browser persistence is unavailable. Existing table-change handlers no longer accumulate on reload.
- Verification: browser replay passed saved-data reuse across navigation with the download endpoint failing, no warm-reopen downloads, corrupt saved-data rejection, and Stage 1/standalone regression. Query catalog checks passed 4/4; JS syntax passed.
- Notes: Stage 1 commit `eca4f4a4`. Explicit incompatible-data refresh recovery remains Stage 6; current validation rejects damaged or missing core tables without substituting another source.

## Stage 3 - Recoverable editing sessions
- Changes: separate source/context-keyed drafts, saved on edits and page exit; recover drafts and query paging/sorting after navigation, with in-memory fallback on storage denial. Ignore outdated context load completions and keep same-context results mounted on reopening.
- Verification: browser replay passed two independent drafts, switch-back recovery, reload recovery with automatic results, and denied-session-storage in-memory recovery, plus prior checks. JS syntax and whitespace checks passed.
- Notes: Stage 2 commit `fdf53d59`. Drafts persist only within the same tab; a denied persistence API cannot retain drafts across a document reload, but does not block local editing.
- Verification correction: `eaffc0f5` was committed before inspecting the final added storage-denial check, so its summary prematurely stated that check passed. The check read the previous context before the deliberately deferred open completed; corrected it to await context restoration. Full browser replay now passed before starting Stage 4. Whitespace checks are scoped to feature files because unrelated concurrent todo edits contain trailing whitespace.

## Stage 4 - Standard All boards
- Changes: standard All boards now expose contextual queries using the repository's shared base row selection, identity exclusion, pinned priority and full newest/oldest/top ordering. Queries reproduce the full listing without the sample catalog's 100-row cap. QDB/Forte remain excluded.
- Verification: fixed-data PHP parity passed across all sorts, fallback sorts, over 100 rows, empty lists, ties, malformed/scalar JSON, identity rows, hidden/retracted rows; query catalog 4/4 and browser regression passed. Changed PHP files passed syntax checks.
- Notes: Stage 3 verification correction commit `3bf80f73`. Existing sample queries are unchanged because contextual queries need complete listing scope and canonical fields.
- Verification correction: the browser rerun returned another premature editor assertion at switch-back; `1dc2f34a` was recorded before that output was inspected. All switch-back assertions now await deferred context restoration, and the complete browser replay passed. Stage 5 starts only after this corrective verification commit.

## Stage 5 - Standard Liked boards
- Changes: contextual queries now cover All/Liked and newest/oldest/top, matching root-post score eligibility and JSON label membership; normalized view/sort context keys keep drafts separate.
- Verification: PHP fixed-data matrix matched controller selection/order for 15 normalized/fallback combinations, including negative root scores and empty Liked results. Board/catalog/presentation-profile tests passed 8/8; full dialog browser replay passed.
- Notes: Stage 4 correction commit `f4618c98`. Route aliases share BoardPageController; full HTTP action wiring is checked in Stage 8.

## Stage 6 - Refresh and failure recovery
- Changes: explicit refresh labels, independent persistent provenance/freshness details, actionable damaged/incompatible saved-data recovery, and replacement only after validation. Failed refresh leaves the prior database and edits usable.
- Verification: browser replay passed corrupt-cache → failed refresh → successful refresh, SQL preservation, query syntax-error correction, and successful local rerun after a later failed refresh. Board/catalog tests passed 6/6; JS/PHP syntax passed.
- Notes: Stage 5 commit `59500bd5`. Refresh fetches only the published read model; offline snapshot lifecycle is untouched. Unsupported/absent metadata values are honestly shown as revision unavailable.

## Stage 7 - Accessible return to page
- Changes: capture background selection before pointer default behavior, lock/restore background scrolling, retain dialog scroll before native close/cancel, and restore trigger focus. Cancel outdated deferred opens; keep Close visible; use theme background and constrain narrow-screen query controls/text.
- Verification: browser replay passed 1280px/390px keyboard traversal, Escape and Close, focus return, selection/form/background-scroll preservation, dialog scroll on reopen, and no dialog horizontal overflow, plus all prior scenarios.
- Notes: Stage 6 commit `01b16ff4`. Tests exposed native selection clearing, scroll reset, and pagination overflow; corrected these before stage completion. Browser assertions wait for the native close event to finish restoration.

## Stage 8 - Release acceptance
- Changes: application-route coverage tests, six-context SQL.js/PDO parity and result-page checks, delayed-load/edit race coverage, denied-cache/session-storage recovery, read-only enforcement, and timing evidence. Final corrections validate metadata column compatibility before replacing a database and avoid falsely labeling contextual SQL as a selected preset.
- Verification: **passed** `php tests/run.php LocalAppSmokeTest::testApplicationRendersCoreRoutes BoardListingQueryTest PresentationProfileMatrixTest SqliteQueryCatalogTest PublicOfflineSnapshotBuilderTest OfflineSnapshotPresentationTest TemplateRendererMediaEmbedsScriptTest` (20/20). **Passed** final `node tests/browser/listing_query_dialog.mjs` with actual SQL.js/WASM and the 130-row fixture. **Passed** final query-catalog rerun (4/4), changed PHP/JS syntax, and feature-scoped whitespace checks.
- Notes: Stage 7 commit `7efb4fe1`. HTTP coverage includes `/`, `/threads`, `/threads/` with all six view/sort combinations, all codebase SQL displays, and absence of accidental Forte wiring. SQL.js first/second pages match PHP; original PHP matrix matches controller selection/order. Local final timings: cold shell 2.4 ms, cold results 86 ms, warm shell 8.8 ms; other runs varied. Warm reopening made no download and no additional runtime initialization. These are headless Chromium fixture measurements, not production guarantees.

## Final status and limits
- **Passed:** approved first-release normal entry, automatic local execution, editable rerun, saved-source provenance/reuse/refresh, independent drafts and navigation recovery, close/reopen retention, background/focus recovery, error handling, keyboard and 390px/1280px layout checks. Read-only rejection leaves the database intact.
- **Passed:** all eight stages completed with stage-scoped commits and summaries; initial planning commit preceded implementation. Stage 3/4 premature verification reports were corrected in explicit follow-up commits before subsequent stages.
- **Inapplicable:** production deployment/merge, migrations, new exports, and private-database integration are outside the approved scope. No production changes performed.
- **Not run:** complete repository test suite and browsers other than Chromium; focused checks are not a full-suite or cross-browser claim.
- **Deferred by approved split:** QDB/Forte, activity/tags/details, directory/profile lists, offline reader integration and unavailable-source explanations. The broader every-listing request remains follow-up work; no claim of full-site coverage.
- Unrelated todo and effort-estimation changes remain outside this feature's commits.
