# Operator command progress

Purpose: Show what a long operator command is doing while it runs, so a working command can be told from a stuck one.
Scenario: `delete-record` and `purge-history` print one stderr line per step, stamped with seconds since the command started (`[  0.21s] Committing removal`), including lock waits and the phases of a full rebuild. `delete-record` ends with `Deleted <id> in 0.21s`; `-q`/`--quiet` keeps only errors and that line, and `--json` prints no step lines so stdout stays machine-readable.
Sources: [CommandProgress](../../src/ForumRewrite/Support/CommandProgress.php), [delete-record](../../scripts/delete_record.php), [delete-thread](../../scripts/delete_thread.php), [purge-history](../../scripts/purge_history.php), [rebuild progress](../../src/ForumRewrite/ReadModel/ReadModelBuilder.php) (`progressReporter`), [lock wait callback](../../src/ForumRewrite/Support/ExecutionLock.php).
Import: `import-instance` passes every line through [ImportOutputFilter](../../src/ForumRewrite/Import/ImportOutputFilter.php). Counters print at most once a second plus their last value, read-model rebuild steps print only with `--verbose` or after five quiet seconds, and `Progress:` lines for Tools → Import print only with `--progress-lines`, which [the import task](../../src/ForumRewrite/Import/InstanceImportTask.php) passes.
Known limitation: `purge-history` keeps its own one-decimal line format; other commands do not use this yet.

*— Claude · 2026-11-04 UTC*

*— Claude · 2026-11-17 UTC (import line) · Model: Claude Opus 5.5*
