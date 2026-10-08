# QDB Archive Importer — Step 2: Feature Description

## Problem

The qdb instance has no quotes yet. The real qdb.us historical archive
(~14,881 approved quotes, 2003–2025, each with a final score/vote tally)
sits in a MySQL dump and needs to become canonical records in this
app's git-backed qdb repository, preserving each quote's original
number and score, without replaying millions of historical votes.

## User stories

- As the site operator, I want to run a one-shot import of the qdb.us
  archive so the qdb instance has real historical content instead of
  being empty.
- As a visitor browsing the qdb board, I want to see each quote's
  original qdb.us number so the archive feels authentic to the classic
  site.
- As a visitor, I want to see each quote's real historical score so the
  board reflects actual community reaction, not zeros.
- As the site operator, I want the import to stay a bounded operation
  (tens of thousands of records, not millions) so the git repository
  and read-model rebuild stay practical.

## Core requirements

- Import quotes where `approved=1`, `spam=0`, `deleted_flag=0` from the
  dump as canonical post records in the qdb instance's repository.
- Each imported quote keeps its original qdb.us `quote_id` as a stable,
  permanent public display number, distinct from its internal record ID.
- Each imported quote's final score/vote tally is represented as one
  aggregate record per quote (not one per historical vote), and must
  still read correctly after a full read-model rebuild.
- Each quote's original timestamp is preserved as its creation date
  (historical dates, not the import run's date).
- Source text (latin1) is transcoded to valid UTF-8 with no mojibake
  before being written.

## Shared component inventory

- `templates/partials/quote_card.php` / `board.php` — existing qdb list
  rendering. Reused, with one extension: display the preserved display
  number instead of today's raw internal record ID (currently
  conflated, per `qdb_todo.txt` item 2).
- Canonical record read/write (`CanonicalRecordRepository`,
  `PostRecordParser`, `CanonicalPathResolver`) — reused as-is for post
  records; already supports arbitrary historical creation dates.
- Reaction/scoring pipeline (`PostReactionRecordParser`, `TagScore`,
  `ReadModelBuilder`) — reused, with the additive extension decided in
  Step 1 (Option B): one aggregate score record per quote instead of
  per-vote records.
- `LocalWriteService` (the live, signature-verified, commit-per-write
  path for real user actions) — not reused for the bulk import; invoking
  it ~80k times would mean its per-call locking/commit cost at import
  volume. The importer is a separate one-shot script that writes
  canonical files directly and commits in bounded batches, then
  triggers one read-model rebuild.
- `state/local_repository_qdb` — the existing qdb instance repository;
  the importer writes into its existing `records/` layout, nothing new.

## Simple user flow

1. Operator points the importer at the qdb MySQL dump and the qdb
   instance's repository root.
2. Importer filters to approved/non-spam/non-deleted quotes and
   transcodes each quote body to UTF-8.
3. Importer writes one post record and one aggregate-score record per
   qualifying quote, preserving the original number and timestamp.
4. Importer commits the new records in bounded batches.
5. A full read-model rebuild runs over the updated repository.
6. Operator loads the qdb board and confirms quotes appear with
   authentic numbers, bodies, and scores.

## Success criteria

- Every qualifying quote appears on the qdb board under its original
  qdb.us number.
- Displayed scores/vote counts match the source dump's final tallies.
- A full read-model rebuild leaves every imported score unchanged.
- The import stays bounded (tens of thousands of records/commits, not
  millions).
- No garbled or mis-encoded text appears on any imported quote.
