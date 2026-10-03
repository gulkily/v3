# QDB Quote Numbering — Step 4: Implementation Summary

> **Feature plan:** [Step 1](./qdb_quote_numbering_step1_solution_assessment.md) · [Step 2](./qdb_quote_numbering_step2_feature_description.md) · [Step 3](./qdb_quote_numbering_step3_development_plan.md) · [Step 4](./qdb_quote_numbering_step4_implementation_summary.md)

## Stage 1 - Shared quote-numbering helper wired into both submission paths
- Changes:
  - Added `LocalWriteService::nextQdbQuoteNumber(): int` — runs the
    verified MAX-extraction query (`MAX(CAST(substr(root_post_id,
    instr(root_post_id,'-qdb-')+5) AS INTEGER))`) against the read-model
    `threads` table, returns `COALESCE(result, 0) + 1`.
  - Added `LocalWriteService::mintThreadPostId(): string` — returns
    `thread-<timestamp>-qdb-<N>` when `SiteProfileRegistry::active()
    ['name'] === 'qdb'`, otherwise falls back unchanged to the existing
    `generateRecordId('thread')`.
  - `createThread()` and `prepareThread()` both call `mintThreadPostId()`
    in place of their previous direct `generateRecordId('thread')` call.
  - Added `use ForumRewrite\SiteProfileRegistry;` import.
- Verification:
  - Ad hoc smoke script against a scratch environment (temp git repo
    cloned from `tests/fixtures/parity_minimal_v1` + temp SQLite read
    model, via `Application::handle()`), run under `php -l` clean syntax
    check first.
  - `FORUM_SITE_ID=qdb`: two sequential `POST /api/create_thread` calls
    minted `thread-<ts>-qdb-1` then `thread-<ts>-qdb-2`.
  - Same qdb environment: a following `POST /api/prepare_thread` minted
    `thread-<ts>-qdb-3` — confirms both entry points share one
    continuous sequence via the shared read model.
  - Fresh qdb environment with zero `-qdb-` rows correctly started
    numbering at `1` (`COALESCE` fallback), matching Step 3's planned
    edge case.
  - `FORUM_SITE_ID=zenmemes` (default/non-qdb): `POST /api/create_thread`
    minted the original unchanged `thread-<ts>-<random-hex>` form — no
    regression to the other site profiles.
- Notes:
  - All three Key Risks from Step 3 (suffix/regex drift, the two-entry-
    point gap, site-scope mistake) are addressed by construction: both
    entry points call one shared helper, which reuses the exact
    already-verified query and the existing `SiteProfileRegistry`
    site-identity check.
  - No change to `quote_card.php` was needed — its existing
    `-qdb-(\d+)$` display regex already picks up the new IDs unchanged.
