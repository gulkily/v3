# QDB Permalink Quote-Card Parity — Step 4: Implementation Summary

> **Feature plan:** [Step 1](./qdb_permalink_quote_card_parity_step1_solution_assessment.md) · [Step 2](./qdb_permalink_quote_card_parity_step2_feature_description.md) · [Step 3](./qdb_permalink_quote_card_parity_step3_development_plan.md) · [Step 4](./qdb_permalink_quote_card_parity_step4_implementation_summary.md)

## Stage 1 - Legacy Likes count toward vote totals on qdb
- Changes:
  - `TagScore::countsTowardVoteTotal(string $tag): bool` — true for upvote
    and downvote on every profile; true for `like` only when the active
    site profile is qdb.
  - `ReadModelBuilder` (full rebuild) and `IncrementalReadModelUpdater`
    (incremental update) now both use this predicate where they count
    vote tags, so the two paths cannot diverge.
- Verification:
  - `php -l` on all changed files — clean.
  - `TagScoreTest::testLikeCountsTowardVoteTotalOnlyOnQdb` — `like` counts
    only under the qdb profile; `upvote` counts everywhere; `flag` never.
  - `WriteApiSmokeTest::testQdbLegacyLikeCountsTowardVoteTotalOnIncrementalAndRebuildPaths`
    — under qdb, a Like on `root-001` gives `vote_count = 1` from the
    incremental path, and the same value after a full rebuild.
  - `WriteApiSmokeTest::testNonQdbLikeDoesNotCountTowardVoteTotal` — under
    the default profile a Like leaves `vote_count = 0`, unchanged behavior.
  - Full suite `./v3 test`: 690 run, 684 passed, 6 failed. The six are the
    pre-existing set tracked in earlier stages; no new failures.
- Notes:
  - Deployment still requires a read-model rebuild so existing qdb quotes
    pick up historical Likes; that is recorded in the Completion Contract
    and will be verified when Stage 4 closes out.
  - The High-risk parity item (incremental vs rebuild) is covered by the
    second test above.
