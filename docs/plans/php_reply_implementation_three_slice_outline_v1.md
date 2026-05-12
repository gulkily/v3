# PHP Reply Implementation Three-Slice Outline V1

This document outlines a clean three-slice plan for implementing replies in the PHP forum rewrite.

## Goal

Implement replies in a way that progresses from canonical correctness to user-facing reads to production-safe behavior.

## Slice 1: Canonical + Write

Focus:

- define the reply record shape
- validate replies correctly
- add the basic write path

Checklist:

- define reply semantics in `docs/specs/canonical_post_record_v1.md`
- require `Post-ID`, `Board-Tags`, `Thread-ID`, and `Parent-ID` for replies
- ensure replies do not carry root-only metadata
- update `src/ForumRewrite/Canonical/PostRecordParser.php`
- add reply creation to `src/ForumRewrite/Write/LocalWriteService.php`
- validate that `parent_id` belongs to the target thread
- add `POST /api/create_reply` handling in `src/ForumRewrite/Application.php`
- add parser and write smoke coverage

Expected outcome:

- the app can accept a valid reply write request
- canonical reply files are written correctly
- invalid thread/parent combinations are rejected

## Slice 2: Read + Render

Focus:

- make replies visible in the read model and UI

Checklist:

- index replies into the `posts` read model in `src/ForumRewrite/ReadModel/ReadModelBuilder.php`
- ensure thread pages load root post plus replies in stable order
- ensure post permalink reads work for replies
- add compose reply route and form in `src/ForumRewrite/Application.php`
- render replies on thread pages
- add local app smoke coverage for reply reads and compose reply UI

Expected outcome:

- reply content appears on thread pages
- reply permalinks resolve
- users can access a reply form and submit a reply through the PHP UI

## Slice 3: Production Hardening

Focus:

- make reply writes production-safe and operationally correct

Checklist:

- commit reply writes to git in the canonical repository
- refresh the read model immediately after successful reply writes
- invalidate affected thread/post/public HTML artifacts
- include author identity linkage if browser identity flow exists
- ensure stale-marker behavior works if post-commit refresh fails
- ensure locking covers reply writes and rebuild interactions
- add write smoke tests for success, git failure, and refresh-failure cases

Expected outcome:

- reply writes are immediately visible
- failure modes are deterministic
- concurrent write/rebuild behavior remains coherent
- the reply flow fits the same production contract as thread creation

## Summary

This three-slice approach separates:

1. canonical/write correctness
2. read/render visibility
3. production-safe orchestration

That keeps the work reviewable while still reaching a deployment-ready reply implementation.
