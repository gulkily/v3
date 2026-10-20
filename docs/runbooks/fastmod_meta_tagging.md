# Automatic root meta tags

Fastmod can add `meta` to newly published local top-level posts after their
ordinary evaluation. Replies and historical/backfill evaluations do not add tags.
This is independent of advisory moderation scores and does not change ranking,
votes, author approval or visibility.

## Enable and verify

Enable existing Fastmod scoring and automatic enqueue, and select the `meta`
rubric in Tools → Fastmod. Then open **Tools → Feature Flags → Fastmod** and
turn on **Automatic Fastmod tagging** with your root-approved browser identity.
The shared flag is `FASTMOD_AUTOMATIC_TAGGING_ENABLED`; it controls the tagging
capability for this and future mappings, not an individual rubric or tag.
Scoring must be enabled for the tagging flag to be effective.

The initial meta threshold is 0.90, inclusive. To change it, set
`FASTMOD_META_THRESHOLD` in private configuration or the environment. Selecting
rubrics controls which probabilities are evaluated; it does not enable actions.
There is no mapping editor or per-rubric feature flag.

Enabling/changing the flag makes no model calls and does not tag old posts.
Only the initial ordinary evaluation of a root published while tagging is on is
eligible, including retries of that work. The flag uses the existing signed
save flow; on save failure, the effective state stays visible and you can retry.

1. Run `./v3 fast-score meta-status` to initialize/check private meta state.
2. Publish a new local top-level post normally. If manual execution is needed,
   run `./v3 task-queue enqueue-fast-score` to find its task ID, then run only
   Fastmod with `./v3 task-queue run --task-id=ID --limit=1 --score-limit=1`.
   The task ID is instance-specific; do not reuse an ID from another runbook
   or an older completed task.
3. Inspect the existing score and `./v3 fast-score meta-status`. A successful meta
   score at or above threshold adds the existing-style label and makes the root
   discoverable under `/tags/meta`. A failure or missing score adds no tag.
4. `./v3 fast-score meta-retry` retries classification application from retained
   ordinary evidence and repeats pending artifact invalidation, without calling
   the provider. Use `--database-path=PATH` when the read model is non-default.
   Use `--repository-root=PATH` for a non-default instance, consistently with the
   task worker. Provider failures still use the existing Fastmod work retry commands.

A pending item without retained successful evidence still needs its ordinary
scoring work to complete. Stale content/model/rubric evidence cannot create a
new tag. Existing classifications remain stable when models, rubrics, scores or
thresholds change; there is no automatic reclassification in this release.

### Overrides and upgrade compatibility

Effective tagging precedence is:

1. `FASTMOD_AUTOMATIC_TAGGING_ENABLED` environment override.
2. An explicitly saved shared site value, including false.
3. The old `FASTMOD_META_TAGGING_ENABLED` environment/private setting as a
   compatibility fallback only.
4. False.

The shared switch defaults off. Existing legacy enablement remains effective
until a shared override is supplied. Saved site false beats legacy true; there
is no second meta-only flag in the interface. The new shared key is a site flag
or environment override, not a new private-file enablement setting. Existing
scoring/dependency policy still applies. Environment overrides appear locked on
the Feature Flags page; change the process environment and restart it if needed.
Invalid site state blocks new tagging unless an explicit shared environment
override governs it; existing flag error/recovery UI identifies the problem.

## Correct, disable and roll back

These commands require the same trusted deployment-shell access as the existing
Fastmod CLI; they are not public HTTP or browser actions.

```bash
./v3 fast-score meta-remove --post-id=POST_ID
./v3 fast-score meta-rollback
```

Remove also suppresses future application for that root. Rollback removes all
current machine tags and suppresses all existing eligible receipts, including
in-flight work. Repeated commands are safe; both preserve independent human
`meta` labels. There is no restore command. New roots published afterward can
still qualify while tagging is enabled.

To stop additions, turn off **Automatic Fastmod tagging** in Feature Flags.
This preserves existing labels and suppressions, including when the legacy
private setting is true. Disable first, then run `meta-rollback` to
stop additions and remove existing machine labels. Re-enabling does not backfill
historical results or undo suppression.

Commands report generation, `dirty` and item state. A nonzero exit or dirty
state after interruption means recovery remains pending; repeat the operation
or run `meta-retry`. Successful scores and retained application evidence commit
together; classification or invalidation failure does not require a second
provider call. Do not clear pending state by editing SQLite.

## Storage, backup and generated output

All probabilities remain in the configured private Fastmod SQLite database
(default `state/private/fast_scores.sqlite3`). That database also owns eligibility,
minimal retained evidence, machine tags and suppression. These persistent tables
are independent of ordinary score/work pruning. Canonical Git alone cannot
restore them. Stop writers and back up private SQLite consistently with its
journal/WAL state, or use SQLite's online backup mechanism. Restore a matching
private backup before resuming workers; never reconstruct suppression from
public labels. A missing database is reported in operator logs; ordinary human
reading still works, but lost private state requires restore.

Live reads fetch active tag IDs through a separate read-only connection and join
only those public IDs in request-local SQLite state. Scores and audit evidence
are never attached to user-entered SQL. There is no persistent live read-model
copy and no generated tag record/signature/Git commit per item.

Generated static/offline output contains effective root labels only. Public
backups/snapshots exclude Fastmod scores, evidence and suppression. Classification
changes invalidate generated presentation and enqueue offline regeneration;
HTML falls back to live rendering until rebuilt. Build/generation checks reject
artifacts built before a correction, including later static release activation.
Run the existing build-static and offline-publication commands to regenerate;
use the same configured instance/static/queue paths for worker and CLI. Repeated
invalidation is deliberate recovery, even if the database tag change already
committed. Existing copies already downloaded are snapshots and do not update
until the reader reconnects.

Deploy/revert code together with these private schema changes. Stop workers
before downgrading, preserve the private backup and retire generated artifacts
before a release without machine-tag support. Do not relabel machine tags as
human canonical records to make an older release understand them.

## Verification

`php tests/run.php FastmodTaggingFlagTest FeatureFlagEvaluatorTest FastmodMetaTest PublicOfflineSnapshotBuilderTest` and
`node tests/browser/fastmod.mjs` cover deterministic mechanics, real signed
publication, root discovery, reply exclusion, CLI suppression/rollback and
public snapshot isolation. The browser uses a local fake provider; these checks
do not measure live-model accuracy. No human-labeled accuracy gate is required
for this release.
