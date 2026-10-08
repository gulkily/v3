> **Feature plan:** [Step 1](./mitrapclub_events_experience_step1_solution_assessment.md) · [Step 2](./mitrapclub_events_experience_step2_feature_description.md) · [Step 3](./mitrapclub_events_experience_step3_development_plan.md) · [Step 4](./mitrapclub_events_experience_step4_implementation_summary.md)

## Stage 1 - Canonical record support for event fields
- Changes:
  - `src/ForumRewrite/Canonical/PostRecord.php`: added three nullable constructor properties (`$eventDate`, `$eventLocation`, `$eventLink`), appended at the end with `null` defaults (only one existing call site, in `PostRecordParser`, so this is purely additive).
  - `src/ForumRewrite/Canonical/PostRecordParser.php`:
    - Added `EVENT_HEADERS` (`Event-Date`, `Event-Location`, `Event-Link`) and rejects any of them on a reply record (`CanonicalRecordParseException`), mirroring the existing `Task-Status`-on-replies rule.
    - `Event-Date`, when present, is validated via a new `parseEventDate()` (regex `^\d{4}-\d{2}-\d{2}$` plus a `DateTimeImmutable` round-trip check — the same shape `parseCreatedAt()` already uses, narrowed to a calendar date).
    - `Event-Location`/`Event-Link` are passed through as plain optional strings; no extra validation at parse time (write-time normalization is Stage 2).
- Verification:
  - `./v3 test` — full suite: 834 run, 834 passed, 0 failed.
  - Parsed five hand-built record strings directly: no event headers → all three null; full trio present → all three parsed correctly; date-only → date parsed, location/link null; malformed `Event-Date: not-a-date` → throws with a clear message; `Event-Date` on a reply record → throws "Replies must not include typed root header: Event-Date". All five matched the Step 3 plan's expected cases exactly.
- Notes:
  - `GenericTextRecordParser` (the underlying header-line parser) is fully generic with no header allowlist, so no change was needed there — confirmed before writing this stage.
