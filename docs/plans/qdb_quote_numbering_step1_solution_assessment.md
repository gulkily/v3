# QDB Quote Numbering — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./qdb_quote_numbering_step1_solution_assessment.md) · [Step 2](./qdb_quote_numbering_step2_feature_description.md) · [Step 3](./qdb_quote_numbering_step3_development_plan.md) · [Step 4](./qdb_quote_numbering_step4_implementation_summary.md)

## Original Query

When original QDB accepted new submissions, they got a quote number, and
that number was displayed with the quote — but that's not how our site
works with the qdb site enabled. Please estimate the lift/method of
replicating the original behavior.

## Problem

On the `qdb` site instance, imported archive quotes display their
original qdb.us number (`templates/partials/quote_card.php` extracts a
`-qdb-<N>` suffix baked into the imported post's ID). A quote submitted
live through the normal compose flow has no such suffix, so its card
falls back to showing the raw internal post ID
(`thread-<timestamp>-<random>`) instead of a sequential quote number —
unlike original qdb.us, where every accepted submission was immediately
assigned the next number and shown with it. This gap was already flagged
as deferred work in `qdb_todo.txt` item 2, pending the importer landing
(it has: `docs/plans/qdb_archive_importer_step4_implementation_summary.md`).

## Context found

- Numbering is per-ID, not a separate field: `quote_card.php` derives
  `$displayNumber` purely from a regex on `$thread['root_post_id']`. No
  other template or read-model column currently carries a quote number.
- New threads are created via `LocalWriteService::createThread()`, which
  IDs every thread as `thread-<gmdate>-<random 8 hex>`
  (`generateRecordId()`). This is instance-agnostic — the same method
  runs for zenmemes, chouse, and qdb — and has no awareness of
  `SiteProfileRegistry` today.
- All writes already serialize through `withTimedWriteLock()`, and a
  thread's canonical record + read-model row commit together — so
  assigning a number at creation time can be made race-safe for free by
  doing it inside that existing lock, without new concurrency control.
- Numbers only need to be unique and increasing, not gapless — qdb.us's
  own numbering already has gaps (deleted/rejected quotes), so "max seen
  + 1" is a faithful enough source of truth.
- "Max seen" is cheap to compute directly from the read model, no
  persisted counter needed: `SELECT MAX(CAST(substr(root_post_id,
  instr(root_post_id,'-qdb-')+5) AS INTEGER)) FROM threads WHERE
  root_post_id LIKE '%-qdb-%'` ran against the real local `qdb` read
  model (14,886 rows) in 4ms via a covering index scan. Must extract and
  `CAST` the numeric suffix rather than `ORDER BY root_post_id DESC` —
  plain string sort gives the same answer today only because the current
  max happens to share its digit-count with its neighbors; it breaks once
  numbers cross a digit boundary (e.g. `"99999" > "100000"` as strings).
- Submission has no moderation/approval gate before a quote is visible
  (confirmed in Stage 3/6 of the qdb instance implementation) — a quote
  posts and becomes visible immediately, so the number can be assigned
  at the same moment, with no pending/approved state to track separately.
- A pre-existing, separate gap: the permalink page (`/threads/<id>`)
  renders the generic `thread_root_card.php`, which shows no quote number
  at all (already tracked in `qdb_todo.txt` item 1). Out of scope here —
  this feature only needs to fix the board-list number, where submitted
  quotes already surface right after posting.

## Options

### Option A — Compute a display number at render time (rank-based)
Leave IDs untouched; at board-render time, rank each non-suffixed thread
by creation order and offset by the max imported number.
- Pros: no write-path change.
- Cons: recomputing a global rank on every render (or every board page)
  is real new work per request; doesn't reuse `quote_card.php`'s existing
  suffix-extraction display logic, so needs a second number source
  invented from scratch; fragile if two threads share a timestamp.

### Option B — Assign the number at write time, embedded in the post ID (Recommended)
When `SiteProfileRegistry::active()` is `qdb`, have `createThread()` mint
the ID as `thread-<timestamp>-qdb-<N>` (same shape the importer already
produces) instead of the random-suffix form, where `N` is one past the
highest number the MAX query above finds, run inside the existing write
lock immediately before minting the ID. No new persisted counter.
- Pros: zero changes to `quote_card.php` — its existing `-qdb-(\d+)$`
  display logic already picks this up; reuses the proven write-lock
  commit pattern; scoped entirely to the `qdb` profile, so zenmemes/chouse
  are untouched; no DB schema change.
- Cons: `LocalWriteService` gains a small amount of site-awareness it
  doesn't have today (currently instance-agnostic).

### Option C — New derived read-model column for quote number
Add a `quote_number` column to the `threads` read-model table, populated
by `ReadModelBuilder`/`IncrementalReadModelUpdater` the way `score_total`
is derived today.
- Pros: decouples the human-facing number from the internal ID.
- Cons: a DB schema change (against the project's stated preference to
  avoid one here), and it still needs the same monotonic-counter problem
  solved underneath — just mirrored into a second place (full rebuild and
  incremental updater both need to agree) instead of solved once at write
  time.

## Recommendation

**Option B.** It reuses the import path's existing ID convention and the
already-correct `quote_card.php` display logic unchanged, assigns the
number exactly once at the moment of submission (matching original
qdb.us), and needs no database schema change — the smallest change that
gets a real quote number in front of a submitter immediately after they
post.

**Estimated lift:** small — roughly a half-day, single Step 3 stage.
Changes are confined to `LocalWriteService::createThread()` (site check +
ID minting) and one tiny new piece of counter state seeded once from the
max imported number; `quote_card.php` and the board page need no edits.
