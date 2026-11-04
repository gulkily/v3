> **Feature plan:** [Step 1](./calendar_step1_solution_assessment.md) · [Step 2](./calendar_step2_feature_description.md) · [Step 3](./calendar_step3_development_plan.md) · [Step 4](./calendar_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/calendar`; [host ledger](./effort_estimation_trial.md#cycle-v3calendar). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 v3 approved; Step 3 proposed; implementation unapproved. Repository update after the merge of `main` on 2026-11-04: work tracking was removed upstream; Stage 4 now follows the title directive precedent, Stage 2 replays feedback item PF-20261104-B10, and no `Work:` trailer applies. Stage scopes and estimates are unchanged. Steps 1–3, the debug log and the ledger were committed to `main` on 2026-11-04 at the maintainer's explicit request, before Step 3 approval; the mockup file stays uncommitted. This does not approve Step 3 or implementation.

**Cycle estimate: 0.90**

Mockup: [calendar_mockups.html](./calendar_mockups.html). Conversation notes for the implementer: [addendum](./calendar_addendum_conversation_notes.md). Precedent: the [event time cycle](./event_time/event_time_step4_implementation_summary.md) added one event header end to end; Stages 1–3 follow its shape.

## Completion Contract

- Normal entry: a member opens `/calendar` or `/calendar/<tag>` (the couch calendar is `/calendar/couches`), moves between months, opens a day, and posts through the shared composer with the date fixed, an optional last day, and the tag preset on a tag calendar. An operator replies `event: confirmed` or `event: declined`, or posts the event with the line.
- End-to-end outcome: the day pages and the grid list the event on every day of its span under the author's username; confirmed days are marked; declined events leave the grid but stay in their thread; the thread page shows the state under the title.
- Required recovery: a refused post keeps the draft with the reason, as the composer does today; event support off shows operators the flag link and nothing else; a read model from the prior schema version is rebuilt on startup before the calendar query runs; records without the new header parse and render exactly as before.
- Deployment/external verification: deployed to the maintainer's instance by the usual pull; the invite-link path is replayed with an unapproved then approved host; recorded as done or explicitly not done.
- Release condition: Step 2 success criteria met locally with JavaScript on and off, phone and desktop widths, text and icon themes, plus the production replay above.

## Key Risks

- **High risk:** format and schema change. Impact: a wrong header breaks parsing of every post; a missed read site shows no span. Early validation: Stage 1 confirms how the current parser treats an unknown root header (older releases must keep reading new records as single-day events) and Stage 3 greps every `event_time` site to mirror it. Mitigation: optional header, additive column, prior-version rebuild test, byte-identical rendering of old records asserted.
- **High risk:** directive authority. Impact: a member confirming their own offer corrupts the grid. Early validation: Stage 4 test that a non-operator's and a quoted line change nothing, before any page uses the state. Mitigation: operator check as `TitleDirectiveAuthority` makes it (`RetractionAuthority::currentOperator`) plus approved, unretired and verified attribution of the line's author.
- Day-state correctness across spans and month boundaries. Impact: wrong empty/open/confirmed marks. Early validation: Stage 5 fixtures with overlapping spans, a span crossing a month edge, and a confirmed plus declined pair. Mitigation: one derivation function with table-driven tests.
- Grid density and overflow. Impact: unreadable cells on phones. Early validation: Stage 8 layout checks with a five-event day. Mitigation: cap entries per cell with a link to the day page; table scrolls in its container.
- Composer placed outside `/compose/thread`. Impact: signing, drafts or Outbox scripts assume the compose page. Early validation: Stage 6 posts from a day page with JavaScript on and off. Mitigation: render the day page with the same scripts and hidden fields the compose page uses.

## Stage 1

**Estimate: 0.08**

- Goal: a thread root may carry `Event-End-Date`; replies may not; old records are unchanged.
- Dependencies: none.
- Expected changes: `PostRecord` gains a trailing nullable `eventEndDate`; `PostRecordParser` adds the header to `EVENT_HEADERS`, parses it with the existing date check, and rejects an end before the start; `docs/specs/canonical_post_record_v1.md` gains the missing event header section covering all five headers.
- Verification approach: parser cases for absent, valid span, end before start, end on a reply; confirm by test how an unknown root header is treated today so the compatibility statement in the spec is true.
- Estimate details: driver: mirroring the event-time stage; uncertainty: unknown-header behaviour of older parsers.
- Risks or open questions: see Key Risks (format).
- Canonical components/API contracts touched: canonical post record V1, `PostRecordParser`, `PostRecord`.

## Stage 2

**Estimate: 0.12**

- Goal: the composer and both write paths accept an optional last day.
- Dependencies: Stage 1.
- Expected changes: `LocalWriteService` reads, normalizes and gates `event_end_date` in the plain and prepared thread flows, refuses end before start, and emits the header after `Event-Date`; the composer's Event disclosure gains an Until date field and the disclosure summary includes it; `browser_signing.js` sends the field; `ComposeAndAccountKeyController` prefills it from the query and on error re-render; agent API field documentation.
- Verification approach: write tests for span, single day, end before start, flag off; fetch `/compose/thread?event_date=…&event_end_date=…` and confirm both inputs are prefilled; signed prepare path carries the field; replay feedback item PF-20261104-B10 (the Event disclosure cannot be closed again) in the browser before and after adding the field, and record the result in the item; fix it only if the cause is in the lines this stage changes, otherwise leave the item as replayed.
- Estimate details: driver: four touch points per field, as event time needed; uncertainty: low.
- Risks or open questions: none beyond Stage 1.
- Canonical components/API contracts touched: `/compose/thread`, `/api/prepare_thread`, `/api/create_thread` input fields, shared composer partial.

## Stage 3

**Estimate: 0.12**

- Goal: the read model stores the span and the event block shows it.
- Dependencies: Stage 1.
- Expected changes: `threads.event_end_date` nullable column; `ReadModelMetadata::SCHEMA_VERSION` 47 → 48; builder, incremental updater, `ThreadRepository` and `Application::fetchThread` select lists; `docs/specs/read_model_schema_v1.md`; `event_block.php` renders "date to end date" when present; a regression test seeded at version 47 proving startup rebuilds before the new column is read.
- Verification approach: incremental and full rebuild agree for span and single-day threads; old fixture thread renders byte-identical; `./v3 rebuild`.
- Estimate details: driver: contributing-rule obligations for a column; uncertainty: low.
- Risks or open questions: see Key Risks (schema).
- Canonical components/API contracts touched: read-model schema V1, `ReadModelSchema`, `ReadModelBuilder`, `IncrementalReadModelUpdater`, `event_block.php`.

## Stage 4

**Estimate: 0.12**

- Goal: an event thread has one derived state set by operator `event:` lines, shown on the thread page.
- Dependencies: none (Stage 3 for the span, not for the state).
- Expected changes: `EventStatusDirective::extract(string $body): ?array` for `event: confirmed|declined` with the quoted-line rule; `EventStatus::forThreads(PDO, list<string>): array` reading root and replies, latest valid line from an active operator with verified attribution, approved and unretired; `ThreadAndPostPageController` passes the state for threads with an event date; `thread.php` shows an `Event:` line under the title.
- Verification approach: tests for latest wins, root line, non-operator ignored, quoted ignored, retracted post ignored, thread page markup.
- Estimate details: driver: sibling of `TitleDirective` with read-time derivation and a stricter author check; uncertainty: operator check needs the repository root alongside the PDO.
- Risks or open questions: see Key Risks (authority).
- Canonical components/API contracts touched: thread page template, `TitleDirective` scanning rules, `RetractionAuthority::currentOperator`.

## Stage 5

**Estimate: 0.20**

- Goal: `/calendar` and `/calendar/<tag>` show month grids of events with navigation.
- Dependencies: Stages 3 and 4.
- Expected changes: `CalendarPageController` with `grid(?string $tag, ?string $month): string`; `CalendarQuery::eventsBetween(PDO, string $from, string $to, ?string $tag): array` over the threads table, tag matched as `TagPage` does; `CalendarDays::derive(events, states): array` giving each day its entries and empty/open/confirmed mark; `calendar.php` rendering two months as tables with previous and next links (`?month=YYYY-MM`), a link between the tag view and all events, and entries capped per cell with a link to the day; routes registered as application routes; event support off shows operators the flag link; navigation entry explicitly not added.
- Verification approach: route tests with fixtures for single day, span, span across a month edge, overlapping confirmed and declined, declined hidden, tag filter by board tag and by label; members-only gate sends an unapproved viewer to the Lobby; identical markup for two approved viewers.
- Estimate details: driver: new page, query and derivation; uncertainty: month arithmetic and timezone (use the instance's date, UTC, consistently).
- Risks or open questions: see Key Risks (day state, density).
- Canonical components/API contracts touched: `TagPage` membership query, `membersOnlyRequestAllowed` route list, page template registry.

## Stage 6

**Estimate: 0.14**

- Goal: a day page lists that day's events and posts a new one with the date fixed and the tag preset.
- Dependencies: Stages 2 and 5.
- Expected changes: `CalendarPageController::day(?string $tag, string $date): string` at `/calendar/<date>` and `/calendar/<tag>/<date>`; `calendar_day.php` listing entries with author, span, place and state, then the shared composer rendered as on `/compose/thread` with `eventDate` fixed, `eventEndDate` optional and `boardTags` preset to the tag; submission uses the existing plain and signed routes; a validation error re-renders through the existing compose error path with the draft kept.
- Verification approach: JavaScript-off form post creates the thread with the right headers and redirects to it; signed post through the browser test harness; the day page shows the new event; error re-render keeps body and date.
- Estimate details: driver: composer placement and script set; uncertainty: Outbox and signing script assumptions about the page.
- Risks or open questions: see Key Risks (composer placement).
- Canonical components/API contracts touched: shared composer partial and its hidden fields, compose scripts, `/compose/thread`.

## Stage 7

**Estimate: 0.08**

- Goal: people and agents can find and use the calendar without reading code.
- Dependencies: Stages 5 and 6.
- Expected changes: `docs/reference/calendar.md` (pages, tag calendars, span, directive, flag, access); concept entries `docs/concepts/calendar.md` and `docs/concepts/post_event_metadata.md`; README docs index; `docs/reference/agent_api.md` mentions `event_end_date`; `/llms.txt` or the API index lists `/calendar` if other pages are listed there.
- Verification approach: documentation review against the shipped behaviour; links resolve.
- Estimate details: driver: wording; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: concept inventory, agent API reference.

## Stage 8

**Estimate: 0.12**

- Goal: release verification and production replay.
- Dependencies: Stages 1–7.
- Expected changes: no product changes expected; browser layout checks for the grid and day page at phone and desktop sizes across text and icon themes, with a five-event day; JavaScript-off pass; `./v3 rebuild` from the prior schema version on a copy of real data; deploy to the maintainer's instance; replay the invite-link entry with an unapproved then approved host; record results in the Step 4 summary and the UI verification notes; no feedback item matches the calendar itself, so commits carry a `Feedback:` trailer only for PF-20261104-B10 if Stage 2 fixed it.
- Verification approach: Step 2 success criteria checked one by one; smoke set plus the tests added in Stages 1–6; the full suite is not run by the agent.
- Estimate details: driver: theme and device matrix; uncertainty: production timing.
- Risks or open questions: see Key Risks (density).
- Canonical components/API contracts touched: none new.

## Concepts Touched

[Shared composer](../concepts/shared_composer.md) used unchanged (Stage 6 placement); [thread title](../concepts/thread_title.md) directive pattern reused (Stage 4); [tag browsing](../concepts/tag_browsing.md) membership query reused (Stage 5); [read model rebuild](../concepts/read_model_rebuild.md) changed (Stage 3); [viewer session](../concepts/viewer_session.md) and [invitation lifecycle](../concepts/invitation_lifecycle.md) used unchanged (Stage 8 replay). Created in Stage 7: *calendar* and *post event metadata*.

## Study Checkpoint

2026-11-04T18:00:00Z; scope v3/A, original baseline `f41f656936e740809242a5d8e1c646068afddc28`, R1/P2, hashes verified earlier this session. User explicitly approved Step 2 v3. Whole-cycle **0.90 reaffirmed** after inspecting the event-time precedent, both thread write flows, `TagPage` membership, `WorkItems`, the compose controller and the rebuild test pattern. Eight stage-work originals 0.08/0.12/0.12/0.12/0.20/0.14/0.08/0.12, non-additive; shared planning and ledger bookkeeping owned by the cycle, not charged to stages. Claude, single exposed rater. No implementation, measured labor or accepted outcome. Await Approved Step 3; planning uncommitted.

*— Claude · 2026-11-04 UTC · Model: claude-fable-5-1*
