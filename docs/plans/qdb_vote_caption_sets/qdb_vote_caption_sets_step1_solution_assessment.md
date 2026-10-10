# QDB Vote Caption Sets — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./qdb_vote_caption_sets_step1_solution_assessment.md) · [Step 2](./qdb_vote_caption_sets_step2_feature_description.md) · [Step 3](./qdb_vote_caption_sets_step3_development_plan.md) · [Step 4](./qdb_vote_caption_sets_step4_implementation_summary.md)

## Original Query

If we review the backed-up QDB database in `~/qdb_database`, we'll find that
the voting buttons had sets of captions for up and down, such as Good/Bad,
that were displayed along with up and down arrows. They were selected randomly
per page. Please review the database and write Step 1 of adding this feature to
our QDB implementation, with your best understanding. Write out your
understanding of the feature in the Step 1 document.

## Understood Intent

Restore classic QDB voting as a tag-based scoring feature: render an up-arrow
and down-arrow with a matched, randomly chosen caption pair, and write the
pressed caption's tag—not generic `upvote` or `downvote`—to the thread.
Each configured caption tag has an explicit positive (`+1`) or negative
(`-1`) score-matrix value. Selection is **once per rendered QDB page**, so
every quote vote control on that response uses the same pair; it is not
randomized separately per quote, button, click, or voter. Accessibility labels
still describe the action as an upvote or downvote as well as its caption.

Caption text containing spaces needs one canonical, whitespace-free tag form
(for example, displayed `Keep It` becomes tag `keep-it`), because the
canonical reaction-record format stores tags as space-separated tokens. The
caption store therefore owns the display label, canonical tag, and polarity;
the scoring and vote-validation path must derive its allowed QDB caption tags
and values from that same source.

Backup evidence: `backup.sql` has a `vote_captions` table keyed by
`caption_set_id` and `vote` (`1` / `-1`), with an `active` flag. Active pairs
are Funny/Unfunny, Good/Bad, Funny/Boring, Funny/Awful (twice, under distinct
set IDs), Good/Awful, Worthy/Sucks, Keep It/Trash It, and Merry/Humbug.
Inactive historical pairs are Good/Bad, Funny/Not, and Funny/Bad. Preserving
the active rows as sets, including the duplicate Funny/Awful set, best matches
the archive's apparent selection pool and weighting.

## Problem

QDB currently renders only `+` and `-` buttons that write generic vote tags;
it lacks both the archived per-page caption-pair presentation and the caption
tags' positive/negative score definitions.

## Options

### Option A — Define the archived pairs and their tag scores in QDB code

Keep the finite, archival caption pool in a QDB-only presentation component;
choose one complete pair while preparing a page, and maintain the matching tag
and score matrix in application code.

- Pros: matches the backup's paired active-set model and per-page behavior;
  preserves duplicate-set weighting; one fixed catalog supplies presentation
  and scoring.
- Cons: caption activation, labels, tag forms, and values require deployment
  changes; duplicates configuration in the static scoring matrix.

### Option B — Randomize a pair independently in each quote-card partial

Have each rendered card choose its own caption pair and write its caption tag.

- Pros: very local presentation change.
- Cons: contradicts the stated per-page behavior; a listing becomes visually
  inconsistent and a single quote's controls could disagree across surfaces.

### Option C — Add a configurable database-backed caption-set and scoring store (Chosen)

Create a QDB SQLite store for caption sets, each label's canonical tag, its
positive/negative value, and active state. QDB chooses one active pair per
page; writes, score calculation, vote counting, and viewer vote state use the
same configured tag/value catalog.

- Pros: fulfills the selected tag-based voting behavior; one authoritative
  catalog governs display, validation, and scoring; future caption changes can
  be made without deploying code.
- Cons: requires a schema, seed/migration, catalog-loading/recovery behavior,
  and a way to keep the read-model scorer in sync with configured tags.

## Recommendation

Adopt **Option C**, per the direction update. Seed its catalog from the
backup's active rows, preserving the duplicate Funny/Awful set as a distinct
selectable pair. Replace generic QDB `upvote`/`downvote` writes with the chosen
caption's canonical tag and score it according to configured polarity. The
database catalog—not a display-only lookup—must be consulted consistently by
the write validator, scoring matrix, vote total, and viewer-state lookup.
Step 2 should explicitly settle tag normalization, whether a person may add
multiple differently named positive/negative caption tags to one quote, and
coverage for listings, search/random results, permalink pages, and
static/offline renders (including whether a generated artifact keeps its
build-time pair until regeneration).

This is a viable vertical slice: a visitor opens a QDB page, sees one coherent
archival caption pair beside all visible vote arrows, presses (for example)
`Good`, and the resulting `good` tag changes the quote score by `+1`; a
negative caption similarly adds its configured `-1` tag. Another page render
may choose a different active pair.

Waiting for "Approved Step 1" before drafting Step 2.
