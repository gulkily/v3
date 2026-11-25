# Event end time one-shot

Cycle: `v3/event_end_time`; Mode: one-shot (transitioned from staged Step 1); Record/index: this file, [plans index](../README.md), [Step 1 assessment](./event_end_time_step1_solution_assessment.md)
Debug mode: enabled ([debug log](./event_end_time_debug_log.md))
Effort study: `railspike-effort` / `v3/event_end_time`; [host ledger](../effort_ledger/event_end_time.md). Standing P2 enrollment.
Extended estimates: enabled (no separate work units in this one-shot)

**Cycle estimate: 0.55** (execution entry; Step 1 handoff was 0.45)

Status: implementation and local event replay complete. Browser and GnuPG checks remain blocked in this workspace. Next permitted action: push the completion commit and open the PR.

## Original request

> https://zenmemes.com/threads/thread-20261116102241-f0e948f6
> There's a problem with this event. The listing says 1–4pm, but in the calendar it is 1–2pm. Can you please write a plan to fix it and pause for approval.

## Authorization

2026-11-24 17:45Z, after reading the Step 1 assessment: "can we one-shot option c?" Then: "you have my permission to push to repo and create a pr". This authorizes Option C of the Step 1 assessment as one bounded one-shot, carried to a pushed branch and an open PR. It does not authorize merging, deploying or posting on zenmemes.com. The staged cycle stops at Step 1; its assessment is kept as this cycle's approach record.

## Outcome and work type

Application change. A timed event can say when it ends, and every place that shows or exports it uses that end:

1. New optional root header `Event-End-Time: HH:MM` (needs `Event-Time`). Written from the composer's new "Ends" field, the `create_thread`/`prepare_thread` input `event_end_time`, and the built-in phrase reader, which now keeps the end of a range like "1-4pm".
2. New reply directive `event-time: 13:00-16:00` (or `event-time: 13:00`) that sets a thread's event times. It counts when its post is unretracted, its attribution is verified, and its author is the thread root's author or a current operator; the latest counting line wins. Nothing is stored, as with `event:` status.
3. Exports (`.ics` feed, one-event `.ics`, Add to Google Calendar), the event block and calendar day entries show the resolved start and end. With no end, the 60-minute default stays. An end at or before the start on a one-day event ends the next day.

Exclusions: the LLM phrase reader (returns no end time; it still fills start); board rows and cards outside the thread page keep the root's own times when a directive changes them (they show only the start, so a directive that only adds an end changes nothing there); posting the fixing reply on zenmemes.com (needs deploy first).

## Approach and risks

- Read-model schema change: one `threads.event_end_time` column, schema version bump per `CONTRIBUTING.md`. Risk: missed write site leaves NULL. Mitigation: mirror every `event_when` site from `fc56566f7`/`79cb6c3d1`.
- Directive at read time, mirroring `EventStatus::forThreads`: one batched query per page. Risk: thread page and calendar disagree. Mitigation: one `EventTimes` resolver used by every calendar output and the thread page.
- Who may set times: thread author or operator, matching `TitleDirectiveAuthority`. Step 1 left this open; this is its proposed default.

## Reuse decisions

- `EventStatusDirective` / `EventStatus` (`src/ForumRewrite/Support`, `src/ForumRewrite/Calendar`): pattern for the directive parser and batched read-time resolution; extended with a sibling rather than a new mechanism.
- `RetractionAuthority::currentOperator` for the operator check, as `EventStatus` and `TitleDirectiveAuthority` do.
- `CalendarIcs::timedDates`: extended to take an end time; no second iCal writer.
- `templates/partials/event_block.php`, `composer.php` Time field: extended in place; "Ends" sits in the same field group (PF-20261111-F11 compacts these fields later).
- `EventPhraseReader::takeTime` / `EventPhraseReading`: extended to carry an end time.

## Completion conditions

- Posting a thread with Time 13:00 and Ends 16:00 writes `Event-End-Time: 16:00`; its thread page shows `at 13:00–16:00`, its `.ics` and Google link encode 13:00–16:00 in the site zone.
- A reply `event-time: 13:00-16:00` by the root's author on a thread with only `Event-Time: 13:00` changes the same outputs; the same line from another non-operator member changes nothing.
- When "Friday 1-4pm" fills Time 13:00 and Ends 16:00.
- Older records without the header behave as before (60 minutes).

## Concepts touched

[Post event metadata](../../concepts/post_event_metadata.md) (changed: new header, reply directive), [Date phrase](../../concepts/date_phrase.md) (changed: ranges keep their end), [Calendar](../../concepts/calendar.md) (used: exports read resolved times).

## Verification and recovery plan

Smoke set plus the event, phrase, iCal, read-model schema and composer tests touched (`./v3 test --smoke ...`); full suite not run, per `AGENTS.md`. New tests for the header, directive authority and range reading. Recovery for the reported thread: after deploy, its author (claude) posts `event-time: 13:00-16:00`; replaying the reported failure needs production and stays unverified here.

Starting revision and branch: `2d7805c8b6d63b0f2ff6ecdd264ec257eaece23b`, branch `feat/event-end-time`.
Planning commit: `6b9828a06` (confirmed).

## Dated execution history

- 2026-11-24 16:55Z: Step 1 written (staged).
- 2026-11-24 17:45Z: maintainer asked to one-shot Option C; transitioned. Step 1's open questions take their proposed defaults.
- 2026-11-24 17:50Z: maintainer asked to stop and hand off to another agent. Work-in-progress commit on `feat/event-end-time` (record layer and read model only; untested).

## Handoff (2026-11-24 17:50Z)

Done (WIP commit, `php -l` clean, no tests run):
- `Event-End-Time` header: `PostRecord::$eventEndTime`, parser (requires `Event-Time`), `LocalWriteService` reads `event_end_time` on both thread paths (direct and prepared), writes the header after `Event-Time`, and the events-off guard lists it.
- Read model schema 57: `threads.event_end_time` in schema, full builder, incremental insert, root retraction clear, `PublicationPostFilter`, `ThreadRepository` (both selects), `Application::fetchThread`, `CalendarQuery`.

Remaining:
1. Schema docs: `docs/specs/read_model_schema_v1.md` (contract line, `threads` table row, Version 57 section); `docs/specs/canonical_post_record_v1.md` event headers and writer order; regression test starting at schema 56 (model: `tests/EventWhenRecordTest.php`, registered in `tests/run.php`).
2. `PublicOfflineSnapshotBuilder::insertThread` does not copy event columns at all; check whether the offline snapshot shows events before deciding it is out of scope.
3. Exports: `CalendarIcs::timedDates` takes the end time (end at or before start on a one-day event = next day; no end = 60 minutes), for `document()` and `googleEventUrl()`.
4. Directive: `EventTimeDirective` (sibling of `Support/EventStatusDirective.php`, `event-time: HH:MM` or `HH:MM-HH:MM`, quoted lines skipped, first match per post) and `Calendar/EventTimes::forThreads()` (sibling of `EventStatus::forThreads`: unretracted, attribution verified, author is the root's author or `RetractionAuthority::currentOperator`; latest wins). Overlay its result on rows in every `CalendarPageController` path that calls `EventStatus::forThreads` and in the thread page (`Application::fetchThread`).
5. Display: `templates/partials/event_block.php` shows `at 13:00–16:00`; `templates/pages/calendar_day.php` shows the range.
6. Composer: "Ends" time input beside Time in `templates/partials/composer.php`; `ComposeAndAccountKeyController` threads `event_end_time` through `composeThread`, the error re-render, `renderEventWhenFill` and `renderComposeThreadPage`; `public/assets/browser_signing.js` (~line 738) sends `event_end_time`; `public/assets/compose_disclosure.js` summary shows the range.
7. Phrase reader: `EventPhraseReader::takeTime` keeps a range's end ("1-4pm" → 13:00, 16:00); `EventPhraseReading` gains `endTime`; `EventPhraseFill`, `EventPhraseApiController` and `public/assets/event_when.js` fill `event_end_time`. LLM reader stays start-only.
8. Docs: `docs/reference/agent_api.md` (~line 721) documents `event_end_time` and the `event-time:` reply; concepts `post_event_metadata.md` and `date_phrase.md` updated.
9. Tests: smoke set plus new/changed tests (`./v3 test --smoke ...`); do not run the full suite. Fix any test that fails on the base commit too.
10. Closeout: complete this record (Outcome, Verification, Final review, closeout estimate), update `docs/plans/effort_ledger/event_end_time.md`, append a closeout entry to the debug log, completion commit, push, open the PR (with a checkout snippet). After merge and deploy, post `event-time: 13:00-16:00` as claude in thread-20261116102241-f0e948f6.

## Outcome

- New events can specify Ends in the composer or `event_end_time` through the API. The built-in phrase reader fills the end of a time range and carries it through both forms of Fill. The direct and prepared writers store the same validated header.
- A reply from the verified root author or a current operator corrects the start and end without changing the root record. The event block, calendar day, iCalendar feeds, single-event iCalendar and Google Calendar link use the latest eligible reply. A start-only correction restores the default end.
- The reported 1–4 p.m. lecture was reproduced locally with a legacy start-only event. The correction changed its exported end from 2 to 4 p.m. on all three export paths. Production needs the deployed code and a correction reply from the recorded author or a current operator.
- The public offline snapshot and its browser reader carry no event metadata or event views. The feature leaves that snapshot schema alone. The event time controls use the existing composer row styling. No new feedback item matched the feature. The nearby PF-20261111-F11 still tracks the wider composer layout work.

## Verification

- Baseline `2d7805c8`: `./v3 test --smoke` passed 461 and failed 26. Two test setup fixes recovered 19 cases. The feature flag fixture now creates its destination before copying, and the API busy test now supplies a valid signing identity and uses normal HTTP warning handling so it can reach and observe the intended error response.
- Feature branch: the following run passed 545 cases, including all 65 selected event and browser-signing transport cases. Seven baseline cases still fail because their GnuPG fixtures cannot start an agent in this workspace. The local socket check returned `Operation not permitted`. The full suite was not run.

```bash
./v3 test --smoke CalendarPageTest CalendarIcsTest EventPhraseReaderTest \
  EventPhraseComposeTest EventPhraseLlmReaderTest EventWhenRecordTest \
  BrowserSigningNormalizationTest::testThreadSubmitTransportHelpersCollectFieldsAndParseResponses \
  BrowserSigningNormalizationTest::testThreadSubmitTransportHelperCollectsEventFieldsWhenPresent
```

- Passed PHP lint, JavaScript syntax checks and `git diff --check`. The browser fixture was updated to check the end field and the signed record, but `CHROMIUM_PATH=/usr/lib/chromium/chromium node tests/browser/event_when.mjs` was blocked at browser startup by the same socket restriction. Visual checks and the signed browser round trip remain unverified.
- The migration fixture removes `event_end_time` and marks the database version 56. Normal startup rebuilt it to schema 57 and rendered the restored range. The export tests cover overnight and multi-day ends, older one-hour behavior and reply authority. The production thread was left untouched.

## Final review

Reviewed the diff against Option C, including the existing event feature flags, template consumers, schema projection and rebuild paths, and documented API contract. The plans index entry sits beside the earlier Event time cycle. The completion commit includes this record, the ledger and the debug log. PR publication is separate from deployment and production verification.

## Study history

- Step 1 handoff (staged): 0.45, Option C.
- Execution entry, 2026-11-24 UTC: 0.55. New understanding, same scope: the end time has to travel through two composer paths, the signed-compose script, the phrase API and two read-model writers, more plumbing than Step 1 assumed. Claude single exposed rater; R1/P2.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*


## Closeout checkpoint

2026-11-24 UTC. Scope v1, starting revision `2d7805c8b6d63b0f2ff6ecdd264ec257eaece23b`. R1 hashes verified against the host ledger. Codex read the prior scores before implementation and made one exposed retrospective assessment. **Retrospective scope assessment: 0.55.** The end field spans storage, composer, phrase parsing and exports, with one additional reply-authority path. The prior 0.45 and 0.55 projections remain as recorded. The browser and GnuPG limits above keep verification partial. Maintainer comfortable-fit judgment and study acceptance remain pending.

*— Codex · 2026-11-24 UTC*
