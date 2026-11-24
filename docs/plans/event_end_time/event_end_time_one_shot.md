# Event end time one-shot

Cycle: `v3/event_end_time`; Mode: one-shot (transitioned from staged Step 1); Record/index: this file, [plans index](../README.md), [Step 1 assessment](./event_end_time_step1_solution_assessment.md)
Debug mode: enabled ([debug log](./event_end_time_debug_log.md))
Effort study: `railspike-effort` / `v3/event_end_time`; [host ledger](../effort_ledger/event_end_time.md). Standing P2 enrollment.
Extended estimates: enabled (no separate work units in this one-shot)

**Cycle estimate: 0.55** (execution entry; Step 1 handoff was 0.45)

Status: planning written; next permitted action: planning commit, then implementation.

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
Planning commit: pending.

## Dated execution history

- 2026-11-24 16:55Z: Step 1 written (staged).
- 2026-11-24 17:45Z: maintainer asked to one-shot Option C; transitioned. Step 1's open questions take their proposed defaults.

## Study history

- Step 1 handoff (staged): 0.45, Option C.
- Execution entry, 2026-11-24 UTC: 0.55. New understanding, same scope: the end time has to travel through two composer paths, the signed-compose script, the phrase API and two read-model writers, more plumbing than Step 1 assumed. Claude single exposed rater; R1/P2.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*
