# QDB Archive Importer — Step 4: Implementation Summary

## Stage 1 - Imported score/vote-count seed on the post record

- Changes:
  - `PostRecord`/`PostRecordParser`: two new optional headers,
    `Imported-Score-Seed` and `Imported-Vote-Count-Seed`. Must be present
    together or both absent; root-only (rejected on replies, like the
    existing task-root headers); score seed is a signed integer, vote
    count seed is non-negative.
  - `ReadModelBuilder`: `indexPosts()` now seeds a thread's initial
    `score_total`/`vote_count` from the root post's seed (defaulting to
    0 exactly as before when absent) instead of always starting at 0;
    `indexThreadLabels()` now reads that already-seeded `threads` row as
    its baseline before folding in ordinary thread-label reactions, both
    for threads with no reactions and for the final per-thread fallback.
  - `IncrementalReadModelUpdater`: added `loadRootImportedSeed()`, which
    reads the seed straight from the canonical post record (not from the
    database) since `deriveThreadLabelState()` recomputes from scratch
    on every call and a DB-read baseline would double-count on a second
    vote. Wired into both places that call `deriveThreadLabelState()` —
    the live single-vote path (`applyThreadLabelWrite`) and the
    approval-triggered rescoring path (`refreshApprovalSensitiveThreadScores`).
  - Post-ID convention correction (pulled forward from Stage 4, see
    Notes): decided on `thread-<YYYYMMDDHHMMSS>-qdb-<quote_id>` rather
    than a plain `thread-qdb-<quote_id>`, so imported posts keep the
    timestamp segment `CanonicalPathResolver::postCandidates()` expects
    when resolving a post by ID without the read model's index.
- Verification:
  - `php tests/run.php ImportedQuoteSeedScoringTest` (new, 4 cases):
    seed with no reactions reproduces exactly; seed plus one ordinary
    vote is seed-plus-delta; rebuilding the same repo twice does not
    double-count the seed; a post with no seed scores exactly as before
    (0, unaffected).
  - `php tests/run.php WriteApiSmokeTest::testApplyThreadTagOnImportedQuoteAddsVoteOnTopOfImportedSeed`
    (new): end-to-end through the real `LocalWriteService::applyThreadTag`
    → incremental-read-model-update path (not a full rebuild) on an
    imported post, confirming the seed survives a live vote cast through
    the actual write API.
  - Full suite: `php tests/run.php` — 629 run, 624 passed, the same 5
    pre-existing long-standing failures (unrelated to this change, all
    predate this branch), 0 new failures.
- Notes:
  - Mid-implementation finding, not in the original Step 3 text: tracing
    `applyThreadTag`'s `loadPost(CanonicalPathResolver::post($threadId))`
    call showed that a Post-ID without an embedded timestamp pattern
    would fail to resolve to its dated-shard file at all outside the
    read model's index — meaning nobody could vote on an imported quote
    under the originally-sketched `thread-qdb-<quote_id>` convention.
    Fixed by keeping the live ID's timestamp segment and only swapping
    its random suffix for a deterministic one. Stage 4 will use this
    corrected convention as given, not re-decide it.
  - Scope grew slightly past Step 3's Stage 1 text (which only named
    `ReadModelBuilder`) to also cover `IncrementalReadModelUpdater`,
    since without it the seed would survive the first full rebuild but
    silently reset on the next live vote or approval change — the same
    correctness property Step 3 asked for, just one more code path that
    turned out to implement it.
