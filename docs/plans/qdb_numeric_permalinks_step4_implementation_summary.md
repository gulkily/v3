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

## Stage 2 - Short link in quote_card.php
- Changes:
  - `templates/partials/quote_card.php`: anchor `href` is now
    `/<displayNumber>` when a `-qdb-<N>` suffix was actually matched,
    otherwise unchanged `/threads/<full-id>` for any quote predating the
    numbering convention. Also corrected an adjacent comment that was
    stale since the quote-numbering feature shipped (it said
    live-authored quotes never carry a `-qdb-` suffix; they do now).
- Verification:
  - `php -l` — no syntax errors.
  - Added four tests to `tests/QuoteCardDisplayNumberTest.php`:
    - `testQuoteCardLinksToTheShortNumericPermalinkOnLatest` — a numbered
      quote's card on `/latest` links `href="/42"`, not the full ID.
    - `testQuoteCardLinksToTheShortNumericPermalinkOnTopSearchAndRandom`
      — the same holds on `/top`, `/search`, and `/random` (all three
      reuse `quote_card.php`, confirming the shared-partial change is
      consistent across every consumer, not just `/latest`).
    - `testQuoteWithoutAQdbNumberKeepsItsFullIdPermalink` — the base
      fixture's un-numbered `root-001` thread keeps its old
      `/threads/root-001` link.
    - `testFollowingTheShortNumericPermalinkReachesTheUnchangedQuotePage`
      — `/42` 302s to `/threads/thread-...-qdb-42`, and that page's
      rendered content is byte-identical to requesting it directly.
  - `./v3 test QuoteCardDisplayNumberTest` — 9 run, 9 passed.
  - Full suite `./v3 test` — same 7 pre-existing failures as Stage 1's
    baseline, no new ones.
  - Live click-through: started a real local server (`php -S` via
    `public/router.php`) against a disposable scratch qdb repository,
    submitted a real quote (minted `#1`), confirmed `GET /latest` shows
    `href="/1"` for it and `href="/threads/root-001"` unchanged for the
    un-numbered fixture thread, confirmed `GET /1` returns `302` and
    `GET /999999` returns `404`. Server stopped afterward; scratch
    repo/db disposable (job tmp dir), nothing persisted.
- Notes: both remaining Key Risks (shared-partial affecting every
  consumer; un-numbered quotes needing their old link preserved) are
  covered directly by this stage's test list, not left as assumptions.
  Feature complete — Completion Contract satisfied end to end, no
  follow-up cycle required.
