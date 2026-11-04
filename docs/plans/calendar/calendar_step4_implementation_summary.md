> **Feature plan:** [Step 1](./calendar_step1_solution_assessment.md) · [Step 2](./calendar_step2_feature_description.md) · [Step 3](./calendar_step3_development_plan.md) · [Step 4](./calendar_step4_implementation_summary.md) · [Addendum](./calendar_addendum_conversation_notes.md)

Debug mode: enabled (inherited from Step 3; log: [calendar_debug_log.md](./calendar_debug_log.md))

Effort study: `railspike-effort` / `v3/calendar`; [host ledger](../effort_estimation_trial.md#cycle-v3calendar). Standing P2 enrollment.

Extended estimates: enabled (inherited)

Status: Step 3 approved 2026-11-04 with the schema bump corrected to 48 → 49. Branch `feat/calendar`; planning commit `7af83949`. The maintainer's addendum (`f729d1b8`) was merged in `dacb71b8` and filed beside the plans; it confirms the approved decisions and changes no stage scope.

**Cycle estimate: 0.90**

Step 4 entry projection, 2026-11-04 UTC: Step 3 whole-cycle 0.90 reaffirmed, same scope v3/A, original baseline `f41f656936e740809242a5d8e1c646068afddc28`, R1/P2. Rater Claude (claude-opus-5-5), exposed to the Step 3 estimate and the plans; copying the stage originals below is not a new independent rating. The schema-version correction does not change any stage's scope.

Stage-work originals from Step 3 (non-additive): 1 0.08 · 2 0.12 · 3 0.12 · 4 0.12 · 5 0.20 · 6 0.14 · 7 0.08 · 8 0.12.

Enhancement requests: none.

## Stage 1 - Event-End-Date header in the record format

**Original estimate: 0.08**

- Changes:
  - `src/ForumRewrite/Canonical/PostRecord.php`: trailing nullable `eventEndDate` after `bodyFormat`.
  - `src/ForumRewrite/Canonical/PostRecordParser.php`:
    - `Event-End-Date` added to `EVENT_HEADERS`, so replies carrying it are rejected like the other event headers.
    - Parsed with the existing date check (`parseEventDate` now takes the header name for its messages; `Event-Date` messages unchanged).
    - Rejects an end without `Event-Date` and an end before `Event-Date`; an end equal to the start is accepted.
  - `docs/specs/canonical_post_record_v1.md`: new Event Headers section covering all five headers, their order, and how older parsers read a span.
  - `tests/EventEndDateParserTest.php`: absent, no event headers, valid span, equal days, end before start, end without start, malformed end, end on a reply, unchanged start-date message.
- Verification:
  - Passed: before the change, today's `PostRecordParser` parsed a root carrying `Event-End-Date` without error and returned it as a one-day event on `Event-Date` (`GenericTextRecordParser` keeps unknown headers and `PostRecordParser` ignores them). The spec's compatibility sentence rests on this.
  - Passed: `./v3 test --smoke EventEndDateParserTest`, 423 run, 423 passed (smoke set plus the new class). Full suite not run, per `AGENTS.md`.
  - Inapplicable: UI, read model, deployment. No reader of `eventEndDate` exists until Stage 3.
- Notes:
  - Requiring `Event-Date` for an end date is not spelled out in Step 3; it follows from "absent means one day" and keeps a stray end date from describing nothing.
  - `SignedPostBundleValidator` rebuilds client-signed records from a fixed header list without event headers, so client-built signed records cannot carry any event header today. Stage 2 checks how the signed prepare path carries `event_date` before adding the end date.
- Estimate details: original scope unchanged; no revision. Retrospective stage-work assessment: 0.08 (as planned; the compatibility question resolved with one check).

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 2 - Until field in the composer and both write paths

**Original estimate: 0.12**

- Changes:
  - `src/ForumRewrite/Write/LocalWriteService.php`:
    - Plain and prepared thread flows read `event_end_date` through `normalizeEventEndDate()`, which reuses `normalizeEventDate()` (now named per field) and refuses an end without `event_date` or before it.
    - An end equal to the start is dropped, so a one-day event is recorded the same way whether or not the Until field was filled.
    - `buildThreadPostRecord()` emits `Event-End-Date` right after `Event-Date`, matching the spec order from Stage 1.
    - `assertEventSupportAllowsInput()` gates `event_end_date` with the other event fields.
  - `templates/partials/composer.php`: Until date input after Date; the disclosure summary shows `start – end` for a span.
  - `public/assets/compose_disclosure.js`: same span text while typing.
  - `public/assets/browser_signing.js`: `collectThreadSubmitFields()` sends `event_end_date`, so signed posts reach the prepared flow with it.
  - `src/ForumRewrite/Http/ComposeAndAccountKeyController.php`: query prefill and error re-render carry `event_end_date`.
  - `tests/WriteApiSmokeTest.php`: two tests (write order, same-day drop, three refusals with no file written, flag-off gate; compose prefill, `/api/prepare_thread` record, JavaScript-off error re-render keeping body and end date).
  - `docs/feedback/items/PF-20261104-B10.txt`: dated replay note.
- Verification:
  - Passed: `./v3 test --smoke` plus the two new tests, `testEventFieldsRequireEventSupportBeforeWriting`, `testPrepareThreadWithEventFieldsRoundTripsThroughFinalizeAndReadModel`, `testComposersOfferTheMarkdownBoxAndTheUnsignedFormPathHonoursIt` and `EventEndDateParserTest`: 428 run, 428 passed. Full suite not run, per `AGENTS.md`.
  - Passed: `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/compose_disclosure.mjs`.
  - PF-20261104-B10 replayed, not reproduced: a scratch Playwright script against the signed-reaction browser fixture with event support on opened `/compose/thread` at 390px and 1280px, empty and prefilled (server-rendered open). The Event disclosure opened, closed and reopened on summary clicks on the original markup and again with the Until field. No page errors. Item left open; the reporter's device and browser are unknown. No `Feedback:` trailer, since nothing here addresses it.
  - Inapplicable this stage: read model and display (Stage 3), themes and layout matrix (Stage 8).
- Notes:
  - Step 3 lists agent API field documentation here, but no document lists the event inputs yet; `docs/reference/agent_api.md` gains them in Stage 7, which already names it.
  - The signed path needed no validator change: the browser posts the fields to `/api/prepare_thread`, the server builds the record, and the client signs those bytes.
- Estimate details: original scope unchanged; no revision. Retrospective stage-work assessment: 0.12.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
