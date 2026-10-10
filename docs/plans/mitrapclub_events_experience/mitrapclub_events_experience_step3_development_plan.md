> **Feature plan:** [Step 1](./mitrapclub_events_experience_step1_solution_assessment.md) · [Step 2](./mitrapclub_events_experience_step2_feature_description.md) · [Step 3](./mitrapclub_events_experience_step3_development_plan.md) · [Step 4](./mitrapclub_events_experience_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** a member fills in (or leaves blank) the new event fields when composing a thread on `mitrapclub`; a visitor then loads the board or that thread on any profile.
- **End-to-end outcome:** a thread whose root post carries a valid event date renders a structured event block (date, location if present, link if present) on both the board card and the thread page; a thread without one renders byte-identical to today, everywhere.
- **Required recovery:** a malformed event date submitted at compose time is rejected before any write happens (same 400/re-render pattern as every other compose validation failure) — never written, never half-applied.
- **Deployment/external verification:** not applicable.
- **Release condition:** `./v3 test` passes in full; byte-diffing rendered board/thread output for existing (non-event) fixture threads on all four profiles shows no change.

## Key Risks

- This spans four existing layers (canonical record parsing → write/validation → read model → rendering) — more than any prior `mitrapclub` cycle. Mitigation: stage strictly in that dependency order, with independent verification at each layer before the next depends on it.
- **High risk:** a malformed event date must never corrupt a thread or crash a page. Resolution: validate/normalize at compose time using the same strict-format-plus-`DateTimeImmutable`-round-trip check `PostRecordParser::parseCreatedAt()` already uses for `Created-At`, raising a `RuntimeException` caught by the existing compose-error path (`ComposeAndAccountKeyController::submitComposeThread()`) — fails closed (nothing written), not open. Early validation: Stage 2's explicit malformed-input case.
- **High risk:** thread rows are fetched by two separately-maintained explicit-column SQL queries that already exist as a duplication in this codebase — `ThreadRepository::fetchThreads()`/`byId()` (board, tags, Forte) and `Application::fetchThread()` (thread page, used at three call sites). Missing either one would make events show on the board but not the thread page, or vice versa. Mitigation: Stage 3 explicitly updates both; Stage 4 verification renders the same event thread on the board AND the thread page to catch a mismatch.
- The read model has two independent write paths that must agree — a full rebuild (`ReadModelBuilder::indexPosts()`) and the live incremental path (`IncrementalReadModelUpdater::insertThread()`, used right after `LocalWriteService::createThread()`). Mitigation: both already consume the same `PostRecord` DTO; adding the three fields to that one DTO (Stage 1) means both call sites read from a single source of truth rather than two independent implementations. Early validation: Stage 3 verifies both paths directly (one via rebuild, one via a live create).
- Event fields are scoped to the thread's root post only (per Step 2) — not stored on individual `posts` rows. Mitigation: Stage 3 only touches the `threads` table/queries; explicitly not the `posts` table, to avoid scope creep.

## Stage 1
- Goal: the canonical post-record format and its parser recognize three new optional, root-only header fields.
- Dependencies: none.
- Expected changes:
  - `src/ForumRewrite/Canonical/PostRecord.php`: add three nullable constructor properties, e.g. `?string $eventDate`, `?string $eventLocation`, `?string $eventLink`.
  - `src/ForumRewrite/Canonical/PostRecordParser.php`: read optional `Event-Date`/`Event-Location`/`Event-Link` headers; when a reply (`$isReply`) declares any of them, throw `CanonicalRecordParseException` (mirroring the existing `Task-Status`-on-replies rejection); when `Event-Date` is present on a root post, validate it with the same strict-format-plus-round-trip check as `parseCreatedAt()` (a narrower calendar-date format, e.g. `YYYY-MM-DD`), throwing on malformed input; `Event-Location`/`Event-Link` are passed through as plain optional strings with no extra validation at parse time (write-time normalization is Stage 2's job).
- Verification approach:
  - `./v3 test` full suite.
  - Parse a handful of hand-built record strings directly (no header; a valid header trio; `Event-Date` only; a malformed `Event-Date`; an `Event-Date` on a reply record) and confirm each produces the expected `PostRecord` values or the expected thrown exception.
- Risks or open questions:
  - Impact: picking too loose an `Event-Date` format now makes Stage 2's write-time validation and Stage 3's rendering inconsistent later.
  - Early warning / validation: the hand-built parse cases above, before Stage 2 begins.
  - Mitigation: reuse `parseCreatedAt()`'s exact validation shape (regex + `DateTimeImmutable` round-trip), just with a date-only pattern.
- Canonical components/API contracts touched: `PostRecord`, `PostRecordParser`.

## Stage 2
- Goal: a member can submit the three new optional fields when composing a thread; invalid input is rejected before any write.
- Dependencies: Stage 1 (parser must accept the headers before the write path can build/validate them).
- Expected changes:
  - `src/ForumRewrite/Write/LocalWriteService.php` (`createThread()` only — `createReply()` untouched, event fields are root-only): read optional `event_date`/`event_location`/`event_link` from `$input`; normalize the date with the Stage-1-matching validator (throw `RuntimeException` on malformed input, same style as existing field validation); normalize location/link via the existing `normalizeAuthoredLine()` helper; `buildThreadPostRecord()` includes the three headers only when non-empty.
  - `templates/partials/thread_compose_form.php`: three new optional inputs (date/location/link) alongside the existing `board_tags`/subject/body fields.
  - `src/ForumRewrite/Http/ComposeAndAccountKeyController.php`: `composeThread()`/`renderComposeThreadPage()`/`submitComposeThread()` thread the three new fields through on initial render and on validation-error re-render, matching the existing `board_tags`/`subject` echo-back pattern exactly.
- Verification approach:
  - `./v3 test` full suite.
  - Call `LocalWriteService::createThread()` directly against a scratch repository with: all three fields filled; none filled; date-only; a malformed date. Confirm the malformed case throws `RuntimeException` and writes no file; confirm the others write a canonical record with exactly the expected headers.
- Risks or open questions:
  - Impact: see Key Risks (malformed date must fail closed).
  - Early warning / validation: the malformed-date direct-call case above.
  - Mitigation: validate before any `writeFile()` call, same ordering `createThread()` already uses for its other fields.
- Canonical components/API contracts touched: `LocalWriteService::createThread()`, `thread_compose_form.php`, `ComposeAndAccountKeyController`.

## Stage 3
- Goal: the read model carries the three fields through to every thread row, via both the full-rebuild and incremental paths.
- Dependencies: Stage 1 (needs `PostRecord` to carry the fields) and Stage 2 (needs a way to actually produce records that have them, for end-to-end verification).
- Expected changes:
  - `src/ForumRewrite/ReadModel/ReadModelBuilder.php`: `threads` table gains three nullable `TEXT` columns (`event_date`, `event_location`, `event_link`); `indexPosts()`'s thread-summary initialization and its `INSERT INTO threads` populate them from the root post's `PostRecord`.
  - `src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php`: `insertThread()`'s `INSERT INTO threads` populates the same three columns from the same `PostRecord` (single source of truth with the rebuild path — see Key Risks).
  - `src/ForumRewrite/ReadModel/ThreadRepository.php`: `fetchThreads()` and `byId()`'s explicit column lists gain the three new columns.
  - `src/ForumRewrite/Application.php`: `fetchThread()`'s explicit column list (the separate thread-page query — see Key Risks) gains the same three columns.
- Verification approach:
  - `./v3 test` full suite.
  - Add one event thread and keep existing ordinary threads in the test fixture repository; run a full rebuild (`scripts/rebuild_read_model.php`) and query the event thread's row via `ThreadRepository::fetchThreads()`/`byId()` and via `Application::fetchThread()`'s path directly: all three fields present and correct; an ordinary thread's are null in both.
  - Call `LocalWriteService::createThread()` live (incremental path, no rebuild) with event fields; confirm the resulting thread row already has them without running a rebuild.
- Risks or open questions:
  - Impact / mitigation: both High risk items above (the two SELECT call sites; the two read-model write paths).
  - Early warning / validation: the rebuild-path and live-incremental-path checks above, run separately, before Stage 4 begins.
- Canonical components/API contracts touched: `ReadModelBuilder` (`threads` schema, `indexPosts()`), `IncrementalReadModelUpdater::insertThread()`, `ThreadRepository`, `Application::fetchThread()`.

## Stage 4
- Goal: a thread with event fields renders a distinct, structured event block on the board and the thread page; everything else is unchanged.
- Dependencies: Stage 3 (the fields must reach `$thread` before a template can render them).
- Expected changes:
  - New shared partial, e.g. `templates/partials/event_block.php`: renders date/location (if present)/link (if present) when `$thread['event_date']` is non-null; renders nothing otherwise.
  - `templates/partials/thread_card.php` and `templates/pages/thread.php`: both include the new partial, gated on the same condition, using the one thread row each already has in scope.
- Verification approach:
  - `./v3 test` full suite.
  - Byte-diff rendered board and thread-page HTML (via `FrontController`, same technique used in the structural-identity feature) for all four profiles against existing fixture threads (none with event fields): must be identical to before this stage.
  - Render the Stage 3 fixture's event thread's board card and thread page: confirm the event block appears with the correct date/location/link on **both** (closing the Key Risks duplication item for real, not just at the data layer).
- Risks or open questions:
  - Impact: a mismatch between the board card and thread page (only one updated) would look like a regression on the other.
  - Early warning / validation: the same-thread, both-surfaces check above.
  - Mitigation: one shared partial included from both, rather than two independent block implementations.
- Canonical components/API contracts touched: new `event_block.php` partial, `thread_card.php`, `templates/pages/thread.php`.

Waiting for "Approved Step 3" before branching and starting Step 4.
