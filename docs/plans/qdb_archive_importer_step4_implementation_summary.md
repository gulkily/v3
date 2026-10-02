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

## Stage 2 - Extract qualifying quote rows from the dump

- Changes:
  - Loaded `~/qdb_database/backup.sql` into a scratch MariaDB database
    (`qdb_import_check`); created a scoped local-only `qdb_import`
    MySQL user with `SELECT` on just that database (root's own
    authentication wasn't usable from a non-sudo shell, and widening
    that was unnecessary for a read-only scratch task).
  - `scripts/qdb_archive_import_extract.php`: connects with
    `charset=latin1` (matching the column's real charset, so MySQL
    performs no conversion), runs the `approved=1 AND spam=0 AND
    deleted_flag=0` query, and writes one JSON-Lines record per
    qualifying quote to `state/qdb_archive_import/extracted_quotes.jsonl`
    (already covered by the repo's existing `state/` gitignore rule) —
    `quote_id`, `created_at`, `score`, `vote_count`, and the quote body
    base64-encoded (keeps the output valid JSON regardless of the
    original bytes; Stage 3 owns the latin1-to-UTF-8 decision, not this
    step).
- Verification:
  - Extracted row count (14,881) matches `SELECT COUNT(*)` with the
    same `WHERE` clause exactly.
  - Byte-exact round-trip check on quote_id 24 (has embedded quotes,
    commas, and newlines): `HEX(quote)` from a direct query against the
    live table matches `bin2hex(base64_decode(...))` from the extracted
    row exactly, and `score`/`vote_count` match too.
- Notes:
  - Real-data finding, not anticipated in Step 3: ~45% of qualifying
    quotes (6,683 of 14,881) have a NULL `add_timestamp` — every
    quote_id <= 20162, none above it, a clean historical cutoff rather
    than scattered bad data. No other column recovers the true date
    (`vote_timestamp` is populated but is a 2018/2019 rescoring date,
    not the original post date). Flagged to the user; decision was an
    honest placeholder rather than excluding these quotes or using the
    misleading rescoring date: a fixed date safely before the earliest
    real `add_timestamp` (2003-01-01T00:00:00Z), offset by quote_id
    seconds so the known relative submission order is preserved instead
    of 6,683 rows colliding on one identical instant. Each extracted
    row carries `date_is_placeholder` so later stages (and any future
    display decision) can tell a real date from this marker.

## Stage 3 - Transcode quote bodies to UTF-8

- Changes:
  - `scripts/qdb_archive_import_normalize.php`: for each extracted row,
    detects whether the raw bytes are already valid multi-byte UTF-8
    (28 rows) versus needing transcoding (14,853 rows), and converts
    the latter from Windows-1252 rather than strict Latin-1 - the
    source is 2003-era IRC chat, and Windows-1252's byte range for
    smart quotes/em-dashes/guillemets is exactly what strict Latin-1
    would otherwise turn into control characters. Writes
    `state/qdb_archive_import/normalized_quotes.jsonl` with a `body`
    field per row; only flags a row if the result fails to be valid
    UTF-8 (0 of 14,881 did).
  - Scope changed from Step 3's plan text after a direct instruction:
    originally this stage ran every body through
    `UnicodeTextPolicy::normalizeBody()` and flagged whatever it
    rejected (156 of 14,881 - mostly non-breaking spaces/tabs/soft
    hyphens, plus real symbol characters like `£`/`°`/`€` as genuine
    quote content, plus a handful of stray control bytes including one
    quote that's deliberately garbled IRC-prank text). The user then
    enabled Unicode authoring for the qdb instance and said to retain
    original characters - including stray control bytes - unless
    something is a genuine functional problem, since the policy that
    produced those 156 flags no longer applies to this instance at all.
    This step now only transcodes; it does not filter content.
- Verification:
  - All 14,881 rows produced valid UTF-8; 0 flagged.
  - Spot-checked a mixed sample of `already-utf8` and `windows-1252`
    rows containing visible non-ASCII characters (smart quotes,
    guillemets) by eye - text reads correctly, no mojibake.
  - Re-checked the three rows that were flagged under the old
    (now-abandoned) policy-based approach - the NBSP-formatted quote,
    the tab-containing quote, and the deliberately-garbled IRC-prank
    quote - all three now pass through with their original bytes
    intact rather than being altered or excluded.
  - No NUL bytes found in any of the 14,881 extracted quote bodies
    (checked before deciding there was no other functional-risk
    character worth special-casing).
  - Full suite: `php tests/run.php` - same 5 pre-existing long-standing
    failures; one additional failure
    (`WriteApiSmokeTest::testIncrementalApprovalMatchesFreshRebuildForTransitiveApprovalAndScoreRefresh`)
    confirmed flaky by rerunning 3x (passed 2/3) and unrelated to this
    stage (no `src/` changes in Stage 3 at all).

## Stage 4 - Write canonical post records with number, date, and score seed

- Changes:
  - `scripts/qdb_archive_import_write_posts.php`: for each row in
    Stage 3's output, builds `thread-<YYYYMMDDHHMMSS>-qdb-<quote_id>`
    (the Stage 1 Post-ID convention) from `created_at`, normalizes line
    endings and outer whitespace (matching the structural - not
    character-filtering - part of what live-authored bodies already
    go through), and writes one canonical post record straight into
    `records/posts/` at its real dated-shard path (no
    `LocalWriteService` call). Parses every record with
    `PostRecordParser` before writing, so nothing unparseable ever
    reaches disk.
  - Found and handled a second, separate restriction while running
    this for real: `GenericTextRecordParser` (the record format's own
    low-level parser, not the optional `UnicodeTextPolicy`) rejects
    true Unicode control/format characters (`\p{C}`) unconditionally,
    on every instance, regardless of feature flags - `\n` and `\t` are
    the only exceptions. This is a hard requirement of the canonical
    file format, which the earlier "retain everything" decision wasn't
    about (that was specifically about the optional, now-disabled
    authoring policy). After excluding CRLF line endings (already
    normalized), only 17 of 14,881 rows (0.1%) actually contain one of
    these - mostly a soft hyphen (54 occurrences in one row) and a
    handful of genuine stray control bytes. The script strips only
    `\p{C}` characters, nothing else, and logs exactly which quote_ids
    and codepoints were affected; since every one of them is
    non-printable, nothing a reader would see changes.
- Verification:
  - Ran for real against the full Stage 3 output (14,881 rows) into a
    scratch copy of the actual `state/local_repository_qdb` repository
    (not the live one) - all 14,881 rows wrote successfully, file count
    matches Stage 3's row count exactly, no Post-ID collisions.
  - Re-parsed all 14,881 written files straight from disk with
    `PostRecordParser` - 0 failures, every seed header round-trips.
  - Ran a full `ReadModelBuilder::rebuild()` against that scratch
    repository, then diffed `score_total`/`vote_count` for all 14,881
    imported threads against the Stage 3 source values - 0 mismatches,
    0 missing.
  - Full suite: `php tests/run.php` - back to exactly the same 5
    pre-existing failures (the one flaky test from Stage 3 passed
    again), confirming it really was flaky and not a regression.

## Stage 5 - Show the imported quote number on the board

- Changes:
  - `templates/partials/quote_card.php`: extracts the trailing
    `-qdb-<quote_id>` suffix from the Post-ID for display only (the
    `#...` permalink text), leaving every other use of the real
    Post-ID untouched (href, `data-thread-id`, `data-post-id`).
    Live-authored posts have no such suffix and display their Post-ID
    exactly as before.
- Verification:
  - New `tests/QuoteCardDisplayNumberTest.php` (2 cases): a qdb-site
    render of a quote with Post-ID `thread-20030613104735-qdb-42`
    shows `#42`, not the internal ID; a non-qdb (zenmemes) render of
    the same post renders `thread_card.php` instead of `quote_card.php`
    at all, confirming the change is scoped to the qdb card and
    doesn't touch the shared board template other instances use.
  - Found along the way: plain `/` on the qdb instance renders the
    welcome page, not the quote list - `/latest` (or `/top`,
    `/leetness`) is the real board route, matching how the live site
    is actually browsed.
  - Full suite: `php tests/run.php` - same 5 pre-existing failures,
    0 new regressions.

## Stage 6 - Orchestrate extract -> normalize -> write -> commit -> rebuild

- Changes:
  - `scripts/qdb_archive_import_run.php`: shells out to the Stages 2-4
    scripts in sequence (each stays independently runnable on its
    own), then owns the one thing none of them do - batching the
    newly written files into git commits of `--batch-size` (default
    500) records each, and triggering exactly one rebuild afterward
    via the existing `scripts/rebuild_read_model.php` (the safer
    build-candidate-then-promote path, not a raw
    `ReadModelBuilder::rebuild()` call, since this is meant to run for
    real). `--repository-root` and `--database-path` are both
    required, no defaults, since this writes and commits real files.
    `--limit=N` truncates to the first N normalized rows, for a small
    dry run without re-querying MySQL differently.
- Verification:
  - Small dry run (`--limit=1200 --batch-size=100`) against a scratch
    copy of the real qdb repository: exactly 12 commits, 100 records
    each; full score/vote_count diff against the source for all 1,200
    rows - 0 mismatches.
  - Full-scale run (no limit, default `--batch-size=500`) against a
    fresh scratch copy: exactly 30 commits (last one correctly sized
    at 381 = 14,881 mod 500) - "dozens," matching Step 3's target
    exactly; full diff against the source for all 14,881 rows - 0
    mismatches, 0 missing. This is effectively a full dry run of
    Stage 7 already, just against a scratch repository instead of the
    live one.
  - Full suite: `php tests/run.php` - same 5 pre-existing failures (no
    `src/` changes this stage at all - orchestration only, as planned).

## Stage 7 - Run the real import

- Changes: none (execution only, as planned). Ran
  `scripts/qdb_archive_import_run.php` for real against the live
  `state/local_repository_qdb` repository and
  `state/cache/post_index_qdb.sqlite3` read model (both gitignored in
  this outer repo - their own 30 import commits live in that nested
  repository's independent git history, not in this feature branch).
- Verification:
  - 14,881 post records written and committed in exactly 30 batches
    (the last sized 381 = 14,881 mod 500); working tree clean
    afterward.
  - Full diff of `score_total`/`vote_count` for all 14,881 imported
    threads against the source data - 0 mismatches, 0 missing.
  - Ran a second full rebuild (`scripts/rebuild_read_model.php`)
    against the now-real repository and re-diffed all 14,881 threads -
    0 mismatches, confirming imported scores are rebuild-safe for real,
    not just in a scratch copy.
  - Rendered the real qdb board (`/latest`) through `Application`
    directly: displayed numbers and scores for sampled quotes
    (`#311675` showing `(38/48)`, `#311668` showing `(42/44)`) match
    the source data exactly.
  - Spot-read 10 random imported quotes' bodies straight from disk,
    spanning 2003-2014 - legible, no mojibake.
- Notes: this closes out the feature. All 7 stages landed; the qdb
  instance now holds the real historical archive.
