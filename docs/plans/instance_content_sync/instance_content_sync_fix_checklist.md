# Instance content import fixes

The `zenmemes.com` preview exposed closed-pipe notices and cascading legacy
record rejections. Implement each numbered item in its own commit and update
this checklist with its validation results.

- [x] 1. Handle closed stdout pipes without PHP notices. Stop further output
  when a pager closes, while allowing imports, recovery, and cleanup to finish.
  Verified with a subprocess whose stdout reader closes early: import and
  publication finish, recovery journal clears, and stderr stays empty.
  `php tests/run.php ImportInstanceCommandTest`: 6/6 passed.
- [x] 2. Preserve legacy creation timestamps without rewriting record or
  signature bytes. Recover dates from isolated source history, retain validated
  timestamp metadata in the destination, and use it on subsequent rebuilds and
  imports. Never install source Git settings or history in the destination.
  Verify legacy bootstrap dependencies, publication, repeat import, recovery,
  and rejection when reliable timestamp metadata is unavailable.
  Validation: 87/87 focused import, canonical reader, publication, read-model,
  private-site, and offline tests passed. Live preview recovered all 59 legacy
  dates; invalid entries fell from 893 to 15, with 922 importable files and four
  local conflicts exposed. No destination content was changed.
- [x] 3. Report dependency root causes clearly. Distinguish absent dependencies
  from rejected or intentionally excluded records, group cascading failures,
  and retain per-record detail for review. Verify missing, excluded, invalid,
  signature, and transitive cases; repeat the live preview without mutating
  destination content.
  Validation: 90/90 focused regression checks passed on the final run, PHP lint,
  shell syntax, and diff whitespace checks passed. One earlier CLI recovery test
  returned an unexpected partial status, then passed alone and in the full rerun;
  its failure diagnostic now preserves command output for future investigation.
  Final live preview: 922 importable files, 1,376 duplicates, four conflicts,
  three blocked records, zero unsupported files, 158 intentional exclusions,
  and no PHP notices/warnings. The conflicts concern local `root-001`/`reply-001`
  content and dates; two replies depend on excluded authority posts, and one
  further record depends on conflicting `root-001`. Default output groups these
  causes; `--verbose` retains per-file detail. No destination content was changed.

- [x] 4. Show archive preparation progress. Report validation entry/byte counts,
  gzip integrity heartbeats, extraction file/byte totals, and legacy timestamp
  scan/recovery counts with per-phase elapsed time. Throttle intermediate output
  to roughly once per second, including during individual history queries.
  Validation: 13/13 focused archive, legacy, and CLI tests passed (including
  closed-pipe handling); PHP lint and diff checks passed. The live preview kept
  the same counts and produced no PHP warnings. Validation/integrity/extraction
  took about 2.5 seconds; legacy history lookup took 33.6 seconds for 59 dates,
  with visible progress throughout. No destination content was changed.

Scheduling, web UI, and importing source authority remain outside this work.
