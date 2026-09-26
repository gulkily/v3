# Fast Post Scoring Productization - Remaining Work Plan

## Purpose

Fast scoring is a private moderation-review signal. It estimates whether a new
post or comment warrants being held for human moderation. It runs
asynchronously after publication, so it does not gate current public
visibility; it is not a reader-facing reputation signal, a public ranking
input, or an automatic moderation decision.

The existing implementation can call a lower-cost model and persist a 0–1
result, but it currently discovers unscored rows by scanning the read model.
This plan replaces that historical-backlog behavior with private, new-content
work tracking and makes the pipeline diagnosable and recoverable.

## Decisions made

- [x] `probability` means the probability that content should be held for human
  moderation: `0` is confidently allowed public and `1` is confidently held.
  The active rubric is `prompts/fast_post_scoring_system.txt`; its content hash
  remains the rubric revision recorded with every result.
- [x] Scores, pending work, attempt metadata, and failures remain in the
  separate private `FAST_SCORING_DATABASE_PATH` SQLite database. They must not
  be copied into the public read model, canonical repository, or static
  artifacts.
- [x] There is no fast-score frontend in this delivery. Readers do not see a
  score, and approved users do not receive a post-page score panel.
- [x] Only content newly published through the normal post-publication flow
  after this feature is enabled is eligible. Do not backfill, scan, or enqueue
  the existing corpus. Rebuilds, imports, and rubric changes do not create
  historical score work.
- [x] A qualifying score gets at most three total automatic attempts: the
  initial attempt plus two provider/invalid-response retries, with a five
  minute delay before retry one and a thirty minute delay before retry two.
  After the cap, only a deployment operator may retry it explicitly.
- [x] A score is advisory only. No threshold, automated hold, ranking,
  moderation action, publishing action, or agent behavior consumes it in this
  delivery.
- [x] Keep private fast-score work/score rows and associated LLM exchange
  records for one year. Provide a dedicated `./v3 fast-score prune` command,
  suitable for scheduled operation, to remove records older than that period.
- [x] Use the same configured LLM provider as the application's other LLM
  calls. Reuse the shared provider-failure categorization, redaction, and
  reporting conventions; the scoring-specific three-total-attempt cap remains
  the additional cost control for this workflow.

## Scope and eligibility

"New" means a thread or reply successfully written through the normal compose
or prepared-post flow and confirmed in the read model. There is currently no
normal post-edit route; future editing is out of scope and must receive its
own eligibility decision.

At successful publication, when `FAST_SCORING_ENABLED` and the new automatic
enqueue setting are both enabled, write one private work record keyed by post
ID, content hash, and rubric revision, then coalescingly enqueue
`fast_score_sweep`. The web request must never wait for an LLM request. If the
private work record or task cannot be queued, record a safe operational error
and leave the published post unchanged; do not silently substitute a scan of
the read model.

The worker processes only pending private work records, not all rows in
`posts`. A content/rubric change does not cause a catch-up scan. It affects
only work created for a subsequently published post. Existing score rows remain
private historical audit data.

## Recommended delivery sequence

### 1. Replace historical sweep discovery with private work tracking

- [x] Add a private pending-work table to the fast-score SQLite database. Store
  post ID, content hash, rubric revision, state, attempt count, last attempted
  time, next eligible time, failure category, and timestamps.
- [x] Migrate existing fast-score databases safely. Do not rely on `CREATE
  TABLE IF NOT EXISTS` to add columns, and do not turn pre-existing posts into
  pending work during migration.
- [x] Change `fast_score_sweep` to claim bounded pending work from that table.
  Retain queue coalescing, but eliminate the read-model scan for missing score
  rows.
- [x] Hook the successful normal post-publication/read-model path to create the
  work record and enqueue the coalesced sweep. Do not hook rebuilds, imports,
  or rubric changes.
- [x] Retire or change the synchronous `/api/score_post` behavior so it cannot
  bypass private work tracking or become a second production execution path.

Acceptance: publishing one eligible new post creates one private pending-work
row and one coalesced worker wake-up; starting the feature creates no work for
the existing corpus.

### 2. Implement bounded retry and failure semantics

- [x] Treat `scored` and deterministic `excluded` outcomes as terminal.
- [x] Retry only `provider_error` and `invalid_response` automatically, on the
  defined five- and thirty-minute schedule, for at most three total attempts.
- [x] Record `config_missing` as actionable but do not retry it automatically;
  a deployment operator fixes configuration and explicitly retries the named
  record. Do not create work at all while fast scoring or automatic enqueueing
  is disabled.
- [x] Preserve a small, redacted failure code and safe message. Define an
  allowlist of failure categories and redact provider exception text before it
  reaches score storage, task-queue output, or a diagnostic command. Never
  store credentials.
- [x] Add audited, narrow CLI retry and invalidate actions for an explicit
  post/content/rubric record. They must not support a blanket historical
  backfill.

Acceptance: a transient provider failure is retried twice at most; a capped or
configuration failure is visible and can be retried by a deployment operator
without SQLite edits; one failed post never fails the rest of a worker batch.

### 3. Make the private pipeline diagnosable

- [x] Add `./v3 fast-score status` (or an equivalently focused task-queue
  subcommand) for pending, terminal, retryable, and capped counts; recent safe
  failures; active rubric revision; and task-queue state, without raw SQLite
  queries.
- [x] Add a bounded live-provider smoke command for one explicit diagnostic
  request. It must show only safe provider/model/structured-output/exchange
  metadata and must never create a corpus sweep.
- [x] Make task-queue exchange recording use the same feature-flag evaluation
  as the application, and document the private exchange path. When linking an
  exchange, match `post_id`, `content_hash`, and `call_type=fast_post_score`
  so stale or unrelated post exchanges are not presented as the score audit.
- [x] Document the worker schedule, worker concurrency, batch limit, retry
  delays, and the resulting maximum requests per newly published post.
- [x] Add `./v3 fast-score prune`, suitable for scheduled operation, to remove
  private work/score rows and associated LLM exchanges older than one year.

Acceptance: an operator can identify a failed new-content score, fix its
configuration, explicitly retry it after the automatic cap, and confirm its
terminal result without editing a SQLite file.

## Deferred work

- A browser results page, post-page diagnostic panel, reader-visible score,
  sorting, moderation automation, and agent-reply behavior are out of scope.
- A future product consumer requires a validation/calibration set, a measured
  threshold, audit events, override controls, rollback behavior, and an
  explicit visibility policy before it can influence a decision.
- Future post editing needs an explicit rule for whether an edit creates a new
  score-work record. It must not be inferred from the current immutable-post
  flow.
