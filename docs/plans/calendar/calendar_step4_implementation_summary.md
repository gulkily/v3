> **Feature plan:** [Step 1](./calendar_step1_solution_assessment.md) · [Step 2](./calendar_step2_feature_description.md) · [Step 3](./calendar_step3_development_plan.md) · [Step 4](./calendar_step4_implementation_summary.md) · [Addendum](./calendar_addendum_conversation_notes.md)

Debug mode: enabled (inherited from Step 3; log: [calendar_debug_log.md](./calendar_debug_log.md))

Effort study: `railspike-effort` / `v3/calendar`; [host ledger](../effort_estimation_trial.md#cycle-v3calendar). Standing P2 enrollment.

Extended estimates: enabled (inherited)

Status: Step 4 closed 2026-11-05 UTC at the maintainer's request and merged to `main`. Technically complete locally. The production checks were **not done** before merge; see Stage 8 and Closeout. Step 3 was approved with the schema bump corrected to 48 → 49. Branch `feat/calendar`; planning commit `7af83949`. The maintainer's addendum (`f729d1b8`) was merged in `dacb71b8` and filed beside the plans; it confirmed the approved decisions.

**Cycle estimate: 0.90**

Step 4 entry projection, 2026-11-04 UTC: Step 3 whole-cycle 0.90 reaffirmed, same scope v3/A, original baseline `f41f656936e740809242a5d8e1c646068afddc28`, R1/P2. Rater Claude (claude-opus-5-5), exposed to the Step 3 estimate and the plans; copying the stage originals below is not a new independent rating. The schema-version correction does not change any stage's scope.

Stage-work originals from Step 3 (non-additive): 1 0.08 · 2 0.12 · 3 0.12 · 4 0.12 · 5 0.20 · 6 0.14 · 7 0.08 · 8 0.12.

Enhancement requests (all from the maintainer on 2026-11-04 after reviewing the grid; refinements of Stage 5–6 UI, no change to scope or estimates; recorded under Stage 8):

| Request | Disposition and reason | Approval | Outcome |
| --- | --- | --- | --- |
| Days next to the blank cells before the 1st are missing borders (left of the 1st, top of the second row). | Accepted: defect. Blank cells used transparent borders, which win collapsed-border ties and hide neighbours' edges. | Maintainer request | Blank cells use `border-style: none`; browser test asserts the four edges. Stage 8 enhancements commit. |
| List only the event title in a day. | Accepted. The author line is dropped from grid cells; the day page still shows author, span, place and state. | Maintainer request | Stage 8 enhancements commit. |
| Make the whole square clickable for empty days. | Accepted, and extended to every day: the day link's stretched area covers the square, while titles and `+N more` sit above it, so a title still opens its thread and the rest of the square opens the day. A focus ring and hover outline mark the square. | Maintainer request; the extension to days with events is mine and can be narrowed. | Browser test clicks an empty square and the blank area of a busy square (day page) and a title (thread). Stage 8 enhancements commit. |

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

## Stage 3 - Read model and event block span

**Original estimate: 0.12**

- Changes:
  - `src/ForumRewrite/ReadModel/ReadModelSchema.php`: nullable `threads.event_end_date`.
  - `src/ForumRewrite/ReadModel/ReadModelMetadata.php`: `SCHEMA_VERSION` 48 → 49 (corrected from the plan's 47 → 48 at approval).
  - `src/ForumRewrite/ReadModel/ReadModelBuilder.php` and `IncrementalReadModelUpdater.php`: filled on full rebuild and incremental insert; cleared with the other event columns for retracted roots, deleted-record placeholders and incremental retraction.
  - `src/ForumRewrite/ReadModel/ThreadRepository.php` (both selects), `src/ForumRewrite/Application.php` (`fetchThread`), `src/ForumRewrite/ReadModel/PublicationPostFilter.php`: carry the column alongside `event_time`.
  - `templates/partials/event_block.php`: `📅 start – end` when an end date is present, unchanged otherwise. Used by thread root cards, thread cards and board rows.
  - `docs/specs/read_model_schema_v1.md`: rows for all five event columns (none were listed) and a Version 49 section.
  - `tests/WriteApiSmokeTest.php`: `testEventEndDateIsIndexedShownAndRebuiltFromVersion48`. Incremental write and full rebuild agree for span and one-day threads; the thread page shows the span; a one-day event's block is the exact pre-change markup; a model seeded at version 48 without the column is rebuilt by normal startup before the thread page reads it.
- Verification:
  - Passed: `./v3 test --smoke` plus the new test, the Stage 1–2 tests, `testPrepareThreadWithEventFieldsRoundTripsThroughFinalizeAndReadModel`, `testEventFieldsRequireEventSupportBeforeWriting`, `DeleteRecordCommandTest::testVersion47ModelFallsBackForInspectAndStartupAddsTheIndex` and `EventEndDateParserTest`: 429 run, 429 passed. Full suite not run, per `AGENTS.md`.
  - Passed: `./v3 rebuild <scratch repo> <scratch db>` on a copy of `tests/fixtures/parity_minimal_v1` plus one span record: 4 posts, 3 threads, `couch-span` stored `2026-11-10`/`2026-11-12`, metadata schema version 49.
  - Pending (Stage 8): `./v3 rebuild` from version 48 on a copy of real data.
- Notes:
  - Every `event_time` site in `src/` now has a matching `event_end_date`; a grep for event columns without the end date returns only the parser, writer and composer, which Stages 1–2 already cover.
- Estimate details: original scope unchanged apart from the approved version number; no revision. Retrospective stage-work assessment: 0.12.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 4 - Event state from operator `event:` lines

**Original estimate: 0.12**

- Changes:
  - `src/ForumRewrite/Support/EventStatusDirective.php`: `extract()` returns `confirmed` or `declined` for a whole line `event: confirmed|declined` (any case, surrounding spaces). Quoted lines are skipped and the first match in a post wins, as `TitleDirective` scans.
  - `src/ForumRewrite/Calendar/EventStatus.php`: `forThreads(PDO, repositoryRoot, threadIds)` and `forThread()`.
    - Reads root and replies in creation order; the latest valid line wins.
    - A line counts only when its post is unretracted and `attribution_verified = 1`, and its author's profile `is_approved = 1` and `RetractionAuthority::currentOperator()` holds (active, unretired, committed operator seed).
    - The operator check is cached per author for each call.
    - The repository root is taken as a parameter, as Step 3 expected.
  - `src/ForumRewrite/Http/ThreadAndPostPageController.php`: passes `eventState` only for threads with an event date while event support is on.
  - `templates/partials/thread_root_card.php`: `Event: confirmed|declined` line under the title. The same markup is served to every viewer.
  - `tests/EventStatusTest.php`, registered in `tests/run.php` with `EventEndDateParserTest`. Covers:
    - extraction rules;
    - an operator root line;
    - latest wins across replies;
    - lines that are ignored: quoted, approved non-operator, retracted, unverified attribution, guest;
    - thread page markup present for a settled event and absent for plain and non-event threads;
    - an operator who is no longer approved stops counting.
- Verification:
  - Passed: `./v3 test --smoke EventEndDateParserTest EventStatusTest LocalWriteServiceTitleDirectiveTest` plus the four Stage 2–3 event tests in `WriteApiSmokeTest`: 446 run, 446 passed. Full suite not run, per `AGENTS.md`.
  - Inapplicable this stage: grid and day pages (Stages 5–6), themes (Stage 8).
- Notes:
  - **Correction to Stage 1:** `tests/run.php` lists test files explicitly, and Stage 1 did not register `EventEndDateParserTest`. The Stage 1 run (423 passed) was the smoke set alone and did not include the parser tests. They are registered here and pass (9/9). Stage 1's commit stays as recorded; it is not amended.
  - Retirement is covered through `ApprovalPolicy::isActiveOperator()` inside `currentOperator()`. The test exercises loss of approval, not a retirement record.
- Estimate details: original scope unchanged; no revision. Retrospective stage-work assessment: 0.12.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 5 - `/calendar` and `/calendar/<tag>` month grids

**Original estimate: 0.20**

- Changes:
  - `src/ForumRewrite/Calendar/CalendarQuery.php`: `eventsBetween(PDO, from, to, ?tag)`.
    - Listed roots from `ThreadKeysetPage::listedRootsSql()`, so retracted and identity roots are excluded, as on the board.
    - Selects events whose span touches the range.
    - The tag filter is the `TagPage` membership rule: board tag or label, case-insensitive.
    - Rows carry the board-row fields that `$threadTitle` and `$author` read; profile fields come from correlated subqueries, so the shared listing SQL is reused untouched.
  - `src/ForumRewrite/Calendar/CalendarDays.php`: `derive(events, states, from, to)` lists an event on every day of its span clipped to the range, drops declined events, and marks days `confirmed`, `open` or `empty`. `dates()` walks UTC days.
  - `src/ForumRewrite/Http/CalendarPageController.php`: `grid(?tag, month)`.
    - Shows two months from `?month=YYYY-MM`, or the current UTC month when absent or invalid.
    - Sunday-first weeks.
    - Previous and next links move one month.
    - Three entries per cell, then `+N more` linking to the day.
    - The tag view links to all events.
  - `templates/pages/calendar.php` and `public/assets/calendar.css`:
    - One table per month inside a horizontally scrolling container.
    - Day numbers link to the Stage 6 day pages.
    - Confirmed entries carry a `✓` with an accessible label, so the mark is not shown by colour alone.
    - Colours come from theme variables only.
  - `src/ForumRewrite/View/TemplateRenderer.php`: page stylesheet registration.
  - `src/ForumRewrite/Application.php`:
    - `/calendar` and `/calendar/<tag>` dispatch; a segment that is a date is left for Stage 6.
    - Both added to the application route list, so the members-only gate covers them like other pages.
    - No navigation entry, as scoped.
  - `tests/CalendarPageTest.php`, registered in `tests/run.php`:
    - day states: single day, span, span across the month edge, confirmed and declined overlap;
    - grid marks, captions and navigation;
    - board-tag and label filtering, case-insensitive;
    - confirmed glyph, declined hidden, entry cap with `+2 more`;
    - invalid month falls back to the current month; an empty tag renders an empty grid;
    - identical calendar markup for a signed-out viewer and the operator;
    - flag off;
    - members-only refusal for a signed-out visitor, with the operator allowed in.
- Verification:
  - Passed: `./v3 test --smoke CalendarPageTest EventStatusTest EventEndDateParserTest AppNavigationPlacementTest BoardSearchTest WebServerRoutingTest ApiRouteCatalogTest`: 440 run, 440 passed. Full suite not run, per `AGENTS.md`.
  - Passed: a scratch render of `/calendar?month=2026-11` and `/calendar/couches` against a fixture copy showed the expected marks across the November–December edge.
  - Pending (Stage 8): layout at phone and desktop widths, text and icon themes, a five-event day in a browser.
- Notes:
  - **Deviation:** with event support off, Step 2 says operators see the flag link. Deciding that on the server would make the markup viewer-specific, which the addendum and `AGENTS.md` rule out. Every viewer therefore gets one line, "Events are off on this site," plus the Feature Flags link, as `/tools/fastmod/` already does; the flags page decides who may change it.
  - Until Stage 6, day links (`/calendar/<date>`) return Not Found.
- Estimate details: original scope unchanged; no revision. Retrospective stage-work assessment: 0.20.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 6 - Day page with the shared composer

**Original estimate: 0.14**

- Changes:
  - `src/ForumRewrite/Http/CalendarPageController.php`: `day(?tag, date)` at `/calendar/<date>` and `/calendar/<tag>/<date>`.
    - Not Found for a date that does not exist.
    - Lists that day's events through the same query and derivation as the grid, so declined events stay off.
    - Renders the composer with the page data `/compose/thread` uses: the date fixed, Until empty, tags preset to the calendar's tag or `general`.
    - Loads the compose page's script set.
  - `templates/pages/calendar_day.php`:
    - Each event is one line: title, author, then only what the page date doesn't already say (the span when multi-day, time, place, state).
    - Below that, `partials/thread_compose_form.php` inside the same `data-compose-root` section the compose page uses.
    - The form posts to `/compose/thread` (plain) or through the signed path (JavaScript), and lands on the new thread.
  - `templates/partials/composer.php`: optional `composerEventDateFixed` marks the Date input `readonly`.
  - `templates/partials/thread_compose_form.php` and `public/assets/browser_signing.js`: optional `data-compose-draft-scope`, so a placement keeps its own draft.
    - Without it, the one shared thread-draft key let a draft from `/compose/thread` overwrite a day page's date and tag on restore.
    - Unscoped composers keep their existing key.
  - `public/assets/calendar.css`:
    - Day-list styles.
    - A phone layout: under 40rem the grid fits seven columns and each day shows its event count, linking to the day page; titles show from tablet width up. The Stage 5 grid scrolled sideways on a phone and hid Thursday–Saturday.
    - The author helper's "(unapproved)" suffix now inherits the cell's small type.
  - `src/ForumRewrite/Application.php`: day route ahead of the tag route; a date segment is always a day.
  - `src/ForumRewrite/View/TemplateRenderer.php`: `compose.css` and `calendar.css` for the day page.
  - `tests/CalendarPageTest.php`:
    - day listing, tag preset, readonly date, draft scope, compose action;
    - all-events day; impossible date gives 404;
    - JavaScript-off form post writes `Board-Tags: couches`, `Event-Date`/`Event-End-Date`/`Event-Location`, and the new event shows on the next day page and the grid;
    - a refused post re-renders through the existing compose error path with body and date kept;
    - members-only refusal on a day page.
  - `tests/browser/calendar.mjs` (manual run, like `composers.mjs`):
    - a compose-page draft does not touch a day page's fields;
    - a signed post from `/calendar/couches/2026-11-30` with Until, place and title is written with the browser key's `Author-Identity-ID` and shows on the grid;
    - a JavaScript-off post from a day page;
    - a five-event day at 390px and 1280px: three titles and `+2 more` on desktop, count `5` and Saturday on screen on a phone; no horizontal page scroll; no page errors.
  - `tests/BrowserSigningNormalizationTest.php`: the two thread-submit helper expectations now include `event_end_date`.
- Verification:
  - Passed: `./v3 test --smoke CalendarPageTest EventStatusTest EventEndDateParserTest BrowserSigningNormalizationTest WriteApiSmokeTest::testComposeThreadCarriesEventEndDateThroughPrefillPrepareAndErrorRerender`, then `./v3 test BrowserSigningNormalizationTest` after its fix: 83 run, 83 passed. Full suite not run, per `AGENTS.md`.
  - Passed: `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/calendar.mjs`, `node tests/browser/compose_disclosure.mjs`, `node tests/browser/composers.mjs`.
  - Screenshots of the grid and day page at 390px and 1280px reviewed locally (text theme only).
  - Pending (Stage 8): icon themes, dark theme, the real-data rebuild, production.
- Notes:
  - **Stage 2 gap found here:** Stage 2 added `event_end_date` to `collectThreadSubmitFields()` without running `BrowserSigningNormalizationTest`. Two of its helper tests failed until this stage updated them. The behavior was right; the expectations were stale.
  - The draft scope is a small change to the shared composer that Step 3 said would be used unchanged. It is needed for the day page's fixed date to stay fixed, and it does not change unscoped composers.
  - The compose script list is repeated from `ComposeAndAccountKeyController::identityScripts()`, which is private there.
- Estimate details: original scope unchanged; no revision. Retrospective stage-work assessment: 0.16 (the draft collision and the phone layout were not anticipated).

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 7 - Documentation and concepts

**Original estimate: 0.08**

- Changes:
  - `docs/reference/calendar.md`: pages, tag calendars, phone and wide layouts, adding an event from a day, the `event:` line and who it counts for, access, and the record and schema links.
  - `docs/concepts/calendar.md` and `docs/concepts/post_event_metadata.md`: new inventory entries, as Step 3 planned.
  - `docs/concepts/shared_composer.md`: touched entry updated for fixed fields and the per-placement draft scope added in Stage 6.
  - `docs/reference/agent_api.md`: new Events section with the five inputs, the end-date rules, the flag refusal, and the fact that client-built signed records cannot carry event headers.
  - `README.md`: Reference index entry.
  - `/llms.txt` and the API index list no pages, so nothing was added there.
- Verification:
  - Passed: every relative link and anchor in the five touched documents resolves (scripted check); `git diff --check` clean.
  - Passed against the code: the access description, rewritten after reading the gate. Step 2's wording, "sent to the Lobby", holds only for a signed-in unapproved member; a signed-out visitor gets the sign-in or Lobby page.
  - Passed against the code: the flag-off behavior. The event block hides event details while the flag is off; the first draft wrongly said records keep rendering.
  - Passed: `./v3 test --smoke PlatformDocsCatalogTest PlatformDocsPageTest DocsPublicationTest DocsPublicationFlowTest`: 455 run, 455 passed. Full suite not run, per `AGENTS.md`.
- Notes: the read-model rebuild concept describes the mechanism generally and needed no change; the version-49 section in the schema spec carries the specifics.
- Estimate details: original scope unchanged; no revision. Retrospective stage-work assessment: 0.08.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Stage 8 - Release verification

**Original estimate: 0.12**

- Changes:
  - `public/assets/calendar.css`: confirmed days use the theme's active ink for every piece of text, at a specificity that outranks theme link colours.
    - Before this, Word97 drew blue links on its navy active background (contrast 1.86).
    - No other product changes.
  - `tests/browser/calendar.mjs`: a theme pass. Every theme (light, dark, console, lcd, chicago, vapor, forge, sticker, arena, thermal, whitehot, word97, midnight) is loaded at 390px and 1280px. Each run checks:
    - no horizontal page scroll on the grid and the day page;
    - empty, open and confirmed days differ in background or border;
    - every visible text element in an open or confirmed day reaches a contrast ratio of 3 against the day's composited background;
    - no page errors.
  - The fixture's operator confirms the offer through `/api/create_reply`, so the confirmed look is checked in every theme.
- Verification:
  - Passed: `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/calendar.mjs`. Covers JavaScript-on signed post, JavaScript-off post, the five-event day at phone and desktop widths, and the 13-theme pass above.
  - Passed: screenshots reviewed locally for light, dark, Word97, Chicago and Midnight.
  - Passed: `./v3 test --smoke CalendarPageTest EventStatusTest EventEndDateParserTest`: 441 run, 441 passed. Full suite not run, per `AGENTS.md`.
  - Inapplicable: theme navigation icons. The calendar has no navigation entry this cycle, so `theme-nav-icons.css` has nothing to map.
  - **Not done before merge:** `./v3 rebuild` from schema 48 on a copy of real data. Only the fixture and scratch rebuilds (Stage 3) were run; this workspace has no copy of the instance's records. The maintainer has the branch deployed for testing; the startup rebuild there is the first real-data run.
  - **Not done before merge:** the invite-link replay with an unapproved, then approved, host. This workspace has no access to the server. The maintainer chose to merge and run it on the deployed build.
  - Not done: Step 2 success criteria checked one by one on production. Everything else in them is covered locally by the tests above.
- Notes:
  - No feedback item matches the calendar. PF-20261104-B10 was replayed and not reproduced (Stage 2), so no `Feedback:` trailer applies.
- Estimate details: original scope unchanged; no revision. Retrospective stage-work assessment: 0.16. The 13-theme contrast pass, the Word97 fix and three maintainer refinements went beyond the planned checks; the production replay did not happen.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
- Enhancements (maintainer requests above):
  - `public/assets/calendar.css`, `templates/pages/calendar.php`, `tests/browser/calendar.mjs`.
  - Passed: `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/calendar.mjs`, including the 13-theme pass and the new border and click checks. `./v3 test --smoke CalendarPageTest`: passed. Full suite not run.
  - Screenshot reviewed: dark theme at 1280px.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

## Closeout

Final verification checklist (`step4_implementation_after.md`):

1. Passed: all eight stages completed with recorded verification. Stage 8's production items are recorded as not done, not as passed.
2. Passed: each stage has its own commit containing its summary update.
   - Stage 1 `b4682206`, Stage 2 `24af2241`, Stage 3 `0727aae1`, Stage 4 `ca93d040`, Stage 5 `03342664`, Stage 6 `d61600fb`, Stage 7 `334d1332`.
   - Stage 8: `36722027` and `70d8c91d`, plus this closeout.
3. Passed: the first Step 4 commit is `7af83949`. It holds the approved plans with organization moves only; the summary first appears in `b4682206`. The cycle has 12 commits from the planning commit, against a minimum of 9.
4. Partly met: the Step 3 Completion Contract.
   - Met locally: normal entry, end-to-end outcome, required recovery (refused post keeps its draft; flag off; prior-version rebuild; old records unchanged).
   - Release condition: met locally with JavaScript on and off, at phone and desktop widths, in 13 themes.
   - **Not done:** the deployment/external verification (production replay).
5. Passed: dependency and environment checks are recorded per stage. No new dependencies; `playwright-core` was already a dev dependency and was only installed locally. The final diff was reviewed for duplication and scope.
   - One known duplication: the compose script list (Stage 6 note).
   - One small shared-composer change: the draft scope (Stage 6 note).
6. Passed: reference, concepts and the agent API docs were updated in Stage 7; this summary is final. Estimate labels keep the originals and show retrospectives separately; contributions are signed with the verified model only.
7. Check status:
   - Passed: smoke set plus changed tests at each stage, and the browser tests listed per stage.
   - Not run: the full suite, per `AGENTS.md`.
   - **Not done:** production rebuild from schema 48 on real data, and the invite-link replay.
   - Known user recovery condition: an unapproved host who opens `/calendar` should be sent to the Lobby and see the grid after approval. Verified locally only in `CalendarPageTest` (members-only refusal, approved access), not on the maintainer's instance.

Deviations from the approved documents:
- Flag-off notice shown to every viewer (Stage 5).
- Phone grid shows counts instead of titles (Stage 6).
- Per-placement draft scope in the shared composer (Stage 6).
- Whole-square day links on every day (Stage 8 requests).

**Cycle retrospective assessment (not a projection, not measured effort): 1.00**, Claude, 2026-11-05 UTC, exposed to all prior estimates.
- Original Step 3 projection 0.90 retained.
- Drivers above plan: the draft collision and phone layout (Stage 6), the theme contrast pass (Stage 8), and correcting two test-registration and coverage misses (Stages 1–2).
- Retrospective stage work: 0.08 · 0.12 · 0.12 · 0.12 · 0.20 · 0.16 · 0.08 · 0.16.
- Maintainer comfortable-fit judgment: **pending**. Study acceptance: **pending**.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
