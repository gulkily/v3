# QDB Numeric Permalinks — Step 4: Implementation Summary

> **Feature plan:** [Step 1](./qdb_numeric_permalinks_step1_solution_assessment.md) · [Step 2](./qdb_numeric_permalinks_step2_feature_description.md) · [Step 3](./qdb_numeric_permalinks_step3_development_plan.md) · [Step 4](./qdb_numeric_permalinks_step4_implementation_summary.md)

## Stage 1 - Server-side numeric resolution
- Changes:
  - Added `ThreadRepository::byQdbQuoteNumber(PDO $pdo, int $number):
    ?string` — resolves a quote number to its full `root_post_id`, using
    the same `CAST(substr(root_post_id, instr(root_post_id,
    '-qdb-')+5) AS INTEGER)` extraction already verified in the
    quote-numbering feature, as an equality check instead of `MAX`.
  - Extended `Application::handle()`'s existing last-resort qdb bare-path
    block: when the direct `ThreadRepository::byId()` lookup misses and
    the path segment is all digits (`ctype_digit`), falls back to
    `byQdbQuoteNumber()`; redirects to `/threads/<resolved-id>` on a hit,
    exactly as the existing exact-ID case already did. No change to
    behavior when the path isn't all digits or isn't the qdb profile.
- Verification:
  - `php -l` on both changed files — no syntax errors.
  - Added three tests to `tests/QuoteCardDisplayNumberTest.php`:
    - `testBareNumericPathRedirectsToTheQuoteWithThatNumber` — a seeded
      quote numbered 42: `GET /42` returns `302` with the redirect body
      linking to `/threads/thread-...-qdb-42`.
    - `testBareNumericPathWithNoMatchingQuoteStill404s` — `GET /999999`
      still `404`s.
    - `testBareNumericPathOnNonQdbProfileDoesNotRedirect` — same `/42`
      request on the default (zenmemes) profile still `404`s, confirming
      the qdb-only gate is unchanged.
  - `./v3 test QuoteCardDisplayNumberTest` — 5 run, 5 passed (existing 2
    plus the 3 new).
  - Full suite `./v3 test` — 683 run, 676 passed, 7 failed; the 7
    failures are the same pre-existing set already tracked before this
    change (including the same order-dependent
    `testIncrementalApprovalMatchesFreshRebuildForTransitiveApprovalAndScoreRefresh`
    flake noted in the prior feature's Step 4 summary). No failures
    introduced by this change.
- Notes: the Key Risks "numeric fallback shadowing a real route" item is
  addressed by construction — the new check lives strictly inside the
  already-last-resort block, after the existing exact-ID check, not as a
  new earlier route.
