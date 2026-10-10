> **Feature plan:** [Step 1](./event_time_step1_solution_assessment.md) · [Step 2](./event_time_step2_feature_description.md) · [Step 3](./event_time_step3_development_plan.md) · [Step 4](./event_time_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** a member on a profile with event support enabled fills in (or leaves blank) the new time field when composing a thread via the shared compose form.
- **End-to-end outcome:** a thread whose root post carries a valid event time renders that time next to its date in the event block, on both the board card and the thread page, for any profile; a thread without one renders byte-identical to today.
- **Required recovery:** a malformed event time is rejected before any write happens — never written, never half-applied — same fail-closed pattern as the existing `event_date` validation.
- **Deployment/external verification:** not applicable.
- **Release condition:** full test suite (`php tests/run.php`) passes; rendered board/thread output for existing fixture threads (none with a time) is unchanged on every profile.

## Key Risks

- **High risk:** a malformed time must never corrupt a thread or crash a page. Mitigation: validate/normalize at compose time with the same strict-format-plus-round-trip shape `normalizeEventDate` already uses, raising the same `RuntimeException` the existing compose-error path already catches. Early validation: Stage 2's malformed-input case.
- Three separate SELECT call sites already carry `event_date`/`event_location`/`event_link` explicitly (`ThreadRepository::fetchThreads()`, `ThreadRepository::byId()`, `Application::fetchThread()`) — confirmed current via direct grep, not assumption. Missing any one of the three would make the time show on some surfaces but not others. Mitigation: Stage 3 updates all three explicitly; Stage 4 verification renders the same event thread on the board AND the thread page to catch a mismatch.
- Two independent read-model write paths must agree: the full rebuild (`ReadModelBuilder`) and the live incremental path (`IncrementalReadModelUpdater`). Mitigation: both already consume the same `PostRecord` DTO; adding the field to that one DTO (Stage 1) means both read from a single source of truth. Early validation: Stage 3 verifies both paths directly (one via rebuild, one via a live create).
- No in-place schema migration exists in this codebase (confirmed in Step 1); a new column requires the existing schema-version-bump → full-rebuild path. Mitigation: follow that exact precedent (already used for `event_date` itself); no new mechanism needed.

## Stage 1
- Goal: the canonical post-record format and its parser recognize one new optional, root-only `Event-Time` header.
- Dependencies: none.
- Expected changes:
  - `src/ForumRewrite/Canonical/PostRecord.php`: add one nullable trailing constructor property, `?string $eventTime = null`, after `$eventLink`.
  - `src/ForumRewrite/Canonical/PostRecordParser.php`: add `Event-Time` to `EVENT_HEADERS` (rejected on replies, same as the other three); parse it with a new `parseEventTime` mirroring `parseEventDate`'s shape — strict `HH:MM` (24-hour) regex plus a round-trip check; pass the result into the `PostRecord` constructor as the new trailing argument.
- Verification approach:
  - `php tests/run.php` full suite.
  - Parse hand-built record strings directly: no header; `Event-Date` + `Event-Time` both valid; a malformed `Event-Time` (e.g. `25:99`, `7:30pm`); `Event-Time` present on a reply record. Confirm each produces the expected `PostRecord` value or the expected thrown exception.
- Risks or open questions:
  - Impact: a loosely-defined `Event-Time` format now makes Stage 2's write-time validation and Stage 4's rendering inconsistent later.
  - Early warning / validation: the hand-built parse cases above, before Stage 2 begins.
  - Mitigation: reuse `parseEventDate`'s exact validation shape (regex + round-trip), with a 24-hour `HH:MM` pattern.
- Canonical components/API contracts touched: `PostRecord`, `PostRecordParser`.

## Stage 2
- Goal: a member can submit the new optional time field when composing a thread, via a native picker; invalid input is rejected before any write.
- Dependencies: Stage 1 (parser must accept the header before the write path can build/validate it).
- Expected changes:
  - `src/ForumRewrite/Write/LocalWriteService.php`: new `normalizeEventTime` validator mirroring `normalizeEventDate`; `createThread()` reads optional `event_time` from `$input`, normalizes it, and passes it to `buildThreadPostRecord()`; `buildThreadPostRecord()` emits `Event-Time:` immediately after `Event-Date:` (only when non-empty, same conditional-emission pattern as the other event headers).
  - `templates/partials/thread_compose_form.php`: swap the existing event-date input from `type="text"` to native `type="date"`; add one new native `type="time"` input for `event_time`, both optional, same label style as the existing fields.
  - `src/ForumRewrite/Http/ComposeAndAccountKeyController.php`: both the GET-prefill call site (`composeThread()`) and the validation-error re-render call site (`submitComposeThread()`) thread `event_time` through the same way they already thread `event_date`, and `renderComposeThreadPage()` forwards it as an `eventTime` template var.
- Verification approach:
  - `php tests/run.php` full suite.
  - Call `LocalWriteService::createThread()` directly against a scratch repository: date+time both filled; date only; malformed time. Confirm the malformed case throws `RuntimeException` and writes no file; confirm the others write a canonical record with exactly the expected headers.
  - Render the compose page with `event_time` on the query string (GET-prefill path) and confirm the native time input shows the correct value.
- Risks or open questions:
  - Impact: see Key Risks (malformed time must fail closed).
  - Early warning / validation: the malformed-time direct-call case above.
  - Mitigation: validate before any `writeFile()` call, same ordering `createThread()` already uses for its other fields.
- Canonical components/API contracts touched: `LocalWriteService::createThread()`, `thread_compose_form.php`, `ComposeAndAccountKeyController`.

## Stage 3
- Goal: the read model carries the new field through to every thread row, via both the full-rebuild and incremental paths, and every existing read site returns it.
- Dependencies: Stage 1 (needs `PostRecord` to carry the field) and Stage 2 (needs a way to produce records that have it, for end-to-end verification).
- Expected changes:
  - `src/ForumRewrite/ReadModel/ReadModelSchema.php`: `threads` table gains one nullable `TEXT` column, `event_time`; `ReadModelMetadata::SCHEMA_VERSION` bumped to trigger the existing full-rebuild path.
  - `src/ForumRewrite/ReadModel/ReadModelBuilder.php`: the in-memory thread-summary builder and the `INSERT INTO threads` column list/bindings both gain `event_time`, populated from the root post's `PostRecord`.
  - `src/ForumRewrite/ReadModel/IncrementalReadModelUpdater.php`: its `INSERT INTO threads` column list/bindings gain `event_time`, from the same `PostRecord` (single source of truth with the rebuild path).
  - `src/ForumRewrite/ReadModel/ThreadRepository.php` (`fetchThreads()` and `byId()`) and `src/ForumRewrite/Application.php` (`fetchThread()`): all three explicit `SELECT` column lists gain `threads.event_time`.
- Verification approach:
  - `php tests/run.php` full suite.
  - Add one fixture thread with date+time and keep an existing date-only event thread and ordinary threads in a scratch repository; run a full rebuild and query the new thread's row via `ThreadRepository::fetchThreads()`/`byId()` and via `Application::fetchThread()`'s path: `event_time` present and correct on all three; the date-only thread's `event_time` is null on all three.
  - Call `LocalWriteService::createThread()` live (incremental path, no rebuild) with a time; confirm the resulting thread row already has it without running a rebuild.
- Risks or open questions:
  - Impact / mitigation: both Key Risks items above (the three SELECT call sites; the two read-model write paths).
  - Early warning / validation: the rebuild-path and live-incremental-path checks above, run separately, before Stage 4 begins.
- Canonical components/API contracts touched: `ReadModelSchema` (`threads` table), `ReadModelBuilder`, `IncrementalReadModelUpdater`, `ThreadRepository`, `Application::fetchThread()`.

## Stage 4
- Goal: a thread with an event time renders it next to the date in the event block, on the board and the thread page, for any profile; everything else is unchanged; full end-to-end verification closes out the feature.
- Dependencies: Stage 3 (the field must reach `$thread` before a template can render it).
- Expected changes:
  - `templates/partials/event_block.php`: renders `event_time` immediately after `event_date` when present (e.g. `📅 2026-11-14 at 19:00`); renders exactly as today when absent.
- Verification approach:
  - `php tests/run.php` full suite.
  - Render the Stage 3 fixture's time-bearing event thread's board card and thread page: confirm the time appears correctly on both.
  - Render an existing date-only event thread (no time) and an ordinary non-event thread, on both surfaces, on at least two profiles (`mitrapclub` and one other, e.g. `qdb`): byte-identical to pre-feature output, confirming zero regression anywhere event support isn't the subject of the test.
- Risks or open questions:
  - Impact: a mismatch between the board card and thread page (only one updated) would look like a regression on the other.
  - Early warning / validation: the same-thread, both-surfaces check above.
  - Mitigation: one shared partial already included from both (`event_block.php`), rather than two independent block implementations.
- Canonical components/API contracts touched: `event_block.php`.

Waiting for "Approved Step 3" before branching and starting Step 4.
