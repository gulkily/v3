# Instance content import fixes

The `zenmemes.com` preview exposed closed-pipe notices and cascading legacy
record rejections. Implement each numbered item in its own commit and update
this checklist with its validation results.

- [ ] 1. Handle closed stdout pipes without PHP notices. Stop further output
  when a pager closes, while allowing imports, recovery, and cleanup to finish.
  Verify with a subprocess whose stdout reader closes early.
- [ ] 2. Preserve legacy creation timestamps without rewriting record or
  signature bytes. Recover dates from isolated source history, retain validated
  timestamp metadata in the destination, and use it on subsequent rebuilds and
  imports. Never install source Git settings or history in the destination.
  Verify legacy bootstrap dependencies, publication, repeat import, recovery,
  and rejection when reliable timestamp metadata is unavailable.
- [ ] 3. Report dependency root causes clearly. Distinguish absent dependencies
  from rejected or intentionally excluded records, group cascading failures,
  and retain per-record detail for review. Verify missing, excluded, invalid,
  signature, and transitive cases; repeat the live preview without mutating
  destination content.

Scheduling, web UI, and importing source authority remain outside this work.
