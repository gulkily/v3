# Event end time Step 1 solution assessment

> **Feature plan:** [Step 1](./event_end_time_step1_solution_assessment.md) · Step 2 · Step 3 · Step 4

Debug mode: enabled ([debug log](./event_end_time_debug_log.md))

Effort study: `railspike-effort` / `v3/event_end_time`; [host ledger](../effort_ledger/event_end_time.md). Standing P2 enrollment; extended estimates enabled.

Cycle estimate: 0.45

## Original Query

https://zenmemes.com/threads/thread-20261116102241-f0e948f6

There's a problem with this event. The listing says 1–4pm, but in the calendar it is 1–2pm. Can you please write a plan to fix it and pause for approval.

## Understood Intent

The HTMAA lecture on Nov 25 runs 1:00–4:00 PM, and the thread text says so. The calendar copy (Add to Google Calendar, the thread's `.ics`, and the subscribed feed) shows 1:00–2:00 PM. Ilya wants this event right and the cause fixed so other events don't repeat it.

Cause: an event record has a start time (`Event-Time`) but no end time. The [event_time](../event_time/event_time_step1_solution_assessment.md) cycle left duration out on purpose ("Let's leave duration out of it for now"), so `CalendarIcs.php` gives every timed event a fixed 60 minutes. The date-phrase reader also drops the end of a range ("7-9pm keeps its start"). The thread's own post already warns that its calendar links show one hour.

## Problem

A timed event can't say when it ends, so every calendar export shows it as one hour long, whatever the post says.

Concepts touched: [Post event metadata](../../concepts/post_event_metadata.md) (all options: a new end-time field), [Date phrase](../../concepts/date_phrase.md) (Options A and C: ranges keep their end), [Calendar](../../concepts/calendar.md) (read unchanged; the export uses the new field). Option B and C add a reply directive beside `event:` and `theme:`.

Feedback items: none ask for an end time. [PF-20261111-F11](../../feedback/items/PF-20261111-F11.txt) (compact date and time fields) touches the same composer fields; the new field should fit that layout rather than add a row.

## Option A — End time on new events
**Estimate: 0.30** — driver: one new record header carried through parser, read model, composer, phrase reader and exports; uncertainty: low.

Add an optional `Event-End-Time: 16:00` header to the root record. The composer gets an "Ends" time beside Time; the phrase reader fills it from ranges like "1-4pm". The event block shows `13:00–16:00`, and the `.ics`, the feed and the Google link use the real end. Events without it keep the 60-minute default.

- Fixes the cause for every event posted after the change.
- An end time reads more plainly than a duration and maps directly from "1–4pm"; an overnight event ends on its Until date.
- Does not fix this thread. Its root is a signed record, so it can't be edited in place, and post revision is backlogged.
- Read-model schema change (one column), with the usual migration and rebuild.

## Option B — Reply directive that sets an event's times
**Estimate: 0.25** — driver: a new directive and who may use it; uncertainty: the permission rule.

A reply line `event-time: 13:00-16:00` (or `13:00` alone) in an event thread sets the event's times, like `event: confirmed` settles it. It counts from the thread's author or an operator; the latest counting line wins. Nothing in the root record changes.

- Fixes this thread with one reply, and corrects any past event, including signed ones.
- Keeps the edit history visible in the thread, in line with keeping the paper trail.
- New events still can't carry an end time when posted; every multi-hour event needs a follow-up reply.
- The time shown can differ from the root's headers, so readers of the raw record see an older time.

## Option C — A and B together
**Estimate: 0.45** — driver: Option A plus the directive, sharing one end-time value through the exports; uncertainty: the directive's permission rule.

Ship the `Event-End-Time` header for new events and the `event-time:` reply for correcting existing ones. Exports read one resolved start and end: the latest counting directive, else the root's headers, else the 60-minute default. This thread is fixed with a reply once the change is deployed.

- Fixes this event and stops new ones from repeating it.
- One resolution rule for start and end, so the event block, day page, `.ics`, feed and Google link can't disagree.
- Largest of the three; still under one slice.

## Recommendation

Option C as one cycle. The vertical slice: after deploy, claude (or Ilya) replies `event-time: 13:00-16:00` in this thread, and the thread's Add to Google Calendar link, its `.ics` and the subscribed feed show 1:00–4:00 PM after Google's next refresh. A new event posted with When "Friday 7-9pm" gets 19:00–21:00 without a reply. The post's warning line can then be answered in the thread.

If a smaller first step is preferred, Option B alone fixes this event soonest; Option A follows as its own cycle.

Questions for Step 2, with proposed defaults:

- Who may set times by reply: the thread's author and operators (proposed), or any approved member.
- Default length with no end time: keep 60 minutes (proposed), or send a start-only event.
- An end time earlier than the start on a one-day event: read it as ending the next day (proposed), or reject it.

Enhancement requests: none beyond the original query.

## Study snapshots

- Intake (after code inspection; no pristine intake): 0.45 projected total for the recommended scope. Baseline `2d7805c8b6d63b0f2ff6ecdd264ec257eaece23b`, scope v1, R1, P2; Claude single exposed rater, 2026-11-24 UTC. R1 hashes not re-verified this session.
- Step 1 handoff: 0.45 for Option C. Options A 0.30, B 0.25, C 0.45; not summed.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*
