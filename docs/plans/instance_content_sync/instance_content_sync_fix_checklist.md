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
- [ ] 3. Report dependency root causes clearly. Distinguish absent dependencies
  from rejected or intentionally excluded records, group cascading failures,
  and retain per-record detail for review. Verify missing, excluded, invalid,
  signature, and transitive cases; repeat the live preview without mutating
  destination content.

Scheduling, web UI, and importing source authority remain outside this work.
