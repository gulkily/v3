# QDB Vote Caption Sets — Step 4: Implementation Summary

> **Feature plan:** [Step 1](./qdb_vote_caption_sets_step1_solution_assessment.md) · [Step 2](./qdb_vote_caption_sets_step2_feature_description.md) · [Step 3](./qdb_vote_caption_sets_step3_development_plan.md) · [Step 4](./qdb_vote_caption_sets_step4_implementation_summary.md)

## Stage 1 - Durable caption catalog

- Changes:
  - Added a dedicated SQLite caption catalog, independent of the rebuildable
    read model, with an optional deployment path override.
  - Seeded all 12 archived sets, their active state, canonical tags, labels,
    polarity, and the separate duplicate Funny/Awful set.
- Verification:
  - `php tests/QdbVoteCaptionStoreTest.php` — passed.
  - `git diff --check` — passed.
- Notes:
  - This stage intentionally adds storage and seed data only; selection,
    scoring, writes, and presentation arrive in later stages.

## Stage 2 - Catalog policy

- Changes:
  - Added a catalog facade for page-pair selection, known-tag score lookup, and
    active-tag validation.
  - Selection accepts only complete active pairs; archived inactive tags remain
    resolvable for historical scoring.
- Verification:
  - `./v3 test QdbVoteCaptionStoreTest` — passed.
  - `git diff --check` — passed.
- Notes:
  - Catalog values are constrained to `+1`/`-1`; activation controls what new
    pages may render and write, not whether existing tag history can be read.

## Stage 3 - Derived QDB vote scoring

- Changes:
  - Added the catalog-backed QDB scoring policy and connected it to full and
    incremental thread-label derivation.
  - Caption tags now score at their configured polarity and count as votes;
    QDB deduplicates caption and legacy vote forms per identity while generic
    profiles retain the static score matrix.
- Verification:
  - `./v3 test QdbVoteCaptionStoreTest TagScoreTest` — 11 passed.
  - `php -l` on the new policy and both read-model paths; `git diff --check` — passed.
- Notes:
  - Post-reaction scoring is deliberately unchanged: captions are thread votes.

## Stage 4 - Server-side caption writes

- Changes:
  - The thread-tag writer accepts only active QDB caption tags, only for QDB
    quote roots, and rejects a second caption vote after any caption or legacy
    QDB vote by that identity.
  - Existing signed API and response contracts remain unchanged; post tags do
    not accept caption votes.
- Verification:
  - `php -l src/ForumRewrite/Write/LocalWriteService.php` — passed.
  - `./v3 test QdbVoteCaptionStoreTest TagScoreTest` — 11 passed.
  - `git diff --check` — passed.
- Notes:
  - Direct API enforcement is in the writer, so client markup cannot bypass
    the active-tag, quote-root, or duplicate-vote checks.

## Stage 5 - Captioned quote listings

- Changes:
  - QDB page controllers select one catalog pair and pass it to every listing,
    random, and search quote card.
  - Quote cards render up/down arrows with caption labels and send their
    canonical caption tag; catalog-wide prior-vote lookup disables later pairs.
- Verification:
  - `./v3 test QdbVoteCaptionStoreTest QdbBoardPolicyTest QdbExperienceRoutingTest` — 11 passed.
  - `php -l` on changed PHP paths and `git diff --check` — passed.
- Notes:
  - Pair selection happens above templates, preserving one consistent pair per
    rendered multi-card page.
