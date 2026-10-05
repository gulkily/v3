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

## Stage 2 - Permalink root card matches the listing card on qdb
- Changes:
  - `templates/partials/thread_root_card.php`: on the qdb profile, the root
    card shows the listing's `#N` header and `(score/votes)` readout, the
    full quote body in quote-card styling, and upvote, downvote, and flag
    controls with `quote_card.php`'s markup and data attributes. Like is no
    longer rendered there. Non-qdb profiles keep the original `<h1>`, body,
    and Like/Flag buttons. Reply, agent, Codex, and analysis features are
    untouched.
  - `Application::fetchThread()`: added `threads.vote_count` to the SELECT.
    It was missing, so every permalink page read a denominator of 0 — the
    `(5/0)` symptom in `qdb_todo.txt` item 1 has this cause as well as the
    Like-counting one fixed in Stage 1. Affects all profiles' thread pages
    only by making the value available; no display change outside the qdb
    root card.
- Verification:
  - `php -l` on the template and `Application.php` — clean.
  - `QuoteCardDisplayNumberTest::testQdbPermalinkRootCardMatchesListingCardWithoutLike`
    — on qdb, `/threads/<id>` shows `href="/42">#42`, the
    `(5/7)` readout from the seeded imported score and vote counts, the
    full body `The quoted body.`, and upvote/downvote/flag, with no
    `data-tag="like"`.
  - `QuoteCardDisplayNumberTest::testNonQdbPermalinkRootCardKeepsLikeAndHasNoQuoteHeader`
    — default profile keeps the Like control and shows no quote header.
  - Live check during investigation: a scratch server rendered the same
    imported quote; the DB row held `vote_count = 7` while the page showed
    `(5/0)` before the `fetchThread()` fix, and the page is now covered by
    the test above.
  - Full suite `./v3 test`: 692 run, 686 passed, 6 failed — the pre-existing
    set (the order-dependent approval test failed in this run and passed
    in an earlier one).
- Notes:
  - Non-qdb byte-identical output was not captured as a before/after diff;
    the non-qdb guarantee is covered structurally (Like present, no quote
    header) rather than by a byte comparison.
  - Found and fixed during this stage: the quote body disappeared on the
    qdb root card because the body helper strips a first line that matches
    the title; the qdb branch now uses the full body, since it has no `<h1>`.

## Stage 3 - Viewer's existing upvote/downvote shown as pressed on the permalink
- Changes:
  - `ThreadAndPostPageController::thread()`: on the qdb profile, for a
    signed-in viewer, looks up whether they have upvoted or downvoted the
    root thread using the same `ViewerTagLookup::threadTags()` lookup the
    listing uses, and passes `viewerHasUpvoted` / `viewerHasDownvoted` to
    the page. The lookup runs only on qdb and only for a known viewer, so
    other profiles pay no extra scan.
  - The existing root-card template (Stage 2) already reads these values, so
    no template change was needed here; partials inherit the page's variables.
- Verification:
  - `php -l` — clean.
  - `WriteApiSmokeTest::testQdbPermalinkShowsViewersExistingUpvoteAsPressedAndDisabled`
    — a viewer who upvoted a qdb quote through the API sees its upvote
    control `aria-pressed="true"` and `disabled` on the permalink; an
    anonymous visitor sees `aria-pressed="false"` and enabled.
  - Full suite `./v3 test`: 693 run, 686 passed, 7 failed. The six
    long-standing failures are unchanged. The seventh is
    `testIncrementalApprovalMatchesFreshRebuildForTransitiveApprovalAndScoreRefresh`.
    It fails in isolation, and I confirmed it also fails at `3bc6044`, before
    any parity work, using a temporary worktree (since removed). It is a
    pre-existing failure, not introduced here; it passed in an earlier run.
- Notes:
  - Flagged for follow-up: that pre-existing failure is now failing
    consistently in isolation, unlike earlier runs, so it deserves its own
    investigation outside this feature.
