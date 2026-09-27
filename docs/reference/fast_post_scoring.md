# Fastmod

Fastmod provides one focused 0–1 moderation probability without running full
post analysis. It is disabled by default and is intended for approved operators
or internal workflows.

## Configuration

Set `FAST_SCORING_ENABLED` and `FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED` to
`true`. The scorer always uses the normal `LLM_*` provider settings. It may use
`FAST_SCORING_LLM_MODEL` to select a lower-cost model from that same provider.

`FAST_SCORING_PROMPT_PATH` selects the rubric prompt. Its text must define exactly what probability 0 and 1 mean. The model receives the target post text; replies additionally receive bounded parent and root context. It returns only a numeric `probability` value.

`FAST_SCORING_DATABASE_PATH` optionally selects private SQLite score state. It
defaults to `state/private/fast_scores.sqlite3`, outside the published
read-model database. Stored results are current only for the matching post
content hash and prompt-text (rubric) revision, so an edited post or rubric is
scored again.

## Batch scoring

Only newly published posts create private Fastmod work. The worker never scans or
backfills the existing corpus. It processes that work through the existing task
queue without making the publishing request wait for the provider:

```bash
./v3 task-queue enqueue-fast-score
./v3 task-queue run --limit=1 --score-limit=25
```

The enqueue command is coalesced: it leaves one queued or running sweep rather
than creating duplicates. A sweep processes up to `--score-limit` pending
private work records. `--limit` is the number of queue tasks a worker claims,
not the number of posts. Provider and invalid-response failures receive at
most two delayed retries after the initial attempt; further retries require an
explicit operator command.

Install the existing task-queue cron reference with `./v3 task-queue cron`.
It runs one locked worker task per minute by default; `--score-limit` defaults
to 25 pending work records per sweep. The execution lock keeps concurrent cron
invocations from running overlapping sweeps.

## API

`POST /api/score_post` is retired and returns `410`; it cannot bypass the
private worker pipeline.

- `scored` results have a probability in the inclusive 0–1 range.
- `excluded` is a deterministic, heuristic-only outcome with no probability.
- `disabled`, `config_missing`, `provider_error`, and `invalid_response` are explicit non-score outcomes.

## Operator commands

`./v3 fast-score status` reports private Fastmod work, scores, and task-queue state.
`retry` and `invalidate` require an explicit post ID, content hash, and rubric
revision; they do not support historical backfill. `smoke --post-id=...` makes
one deliberate provider request without creating a score row or corpus sweep.
`prune` removes Fastmod rows and `fast_post_score` exchange records older
than one year by default.

Model exchanges use the existing private LLM-exchanges audit surface. A scored
result is shown as a fractional fast-moderation score on its public
`/posts/{id}` detail page; the page reads it from the private score database
while rendering. Scores are not added to thread cards, APIs, canonical records,
or the read model, and do not make an automated moderation decision.
