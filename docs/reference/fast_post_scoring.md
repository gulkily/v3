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

For an audit or historical backfill using a model other than `gpt-5-nano` or
`openai/gpt-5-nano`, set `FAST_SCORING_INPUT_USD_PER_MILLION` and
`FAST_SCORING_OUTPUT_USD_PER_MILLION` to that model's current provider prices.
They can also be supplied as matching command options for one invocation.

## Batch scoring

Only newly published posts create ordinary private Fastmod work. The worker never
scans the existing corpus. It processes that work through the existing task queue
without making the publishing request wait for the provider:

```bash
./v3 task-queue enqueue-fast-score
./v3 task-queue run --limit=1 --score-limit=25 --work-limit=250
```

The enqueue command is coalesced: it leaves one queued or running sweep rather
than creating duplicates. A sweep makes up to `--score-limit` provider calls
(25 by default); a provider failure or invalid response consumes one call
because the request was made. `--work-limit` separately bounds all examined
private work rows (250 by default), including local heuristic exclusions that
need no provider call. `--limit` is the number of queue tasks a worker claims,
not a post or provider-call limit. Provider and invalid-response failures
receive at most two delayed retries after the initial attempt; further retries
require an explicit operator command.

Install the existing task-queue cron reference with `./v3 task-queue cron`.
It runs one locked worker task per minute by default; `--score-limit` defaults
to 25 provider calls and `--work-limit` defaults to 250 examined work rows per
sweep. The execution lock keeps concurrent cron invocations from running
overlapping sweeps.

## API

`POST /api/score_post` is retired and returns `410`; it cannot bypass the
private worker pipeline.

- `scored` results have a probability in the inclusive 0–1 range.
- `excluded` is a deterministic, heuristic-only outcome with no probability.
- `disabled`, `config_missing`, `provider_error`, and `invalid_response` are explicit non-score outcomes.

## Operator commands

`./v3 fast-score status` is the operator overview. It separates regular
new-content work from historical backfill work, shows retained outcomes and
batch progress, reports reserved estimate against the batch cap, and gives the
next queue action. Regular work intentionally runs before historical backfill.
Use `./v3 fast-score status --verbose` for recent individual work rows; hashes
are shortened there to keep the output scannable.
`retry` and `invalidate` require an explicit post ID, content hash, and rubric
revision; they do not support historical backfill. `smoke --post-id=...` makes
one deliberate provider request without creating a score row or corpus sweep.
`prune` removes Fastmod rows and `fast_post_score` exchange records older
than one year by default.

### Historical audit and backfill

Historical content is never included unless an operator explicitly requests it:

```bash
./v3 fast-score audit --include-existing
./v3 fast-score backfill --include-existing --confirm --max-posts=100 --max-cost-usd=0.10
./v3 task-queue run --limit=1 --score-limit=25 --work-limit=250
```

`audit` is read-only. It reports current-content/current-rubric state counts,
the selected model, and a cost estimate using recorded usage for that model or
a conservative fallback. It writes no score, work, task, or exchange rows.

`backfill` snapshots only currently unrated candidates into a private batch.
Both `--max-posts` and `--max-cost-usd` are required; the lower resulting
limit wins. The worker reserves the batch's estimated cost before each provider
attempt, including a retry, and stops a batch with `budget_exhausted` when the
remaining reservation cannot cover another attempt. `fast-score status` shows
recent batch IDs, state, and reserved estimate. Inspect exact provider payloads
and token usage through the existing private LLM-exchange page.

Historical backfill work is isolated from ordinary new-content sweeps. It is
private, advisory, and retained/pruned with the same one-year Fastmod policy.

Model exchanges use the existing private LLM-exchanges audit surface. A scored
result is shown as a fractional fast-moderation score on its public
`/posts/{id}` detail page; the page reads it from the private score database
while rendering. Scores are not added to thread cards, APIs, canonical records,
or the read model, and do not make an automated moderation decision.
