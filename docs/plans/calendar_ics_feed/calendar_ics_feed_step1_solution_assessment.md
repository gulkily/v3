# Calendar iCal feed Step 1 solution assessment

> **Feature plan:** [Step 1](./calendar_ics_feed_step1_solution_assessment.md) · [Step 2](./calendar_ics_feed_step2_feature_description.md) · [Step 3](./calendar_ics_feed_step3_development_plan.md) · [Step 4](./calendar_ics_feed_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/calendar_ics_feed`; [host ledger](../effort_ledger/calendar_ics_feed.md). Standing P2 enrollment; extended estimates enabled.

Cycle estimate: 0.50 (Revision 1; was 0.40)

## Original Query

How do I add my ZenMemes calendar to my Google Calendar?

[Claude: ZenMemes publishes no calendar feed yet; v3 needs one, for example `/calendar.ics`. Want us to write Railspike Step 1 for a `calendar_ics_feed` cycle?]

Please write Step 1.

## Understood Intent

Ilya wants the ZenMemes calendar to show up inside Google Calendar and stay current, without copying events by hand. Google Calendar subscribes to outside calendars only through an iCal (`.ics`) URL, entered under "Other calendars → From URL". Today the calendar exists only as HTML pages (`/calendar`, `/calendar/<tag>`, `/calendar/<date>`). Nothing in v3 writes iCal, and `/calendar.ics` returns 404. The original [calendar cycle](../calendar/calendar_step1_solution_assessment.md) left export and sync out of scope; this cycle takes them up.

## Problem

Nobody can follow the ZenMemes calendar from Google Calendar or any other calendar app, because v3 publishes no iCal feed.

Concepts touched: [Calendar](../../concepts/calendar.md) (every option reads its event selection; Options A, B and D add a feed beside it, and the concept's Boundary line "no … export" changes), [Post event metadata](../../concepts/post_event_metadata.md) (read unchanged), [Date phrase](../../concepts/date_phrase.md) (read unchanged; the phrase goes into the event description), [Feature flags](../../concepts/feature_flags.md) (one new flag). Options A, B and D propose a "calendar feed" concept entry.

Feedback items: none ask for a feed. [PF-20261108-F02](../../feedback/items/PF-20261108-F02.txt) (only approved members put events on the calendar) is related; the feed must apply the same rule.

## Option A — Public feed for each calendar page
**Estimate: 0.30** — driver: turning event rows into valid iCal that Google accepts; uncertainty: how Google treats a start time with no time zone.

Add `/calendar.ics` and `/calendar/<tag>.ics`. Each returns the same events as the matching calendar page, through `CalendarQuery.php`: approved authors only, declined events left out. A date-only event becomes an all-day event that spans its Until date. An event with `Event-Time` starts at that time. The title, place, link, thread URL and `Event-When` phrase go into the event. The thread ID is the stable event ID, so edits update in place. A feature flag turns the feed off.

- Smallest change; no new records, storage or migration.
- One readable URL per calendar, in line with the readable-URL principle; `/calendar/couches.ics` follows from `/calendar/couches`.
- Every calendar app can use it, not only Google.
- Nobody finds the URL unless told, since no page links to it.
- `Event-Time` carries no time zone. The feed would send a "floating" time, which Google shows in each subscriber's own zone. That is right for local events and wrong for a reader in another zone.

## Option B — Option A plus a Subscribe link on calendar pages
**Estimate: 0.40** — driver: Option A, plus one small control on the grid page; uncertainty: the same time-zone question.

Same feed as Option A. Each calendar page also shows a quiet Subscribe link. It offers the feed URL to copy, a `webcal://` link that opens Apple Calendar and similar apps, and an "Add to Google Calendar" link that opens Google with the feed filled in.

- Answers the original question with two taps from the calendar page, on iPhone too.
- Tag calendars get their own Subscribe link, so a couch host can share just the couch calendar.
- Adds one control to a page whose phone layout is still being worked on.
- Google refreshes subscribed feeds on its own schedule, often hours apart. The page should say so, or new events will look missing.

## Option C — "Add to Google Calendar" on each event
**Estimate: 0.20** — driver: building one Google link per event; uncertainty: low.

Put an "Add to Google Calendar" link in each event block, using Google's event template link. Optionally offer a one-event `.ics` download beside it.

- Very small, and useful for "put this one event on my calendar".
- Does not answer the question: the copy never updates, and new events never appear.
- Google-only unless the `.ics` download is added.

## Option D — Private feed for each member
**Estimate: 0.80** — driver: secret tokens, where to keep them, and how to reset one; uncertainty: what a personal calendar should contain.

Give each member a secret feed URL that holds their own events, or events they marked as going. The token in the URL stands in for a sign-in, since Google cannot log in.

- The only option that could carry members-only or personal events.
- v3 has no personal calendar or "going" mark yet, so this cycle would invent one.
- A secret token in a URL is the kind of opaque URL state the project avoids without a strong reason. Today every calendar event is public, so there is no such reason yet.

## Recommendation (original, superseded by Revision 1)

Option B. Option A alone works but stays hidden, and Option B's link is the part that actually answers "how do I add it". The vertical slice is whole: Ilya opens `/calendar` on his phone, taps Subscribe, adds it to Google, and a newly confirmed event appears there after Google's next refresh. Ship the feed and the link behind one feature flag, on by default where event support is on. Option C can follow later as a separate small cycle. Option D waits until v3 has personal or members-only events.

Questions for Step 2, with proposed defaults:

- Times: send floating times now. A site time zone setting, so times become exact, is a separate later cycle.
- Open (unsettled) events: include them, marked tentative; confirmed events marked confirmed.
- Range: events from 90 days ago onward, so the feed stays small.

Enhancement requests: none beyond the original question.

## Revision 1 — combine B and C

Maintainer, 2026-11-17: "b+c please"

Revised direction: ship the subscribable feed with its Subscribe link, and also let a reader add one event at a time. Options B and C fit together. Both read the same event fields, and the one-event file reuses the feed's iCal writer.

### Option B+C — Feed with Subscribe link, plus "Add to calendar" on each event
**Estimate: 0.50** — driver: Option B, plus per-event links in the event block; uncertainty: how Google treats a start time with no time zone.

Everything in Option B: `/calendar.ics` and `/calendar/<tag>.ics`, and a Subscribe link on each calendar page. Each event thread page also gets an "Add to Google Calendar" link that opens Google's new-event form already filled in. Beside it sits a one-event `.ics` download for other calendar apps, at a readable URL such as `/threads/<thread_id>.ics`.

- Covers both needs: follow the whole calendar, or keep one event.
- One iCal writer serves the feed and the one-event file, so the two cannot disagree.
- The event block appears on thread pages, board rows and cards. The links belong on the thread page only, so rows and cards stay uncluttered.
- An event added one at a time never updates. The thread page should keep pointing at the feed for anyone who wants changes.
- One feature flag covers both parts, so an operator turns them on or off together.

### Recommendation (Revision 1)

Option B+C as one cycle, still well under one slice. The vertical slice: Ilya opens `/calendar` on his iPhone, taps Subscribe and adds the feed to Google. Then he opens an event thread, taps "Add to Google Calendar", and Google's event form opens filled in. The Step 2 questions above still stand, with one more: per-event links on the thread page only (proposed default) or also on day pages.

*— Revised by Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Study snapshots

- Intake (after code inspection; no pristine intake): 0.40 projected total for the recommended scope. Baseline `1446b8a5b06f660e016f4486a06ffdf66bd37a9b`, scope v1, R1, P2; Claude single exposed rater, 2026-11-17 UTC. R1 hashes not re-verified this session.
- Step 1 handoff: 0.40 for Option B. Options A 0.30, B 0.40, C 0.20, D 0.80; not summed.
- Revision 1 (2026-11-17 UTC, scope v2: B and C combined): 0.50 for Option B+C. Options A 0.30, B 0.40, C 0.20, D 0.80, B+C 0.50; not summed. Changed scope, not new understanding; original values retained above.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
