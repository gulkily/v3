> **Feature plan:** [Step 1](./freeform_event_date_step1_solution_assessment.md) · [Step 2](./freeform_event_date_step2_feature_description.md) · [Step 3](./freeform_event_date_step3_development_plan.md) · [Step 4](./freeform_event_date_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/freeform_event_date`; [host ledger](../effort_estimation_trial.md#cycle-v3freeform_event_date). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled (inherited)

**Cycle estimate: 0.90** (Step 3, reaffirmed at Step 4 entry)

Branch: `feat/freeform_event_date`. Planning commit `b70aadd`. Base `ed6aecc` (main moved past the Step 3 revision `e01a6fe`; no planned file had conflicting changes).

Maintainer direction at approval (2026-11-11): "let's stretch it a little bit if we have to." All eight stages stay in this cycle; the Step 3 split point for Stages 5 to 7 is not used.

## Study checkpoint

2026-11-11T01:40:00Z, Step 4 entry. Whole-cycle projection **0.90**, reaffirmed from Step 3, same scope v2 plus flag, R1/P2, single exposed Claude rater. Stage originals copied from Step 3: 0.10/0.12/0.10/0.12/0.10/0.10/0.08/0.08, non-additive.

## Stage 1 - Flag and no-JS When box

**Original estimate: 0.10**

- Changes:
  - `FORUM_EVENT_WHEN_ENABLED` ("Event phrases") in `FeatureFlagRegistry`, default off, after event support in the Authoring group, requiring event support. Templates get `eventWhenEnabled`.
  - When box and Fill button at the top of the composer's Event section; a fixed Date carries `event_date_fixed` so it stays fixed after a round trip.
  - `EventPhraseReader` (new): today, tonight, tomorrow, weekdays (today counts), and calendar dates with or without a year. `EventPhraseReading` holds the result; `EventPhraseFill` applies it to the fields and keeps a fixed Date.
  - `ComposeAndAccountKeyController`: a submit with `event_when_fill` re-renders the composer filled, with the draft kept and nothing written; today is the UTC date and the status line says so.
- Verification:
  - `./v3 test --smoke FeatureFlagEvaluatorTest FeatureFlagChangePageTest FeatureFlagsBehaviorTest CalendarPageTest EventPhraseReaderTest EventPhraseComposeTest`: 469 run, 468 passed.
  - The one failure, `FeatureFlagsBehaviorTest::testRenderedOrderIgnoresRegistryInputOrder`, reproduces on the base commit: its fixture dies in `tools_nav_card.php` ("Value of type null is not callable"). Not caused by this stage; left for the maintainer.
  - Full suite not run.
- Notes:
  - Without JavaScript, Enter in the When box submits with the form's first button, Post, as Enter in the other Event fields already does. Stage 3 makes Enter mean Fill when scripts run.
- Estimate details: original scope as planned; uncertainty low; no revision.

**Retrospective stage work: 0.10**

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Stage 2 - Built-in reader coverage

**Original estimate: 0.12**

- Changes:
  - `EventPhraseReader` now reads start times ("7pm", "7:30 p.m.", "19:00", "at 7", "noon", "midnight", "7-9pm" keeps its start), "this" and "next" weekdays, "in 3 days" and "in two weeks", spans ("Nov 14-16", "Nov 28 - Dec 2", "Friday to Sunday", "this weekend", "next weekend"), and a weekday with a date ("Sat Nov 14"), which must agree.
  - The rules where English is loose are written at the top of the class: a bare weekday includes today, "next Friday" is the Friday of next week, a bare hour from 1 to 11 is pm unless "am" or "morning" says otherwise.
  - New reasons: a time with no day, a malformed time.
- Verification:
  - `./v3 test EventPhraseReaderTest EventPhraseComposeTest`: 15 passed. The phrase test set in `EventPhraseReaderTest` covers each rule, month and year ends, leap day, a Sunday "today", and phrases that must stay unread ("next week", "the week of the 20th", a weekday that disagrees with its date).
  - Smoke set not rerun for this stage: only the new reader class changed. Full suite not run.
- Notes:
  - The phrase test set is the agreed contract for "common". The maintainer can add phrases during review.
  - Phrases about holidays ("the weekend after Thanksgiving") stay unread for the LLM fallback in Stage 4.
- Estimate details: original scope as planned; uncertainty medium; no revision.

**Retrospective stage work: 0.12**

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Stage 3 - Fill in place with JavaScript

**Original estimate: 0.10**

- Changes:
  - `GET /api/read_event_phrase` (`EventPhraseApiController`): returns the filled fields, a message and the date it read with. It writes nothing, answers 404 with the flag off, and is `no-store`. The browser's local date is used when it is within a day of the server's UTC date; otherwise the UTC date.
  - `public/assets/event_when.js`, loaded only while the flag is on: Fill and Enter in the When box call the endpoint, fill Date, Until and Time in place, and fire input events so the Event summary and the draft store update. A fixed Date stays (the read-only picker is sent as fixed). The status line shows only reasons and the fixed-date note; a plain success shows nothing.
  - `compose.css`: When and Fill share a row.
  - Route registered in `Application` and `ApiRouteCatalog`.
- Verification:
  - `./v3 test --smoke ApiRouteCatalogTest WebServerRoutingTest EventPhraseComposeTest EventPhraseReaderTest CalendarPageTest`: 470 passed.
  - `node tests/browser/event_when.mjs` (new): passes. New York at phone width and Tokyo at desktop width; in-place fill with the browser's date, Enter fills without posting, a span fills Until, an unread phrase changes nothing; a calendar day page keeps its date; without JavaScript Fill returns the filled composer; the repository HEAD is unchanged throughout; no page errors; no horizontal overflow. Screenshots checked at 390 and 1280 px in the default theme.
  - Browser test run with the preinstalled `playwright-core` 1.63.0 linked into `node_modules`; the project pins 1.58.1. Nothing was added to the repository.
  - Full suite not run.
- Notes:
  - Other themes are checked in Stage 8.
- Estimate details: original scope as planned; uncertainty low; no revision.

**Retrospective stage work: 0.10**

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Stage 4 - LLM fallback

**Original estimate: 0.12**

- Changes:
  - `EventPhraseLlmReader` (new): one structured call (`event_phrase` schema: status, date, end_date, time) with today, the poster's time zone and the phrase, through the existing Anthropic or OpenAI-compatible provider. Timeout capped at 15 seconds; no exchange recorder is passed, so Fill calls are not stored. The answer is checked like a record: valid dates, Until not before Date, 24-hour time, and a date from a year back to five years ahead. A provider error gives "Could not read that right now."
  - `StubEventPhraseProvider` (new) serves `LLM_PROVIDER=stub`: a week after today at 18:00, or unclear when the phrase says "unclear".
  - `EventPhraseFill` takes an optional fallback. It runs only when the built-in reader could not read the day, not for a bad time, a time with no day, or a phrase over 120 characters.
  - `EventPhraseFillFactory` (new) builds Fill for the request: the fallback only for a signed-in approved member with a provider configured, built lazily. Both the API and the no-JS path use it. The time zone must be a known IANA name or it is dropped.
  - `RouteServices::projectRoot()` added so the factory can load the private config.
- Verification:
  - `./v3 test --smoke EventPhraseLlmReaderTest EventPhraseComposeTest EventPhraseReaderTest LlmProviderConfigTest PostAnalyzerFactoryTest`: 481 passed.
  - `EventPhraseLlmReaderTest` (new) covers success, the request contents, ten unclear or malformed answers, a provider exception, an over-long phrase, and that the fallback runs only for an unread day. `EventPhraseComposeTest` covers the stub provider end to end: refused for a signed-out viewer, used for the approved operator, unclear answers, the built-in reader first, and the no-JS path. HEAD unchanged.
  - Not checked against a real provider: no key in this workspace.
  - Full suite not run.
- Notes:
  - No request limiter exists to reuse (confirmed); gating, the length cap and one call per Fill are the cost guard, as planned.
- Estimate details: original scope as planned; uncertainty medium; no revision.

**Retrospective stage work: 0.12**

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
