# QDB Archive Importer — Step 3: Development Plan

## Stage 1
- Goal: let a post record carry its own imported score/vote-count seed,
  so an imported quote doesn't need a second file to hold its score.
- Dependencies: none.
- Expected changes:
  - `PostRecordParser`/`PostRecord` accept two new optional headers
    (an imported score seed and an imported vote-count seed) alongside
    the existing ones.
  - `ReadModelBuilder`'s thread score/vote-count reduction initializes
    its running total from that seed (defaulting to 0 when absent, as
    today) instead of always starting from 0, then accumulates ordinary
    reaction deltas on top exactly as it does now.
- Verification approach: unit-level — build one post record with a
  seed and zero reactions, run `ReadModelBuilder::rebuild()`, confirm
  `score_total`/`vote_count` equal the seed; add one ordinary reaction
  and confirm the total is seed-plus-delta; rebuild again and confirm
  unchanged.
- Risks or open questions:
  - Must not change scoring for any existing post that has no seed
    (every non-imported post on every instance) — needs a regression
    check, not just a new test.
  - Header naming should read as clearly import-specific, not as a
    general scoring feature.
- Canonical components/API contracts touched: `PostRecordParser`,
  `PostRecord`, `ReadModelBuilder` (thread score/vote-count
  initialization only).

## Stage 2
- Goal: extract qualifying quote rows from the MySQL dump into a plain
  intermediate dataset, without hand-parsing mysqldump's SQL syntax.
- Dependencies: none.
- Expected changes:
  - Load `~/qdb_database/backup.sql` into a scratch MariaDB database
    (`mariadb-server` and PHP's `pdo_mysql`/`mysqli` are already
    available) — a one-time, throwaway load, not a persistent service.
  - A standalone script connects via PDO and runs one query
    (`quote_id`, `quote`, `add_timestamp`, `score`, `vote_count` where
    `approved=1 AND spam=0 AND deleted_flag=0`), streaming results into
    the plain intermediate dataset.
  - Scratch database dropped once extraction finishes.
- Verification approach: extracted row count matches a direct
  `SELECT COUNT(*)` with the same `WHERE` clause; spot-check a handful
  of extracted rows (including one with embedded commas/quotes/newlines
  in the quote text) against the live table by eye.
- Risks or open questions: loading a ~731MB dump takes real wall-clock
  time and scratch disk space — budget for it, and make sure the
  scratch database/credentials are local-only and torn down afterward.
- Canonical components/API contracts touched: none (standalone script
  + scratch database, no app code).

## Stage 3
- Goal: normalize each extracted quote body to text this app's
  canonical records can legally hold.
- Dependencies: Stage 2's extracted dataset.
- Expected changes:
  - A normalization step transcodes each quote body from latin1 to
    UTF-8, then runs it through the same shape of check
    `UnicodeTextPolicy::normalizeBody()` already enforces, flagging
    (not silently dropping) any row that would fail.
- Verification approach: zero flagged rows, or an explicit, reviewed
  list of flagged rows with a decision per row; manual read-through of
  a random sample to confirm no mojibake.
- Risks or open questions: a 22-year-old dump may mix encodings across
  eras; flagged rows need a human decision, not an automatic fix.
- Canonical components/API contracts touched: `UnicodeTextPolicy`
  (read-only reuse of its rules, not a code change).

## Stage 4
- Goal: write one canonical post record per qualifying quote, carrying
  its stable public number, its real historical date, and its score
  seed, all in a single file.
- Dependencies: Stage 1 (seed headers), Stage 3 (normalized dataset).
- Expected changes:
  - A deterministic Post-ID convention derived from the original
    `quote_id` (distinct from the live `thread-<timestamp>-<hash>`
    convention, so the two can never collide).
  - `Created-At` set from the row's original `add_timestamp`, not
    import time, so records land in their true historical dated shard.
  - Stage 1's score/vote-count seed headers set from the row's source
    `score`/`vote_count`.
  - One post record file written per qualifying quote directly into
    the qdb repository's `records/posts/` tree (no `LocalWriteService`
    call).
- Verification approach: parse every written record back with
  `PostRecordParser` before considering the stage done; confirm file
  count equals Stage 3's row count; confirm no Post-ID collisions; after
  a full `ReadModelBuilder::rebuild()`, every imported thread's
  `score_total`/`vote_count` matches the source dump exactly for a full
  row-by-row diff, not a sample.
- Risks or open questions: none beyond Stage 2/3's.
- Canonical components/API contracts touched: `PostRecordParser`,
  `CanonicalPathResolver::datedPost()`.

## Stage 5
- Goal: show the preserved qdb number on the board instead of today's
  raw internal Post-ID, for the qdb instance only.
- Dependencies: Stage 4's Post-ID convention.
- Expected changes:
  - `templates/partials/quote_card.php` displays the number embedded in
    Stage 4's Post-ID convention when present, otherwise falls back to
    today's behavior unchanged.
- Verification approach: load the qdb board and a quote's permalink
  page, confirm the displayed number matches the source `quote_id`;
  confirm zenmemes/chouse board rendering is pixel-identical to before
  (regression check, not just "looks fine").
- Risks or open questions: none.
- Canonical components/API contracts touched: `quote_card.php`.

## Stage 6
- Goal: tie Stages 2–5 together into one runnable, bounded, one-shot
  import.
- Dependencies: Stages 1–5.
- Expected changes: a single CLI entry point that runs extraction through
  record-writing end to end, committing the qdb repository in bounded
  batches (not one commit per quote), then triggering exactly one full
  `ReadModelBuilder::rebuild()` at the end.
- Verification approach: dry run against a small slice of the dump
  (e.g. one year of quotes) end to end; confirm commit count stays in
  the dozens, not in the tens of thousands.
- Risks or open questions:
  - Batch size needs a concrete number (time/commit-count tradeoff) —
    decide during implementation, not here.
- Canonical components/API contracts touched: none new (orchestration
  only).

## Stage 7
- Goal: run the real import and confirm the feature's success criteria.
- Dependencies: Stage 6.
- Expected changes: none (execution only) — produces the qdb
  instance's real imported record set.
- Verification approach: full run against the entire dump; board loads
  with every qualifying quote under its original number and matching
  score; a second full read-model rebuild leaves every score unchanged;
  encoding spot-check across a random sample.
- Risks or open questions: real-data edge cases (e.g. unusually long
  quotes, rare characters) may surface here for the first time despite
  Stage 3's checks.
- Canonical components/API contracts touched: none new.
