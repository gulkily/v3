# Calendar iCal feed Step 4 implementation summary

> **Feature plan:** [Step 1](./calendar_ics_feed_step1_solution_assessment.md) · [Step 2](./calendar_ics_feed_step2_feature_description.md) · [Step 3](./calendar_ics_feed_step3_development_plan.md) · [Step 4](./calendar_ics_feed_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/calendar_ics_feed`; [host ledger](../effort_ledger/calendar_ics_feed.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.55**

Branch `feat/calendar-ics-feed`, replayed onto `f05cc94` when push access arrived; planning commit `ab5c89f`, Stage 1 `fc4c007`. PR [#324](https://github.com/gulkily/v3/pull/324) (Steps 1–3, Stages 1–2) was merged 2026-11-17. Later stages continue on `feat/calendar-ics-routes` from `3e1b719`, per AGENTS.md follow-up rules.

## Step 4 entry

- Step 3 approved 2026-11-17. Whole-cycle projection **0.55** reaffirmed after the planning commit. Stage originals 0.05/0.12/0.12/0.10/0.10/0.05/0.03 copied from Step 3, non-additive. Claude single exposed rater; R1 hashes not re-verified.

## Stage 1 - Google probe

**Original estimate: 0.05**

**Status: partly checked.** Stage 2 started before the remaining checks, at the maintainer's request (2026-11-17), with floating times as planned; a shifted time would revise Stage 2's time format.

- Changes:
  - New `docs/plans/calendar_ics_feed/google_probe.ics`. Probe A is a tentative event on 2026-11-20 at 19:00 with no time zone. Probe B is a confirmed all-day event from 2026-11-27 to 2026-11-29, with a place and a URL.
- Verification:
  - Parsed with the Python `icalendar` package: both events read back with the intended start, end and status. Passed.
  - No line exceeds 75 octets; lines end in CRLF. Passed.
  - Google checks on Ilya's iPhone: pending. The branch cannot be pushed from this session, so the probe has no raw GitHub URL. Revised route: Ilya copies the file to `public/` on the zenmemes.com server, so it is served at `https://zenmemes.com/google_probe.ics`, and removes it afterwards.
- Checks for Ilya, with results to record here:
  - Subscribe link on the iPhone: **passed** (maintainer, 2026-11-17). The link opened Google Calendar and offered to add the calendar. Google shows that offer before it fetches the file, so this does not yet show that the events arrived. At 23:40 UTC `https://zenmemes.com/google_probe.ics` returned 404.
  - "From URL" on calendar.google.com: does the calendar appear?
  - Probe A: shown at 7:00 PM on Fri Nov 20, or shifted?
  - Probe B: three days, Nov 27 to Nov 29, or four?
  - Event-template link: does Google's event form open filled in, at 7:00 PM?
- Notes:
  - **Deviation from Step 3:** the probe is served from zenmemes.com instead of a raw GitHub URL, because this session cannot push. Same purpose and scope.

## Stage 2 - iCal writer and flag

**Original estimate: 0.12**

**Retrospective: 0.10**

- Changes:
  - New `src/ForumRewrite/Calendar/CalendarIcs.php`. `document()` takes event rows shaped like `CalendarQuery::eventsBetween`, a thread-to-state map from `EventStatus`, the site URL and a calendar name.
  - All-day events use DATE values with an end the day after the last day. A timed event starts at `Event-Time` with no zone and ends an hour after that time on its last day.
  - UID is `<thread_id>@<host>`. STATUS is CONFIRMED for a confirmed event and TENTATIVE otherwise. The DESCRIPTION holds the phrase, the link and the thread URL. Text is escaped, lines are folded at 75 octets on character boundaries, and lines end in CRLF.
  - New flag `FORUM_CALENDAR_FEED_ENABLED` ("Calendar feed"): site-mutable, on, requires Event support, in the AUTHORING group after Event phrases.
- Verification:
  - `./v3 test --smoke CalendarIcsTest FeatureFlagEvaluatorTest`: 478 passed. Full suite not run.
  - New `CalendarIcsTest`: one-day, multi-day, timed, timed multi-day across midnight, tentative and confirmed, escaping, the description, folding of Cyrillic text, and skipping rows with a bad date.
  - `FeatureFlagEvaluatorTest`: both registry lists updated for the new flag.
  - A two-event sample with a long Cyrillic title read back through the Python `icalendar` package with the intended dates, times, status, title and description.
- Notes:
  - Step 3 named no end for timed events. **Decision:** a one-hour length, because `Event-Time` has no end time and a calendar entry with no end shows as a zero-length tick. New understanding, not changed scope.
  - Nothing reads the flag yet; Stage 3 adds the routes.
- Estimate details: as planned; the writer was simpler than estimated.

## Stage 3 - Feed routes

**Original estimate: 0.12**

**Retrospective: 0.10**

- Trigger: after deploying #324, the maintainer got a 404 at `/calendar.ics`. That was expected, because #324 had no routes; this stage adds them.
- Changes:
  - `CalendarQuery::eventsBetween` also selects `event_link` and `event_when`.
  - New `CalendarPageController::feed(?string $tag)`. It reads events from 90 days ago to two years ahead with the grid's query and states, drops declined events, and writes them with `CalendarIcs`. The site origin comes from `TemplateRenderer::requestOrigin()`. The calendar name is the site's display name, plus `#tag` for a tag feed. Returns null while event support or the feed flag is off.
  - `Application`: `/calendar.ics` and `/calendar/<tag>.ics`, checked before the calendar page routes. They send `text/calendar; charset=utf-8` with a five-minute public cache, or 404. Both are added to `isApplicationRoute`. `membersOnlyRequestAllowed` is unchanged, so members-only sites keep the feed behind sign-in.
- Verification:
  - `./v3 test --smoke CalendarPageTest CalendarIcsTest`: 490 passed. Full suite not run.
  - New `CalendarPageTest` cases: the feed lists the grid's events with dates relative to today, leaving out declined, anonymous and too-old events; confirmed and tentative status; the tag feed lists only its tag; 404 with event support off and with the feed flag off, while the tag page still works; and on a members-only site, signed-out visitors don't get the feed.
  - Local server with event support on: `/calendar.ics` returned 200, `text/calendar; charset=utf-8`, `Cache-Control: public, max-age=300`. `/calendar/general.ics` returned 200 and `/calendar.ics/` returned 404.
  - zenmemes.com: pending deploy.
- Notes:
  - The Stage 1 time checks are still open. The live feed can now stand in for the probe file.
- Estimate details: as planned.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
