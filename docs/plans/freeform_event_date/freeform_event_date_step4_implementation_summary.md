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
