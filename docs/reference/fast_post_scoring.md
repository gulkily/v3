# Fast Post Scoring

Fast post scoring provides one focused 0–1 probability without running full post analysis. It is disabled by default and is intended for approved operators or internal workflows.

## Configuration

Set `FAST_SCORING_ENABLED` to `true`. The scorer uses the normal `LLM_*` provider settings unless any `FAST_SCORING_LLM_*` override is present; at minimum, set `FAST_SCORING_LLM_MODEL` to a lower-cost model suitable for a short classification request.

`FAST_SCORING_PROMPT_PATH` selects the rubric prompt. Its text must define exactly what probability 0 and 1 mean. The model receives the target post text; replies additionally receive bounded parent and root context. It returns only a numeric `probability` value.

`FAST_SCORING_DATABASE_PATH` optionally selects private SQLite score state. It
defaults to `state/private/fast_scores.sqlite3`, outside the published
read-model database. Stored results are current only for the matching post
content hash and prompt-text (rubric) revision, so an edited post or rubric is
scored again.

## Batch scoring

Use the existing task queue to score the backlog without a request per post:

```bash
./v3 task-queue enqueue-fast-score
./v3 task-queue run --limit=1 --score-limit=25
```

The enqueue command is coalesced: it leaves one queued or running sweep rather
than creating duplicates. A sweep processes up to `--score-limit` nonempty
root posts or replies in stable order, then requeues itself if current posts
remain. `--limit` is the number of queue tasks a worker claims, not the number
of posts. Provider failures are stored on the affected private score record and
do not stop the rest of a batch.

## API

`POST /api/score_post?post_id=<id>` requires an approved viewer. Its response includes `post_id`, `status`, nullable `probability`, `source` (`llm`, `heuristic`, or `none`), and `signals`.

- `scored` results have a probability in the inclusive 0–1 range.
- `excluded` is a deterministic, heuristic-only outcome with no probability.
- `disabled`, `config_missing`, `provider_error`, and `invalid_response` are explicit non-score outcomes.

Model exchanges use the existing private LLM-exchanges audit surface. The endpoint does not publish a score on post cards or make a reader-facing threshold decision.
