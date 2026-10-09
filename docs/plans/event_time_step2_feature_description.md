> **Feature plan:** [Step 1](./event_time_step1_solution_assessment.md) · [Step 2](./event_time_step2_feature_description.md) · [Step 3](./event_time_step3_development_plan.md) · [Step 4](./event_time_step4_implementation_summary.md)

## Problem

An event thread can only carry a date, location, and link — never a start time — and the compose form's date field is a plain text box with no native picker, so authors must type a date by hand in a specific format with no guidance.

## User Stories

- As a member composing an event thread, I want to specify a start time, so that attendees know exactly when to show up, not just which day.
- As a visitor viewing an event thread, I want to see the time alongside the date, so I know when the event happens at a glance.
- As a member composing any thread, I want native date/time pickers instead of typing a date by hand, so I can't accidentally submit a malformed date.
- As an operator of a site without event support enabled, I want this change to have zero effect on my site, so nothing about my compose flow or rendering regresses.

## Core Requirements

- Add one new optional `event_time` field to thread creation, following the exact pattern already established for `event_date`/`event_location`/`event_link` (canonical header, read-model column, validator, event-block rendering).
- Validate `event_time` strictly (24-hour `HH:MM`), using the same validation/error-message shape as the existing `normalizeEventDate`.
- Swap the compose form's event-date input from `type="text"` to native `type="date"`, and add a native `type="time"` input for `event_time` — both remain optional, in the one shared compose-form partial, so every profile with event support enabled gets the change (per Step 1's Option A).
- Render `event_time` in the event block next to the date when present; an event thread with no time renders exactly as it does today.
- No change to the already-shipped `event_date`/`event_location`/`event_link` fields or any existing event thread's stored data.

## Delivery Scope

- **Work type:** Application change (PHP write path, canonical record parser, read model, templates).
- No new feature flag — this extends the existing `FORUM_EVENT_SUPPORT_ENABLED`-gated framework; it is not independently gated.

## Completion Boundary

- **Normal entry:** Any member on a profile with event support enabled, composing a new thread via the shared compose form.
- **End-to-end outcome:** The thread can optionally carry a start time, entered through a native time picker, validated, stored, and rendered in the event block alongside the date.
- **Needed recovery:** An invalid time value is rejected inline with the same error pattern as the existing event fields; omitting the time changes nothing.
- **Release condition:** Ships on `main` behind the existing event-support flag; full test suite green; read-model schema version bumped (triggers the existing full-rebuild path — no migration script exists or is needed, per Step 1).

## Risks

- Adding a read-model column requires a full rebuild (no in-place migration path exists in this codebase). *Validate:* confirm the existing schema-version-bump → full-rebuild path (already used for every prior column addition, including `event_date` itself) still completes cleanly. *Mitigate:* follow that exact precedent; no new mechanism needed.
- Switching the date input from `type="text"` to native `type="date"` changes what a browser submits (always `YYYY-MM-DD` in the input's `value`) and how already-queued prefill values (from the existing GET-prefill path) are displayed. *Validate:* check the GET-prefill path still round-trips correctly once the input type changes, early in Step 3/4. *Mitigate:* the existing validator already expects exactly `YYYY-MM-DD`, which is what a native date input submits, so no format translation should be needed.
- Native `type="time"` submits 24-hour `HH:MM`, but some browsers render a 12-hour AM/PM picker UI regardless of the submitted format. *Validate:* manually check rendered time values round-trip correctly in at least one real browser during Step 4. *Mitigate:* validate strictly against `HH:MM` server-side regardless of what the picker UI displays, consistent with how `event_date` is already validated independent of its input's display.

## Shared Component Inventory

- **Compose form:** `templates/partials/thread_compose_form.php` — the single shared partial rendering `event_date`/`event_location`/`event_link` inputs (included from 4 places, per Cycle 8's investigation). Extend in place: swap the date input's type, add the new time input; no new partial.
- **Event display:** `templates/partials/event_block.php` — the single shared event-rendering partial. Extend to render `event_time` next to `event_date`; no new partial.
- **Write path:** `LocalWriteService::createThread()`/`buildThreadPostRecord()` — the single thread-creation write path. Extend with an `event_time` validator (mirroring `normalizeEventDate`) and header emission.
- **Canonical parsing:** `PostRecordParser` — the single canonical post-record parser. Extend `EVENT_HEADERS` and the typed-extraction logic, mirroring the existing `Event-Date` handling exactly.
- **Read model:** `ReadModelSchema` (table definition), `ReadModelBuilder` (full rebuild), `IncrementalReadModelUpdater` (incremental write path) — all three already have a parallel site for `event_date`/`event_location`/`event_link`; extend each the same way for `event_time`. `ReadModelMetadata::SCHEMA_VERSION` bumps to trigger the existing full-rebuild path.

## Simple User Flow

1. A member on a profile with event support enabled opens the thread composer.
2. They optionally pick an event date and/or time using native pickers, plus location/link as today.
3. They submit; any provided time is validated and stored alongside the rest of the event fields.
4. Anyone viewing the thread (board card or thread page) sees the time rendered next to the date in the event block.
5. If no time was given, the event block renders exactly as it does today (date/location/link only).

## Success Criteria

- A newly composed event thread with a time renders that time in the event block on both the board and thread pages.
- A newly composed event thread without a time renders identically to today's event block — no regression.
- An invalid time value is rejected with a clear inline error, matching the existing event-field validation pattern.
- A profile without event support enabled shows no change at all — same compose form, same (absent) event rendering as today.

Waiting for "Approved Step 2" before drafting Step 3.
