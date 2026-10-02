# QDB Archive Import Runbook

One-shot historical backfill that imports qdb.us's quote archive into a qdb
instance. Already run against local dev's `state/local_repository_qdb` (see
`docs/plans/qdb_archive_importer_step4_implementation_summary.md` for that
run's full verification record). This runbook covers running it against a
real production vhost.

## Why extraction doesn't run on prod

- Production only requires PDO SQLite (per `production_deploy.md`); MySQL/
  MariaDB is not a prod dependency and shouldn't become one for a one-time
  historical backfill.
- Extraction and normalization (Stages 2-3 of
  `docs/plans/qdb_archive_importer_step3_development_plan.md`) already ran
  locally against a scratch MySQL database loaded from
  `~/qdb_database/backup.sql`, producing one intermediate file:
  `state/qdb_archive_import/normalized_quotes.jsonl` (9.8MB - small enough
  to copy anywhere, versus the 731MB raw dump).
- That one file is the only thing that needs to reach the production host.
  Nothing on prod ever touches MySQL or the raw dump.

## Procedure

1. Copy the normalized file to the production host:

   ```bash
   scp state/qdb_archive_import/normalized_quotes.jsonl prod-host:/tmp/qdb_normalized_quotes.jsonl
   ```

2. Recommended: tag the pre-import state for an easy rollback point:

   ```bash
   git -C $FORUM_REPOSITORY_ROOT tag pre-qdb-import
   ```

3. On the production host, from the deployed application directory, run
   the import with `--normalized-input` - this skips extraction/
   normalization entirely (no MySQL involved) and runs straight through
   writing the canonical post records, committing them in bounded batches
   (not one commit per quote, not one giant commit - 500 per commit by
   default), and rebuilding the read model exactly once:

   ```bash
   php scripts/qdb_archive_import_run.php \
     --normalized-input=/tmp/qdb_normalized_quotes.jsonl \
     --repository-root=$FORUM_REPOSITORY_ROOT \
     --database-path=$FORUM_DATABASE_PATH
   ```

4. If this vhost serves prebuilt static HTML artifacts, rebuild those too -
   otherwise the live site keeps serving pre-import pages even though the
   canonical repository and read model are already updated:

   ```bash
   ./v3 build-static
   ```

5. Verify:

   - `/api/read_model_status` reports `status=ready`
   - `/latest` on the qdb vhost shows imported quotes under their original
     numbers
   - spot-check a few known `quote_id`s' scores against the source data in
     `state/qdb_archive_import/normalized_quotes.jsonl`

## Timing and lock notes

The final rebuild briefly holds the exclusive read-model lock (about 2
seconds for the full set in local testing). A live write from a real
visitor during that window waits up to `FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS`
(default 5 seconds) rather than failing outright, but running during low
traffic is still the safer choice.

## Rollback

- `git -C $FORUM_REPOSITORY_ROOT reset --hard pre-qdb-import` (if tagged per
  step 2), then re-run the import (or just the read-model rebuild and
  static-artifact build, if the repository's canonical records are already
  back to the pre-import state).
- The read-model SQLite database needs no separate backup - it is fully
  regenerable from canonical records.

## Related

- `docs/plans/qdb_archive_importer_step1_solution_assessment.md` through
  `step4_implementation_summary.md` - the full design and implementation
  record for this feature.
- `docs/runbooks/production_deploy.md` - the general production deployment
  model, per-vhost environment variables, and static artifact rebuild
  procedure this runbook builds on.
