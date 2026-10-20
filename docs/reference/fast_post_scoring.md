# Fastmod

Fastmod records independent 0–1 rubric scores from one LLM call per evaluation,
without running full post analysis. Optional [root meta tagging](../runbooks/fastmod_meta_tagging.md)
can apply the meta result to existing tag discovery; other scores remain advisory.
Fastmod is disabled by default and intended for approved operators or internal workflows.

## Configuration

Set `FAST_SCORING_ENABLED` and `FAST_SCORING_AUTOMATIC_ENQUEUE_ENABLED` to
`true`. The scorer always uses the normal `LLM_*` provider settings. It may use
`FAST_SCORING_LLM_MODEL` to select a lower-cost model from that same provider.
An absent or blank override inherits `LLM_MODEL`; generated configuration defaults
to inheritance. Explicit overrides are preserved, so use a model identifier valid
for the configured endpoint (for example, `gpt-5-nano` for direct OpenAI).

`FAST_SCORING_PROMPT_PATH` selects the shared task introduction (default:
`prompts/fastmod_introduction.txt`). The former generated default,
`prompts/fast_post_scoring_system.txt`, is automatically treated as the new
introduction path. Custom introductions must request CSV, not JSON. The model
receives target text plus bounded parent/root context for replies.

### Rubric selection

Open **Tools → Fastmod** as a root-approved operator. Each rubric displays its repository prompt beside its checkbox. Enable
one or more rubrics and save using the browser identity signature. No individual
rubric is mandatory, but the panel and harness reject an empty selection.
Saving settings does not enqueue work or call the model. Disabled rubric text
is omitted from the prompt and response contract entirely.

Each rubric is a compact `.txt` template under `prompts/fastmod/`, ideally a
single bullet. The filename without `.txt` is its stable identity (lowercase
letters, digits, underscores; first character a letter). Initial rubrics are
`moderation` and `meta`. Meta covers catch-all application support: feature
requests, bug reports, and help concerning the platform, instance, or interaction
with the application and interfaces. Ordinary subject-matter questions are not
meta. Support and hostility may overlap.

Adding a template automatically adds a panel option; changing/removing templates
requires no scoring-pipeline changes. Preserve an identity's meaning; use a new
identity for a different signal. Before removing an enabled rubric, disable it
and leave at least one other selected. Missing, empty, or invalid selected
rubrics fail configuration rather than silently disappearing. A renamed rubric
is a removed rubric plus a new one.

Selection is stored privately and atomically in
`state/private/fastmod_rubrics.json`, shared by web and worker processes; ensure
both run against the same application root and can access this file. Without a
saved selection, both initial rubrics are enabled; a PHP private configuration
may set `FAST_SCORING_RUBRICS` to a nonempty array of IDs as the initial selection.
The saved panel selection takes precedence. IDs are sorted lexically for the
prompt and CSV order.

The response is only comma-separated probabilities, for example `0.8,0.2` for
`meta,moderation`, or `0.8` for meta alone. The harness trims surrounding/field
whitespace and validates exact count, numeric syntax, finite values, and range.
It rejects prose, markdown, missing/extra fields, and invalid values as a whole;
it never fills missing scores, clamps values, or accepts partial results.
Recorded results use rubric IDs, not CSV positions. The legacy `probability`
field is moderation only and is null for meta-only results; it is never a blend.

`FAST_SCORING_DATABASE_PATH` optionally selects private SQLite score state. It
defaults to `state/private/fast_scores.sqlite3`, outside the published
read-model database. Stored results are current only for the matching post
content hash and effective introduction/ordered-rubric revision. Changing
selection or templates does not automatically score existing posts. Pending work
from an older composition is excluded with `rubric_changed_before_score` before
a provider call. Use the existing explicit audit/backfill workflow if re-evaluation
is wanted; historical results keep their original rubric identity.

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

Add `--verbose` to `task-queue run` to print each Fastmod result and provider
request as it happens. `--quiet` suppresses that progress output.

## API

`POST /api/score_post` is retired and returns `410`; it cannot bypass the
private worker pipeline.

- `scored` results contain named rubric scores in the inclusive 0–1 range.
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

Model exchanges use the existing private LLM-exchanges audit surface. Each recorded
score is labeled independently on its public
`/posts/{id}` detail page; the page reads it from the private score database
while rendering, with legacy/earlier-revision markers where applicable. Scores are not added to thread cards, APIs, canonical records,
or the read model, and do not make an automated moderation decision. Optional meta
tagging publishes only the resulting root label using existing presentation;
probabilities and evidence remain in private storage and are excluded from public backups.


## Deployment, recovery, and rollback

Deploy code and prompt files together, including all selected templates. The
private Fastmod store migrates automatically and preserves old single-score rows
as legacy moderation-only results. The core read-model schema is unchanged.
Custom prompt overrides need a CSV introduction; generated legacy default paths
are translated automatically. Preserve private score and settings files across
application releases. No automatic backfill or new scheduling trigger is added.

Inspect configuration errors in the panel and `./v3 fast-score status`; correct
missing/empty templates or permissions before retrying. For a malformed manually
edited selection, restore a valid nonempty JSON array in the private settings
file (or remove it to restore configured defaults). Provider and invalid CSV
responses use existing bounded delayed retries and explicit operator retry.
Do not interpret failures as a score of zero.

Before reverting application code, stop the worker and preserve a backup of the
private score/settings/task databases. Older binaries understand only the former
single-score contract and must not execute new-format queued work. Restore a
matching prior private-state backup or keep workers stopped until the compatible
release is restored; never relabel new score maps as one legacy probability.

Verification: `php tests/run.php` and `node tests/browser/fastmod.mjs`. The browser
check uses temporary private state and a deterministic local HTTP provider to
exercise signed selection, normal publication, worker evaluation, omission,
multiple scores, malformed output, and explicit retry. It makes no external
provider requests. Real-model rubric accuracy remains an operator evaluation.


## Automatic root meta tagging

Enable **Automatic Fastmod tagging** in Tools → Feature Flags → Fastmod,
with scoring/ordinary enqueue enabled and the meta rubric selected. One shared
`FASTMOD_AUTOMATIC_TAGGING_ENABLED` flag controls all automatic tag mappings;
this release includes only the root meta mapping. `FASTMOD_META_THRESHOLD`
defaults to 0.90, inclusive.
Only newly published local roots qualify; replies, imports, old work and backfill
do not. Existing tags are stable across configuration/model/rubric changes.

Use `./v3 fast-score meta-status`, `meta-remove --post-id=ID`, `meta-retry`
and `meta-rollback` for shell-based operation. Removal suppresses reapplication;
rollback preserves human tags. Disabling stops additions without removing tags.
The enable switch reuses existing Feature Flags; no tag styling, mapping editor,
restore workflow or automatic reclassification is added. Shared environment
override takes priority over a saved site value, then legacy
`FASTMOD_META_TAGGING_ENABLED` is a compatibility fallback. A saved shared false
overrides legacy true. See the [runbook](../runbooks/fastmod_meta_tagging.md) for
backup, artifact freshness and interrupted recovery.
