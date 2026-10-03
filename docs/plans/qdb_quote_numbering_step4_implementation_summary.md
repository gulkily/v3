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

## Stage 2 - Automated regression coverage
- Changes: added four tests to `tests/WriteApiSmokeTest.php`:
  - `testQdbSiteAssignsSequentialQuoteNumbersAcrossDigitBoundary` —
    10 sequential qdb submissions get `-qdb-1` through `-qdb-10`,
    crossing the single-to-double-digit boundary the numeric `CAST`
    guards against (vs. a naive string-sorted `MAX`).
  - `testQdbSiteStartsQuoteNumberingAtOneWithNoExistingQuotes` — a fresh
    qdb instance's first submission mints `-qdb-1` (`COALESCE` fallback
    on an empty `threads` table).
  - `testQdbPrepareThreadContinuesSameQuoteNumberSequenceAsCreateThread`
    — a `createThread()` call (`-qdb-1`) followed by a `prepareThread()`
    call (`-qdb-2`) on the same instance share one sequence, directly
    covering the two-entry-point risk from Step 3.
  - `testNonQdbSiteProfileThreadIdsAreUnaffectedByQuoteNumbering` — a
    default-profile submission still mints the original
    `thread-<timestamp>-<random-hex>` form, with no `-qdb-` suffix.
- Verification:
  - `php -l tests/WriteApiSmokeTest.php` — no syntax errors.
  - `./v3 test` targeted at the four new tests — 4 run, 4 passed.
  - Full suite `./v3 test` — 680 run, 673 passed, 7 failed; the 7
    failures are the same set already tracked before this change (6
    long-standing, plus
    `testIncrementalApprovalMatchesFreshRebuildForTransitiveApprovalAndScoreRefresh`,
    which the harness flagged as "new" only because of run-order
    sensitivity — re-ran it alone and it passed, matching the identical
    pre-existing order-dependent flakiness already documented in
    `qdb_quotes_instance_step4_implementation_summary.md`). No failures
    introduced by this feature.
- Notes: none.
