# Calendar iCal feed Step 3 development plan

> **Feature plan:** [Step 1](./calendar_ics_feed_step1_solution_assessment.md) · [Step 2](./calendar_ics_feed_step2_feature_description.md) · [Step 3](./calendar_ics_feed_step3_development_plan.md) · [Step 4](./calendar_ics_feed_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/calendar_ics_feed`; [host ledger](../effort_ledger/calendar_ics_feed.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.55**

Approved: Step 2 (2026-11-17). Concepts touched: [Calendar](../../concepts/calendar.md), [Post event metadata](../../concepts/post_event_metadata.md), [Date phrase](../../concepts/date_phrase.md), [Feature flags](../../concepts/feature_flags.md); new "Calendar feed" entry in Stage 6.

## Completion Contract

- Normal entry: the Subscribe link on `/calendar` and `/calendar/<tag>`; the event links on an event thread page.
- End-to-end: Ilya subscribes from `/calendar` on his iPhone, a new approved event appears in Google Calendar after Google's refresh, and "Add to Google Calendar" on a thread opens Google's filled event form.
- Recovery: the Subscribe panel explains Google's refresh delay and gives a copyable URL with steps for calendar.google.com, in case the Google link fails on the phone.
- Deployment: no record, read-model or dependency change. One new site-mutable flag. Google checks need the feed on a public HTTPS host, so Stage 1 uses a probe file and Stage 7 uses zenmemes.com.
- Release: the flag ships on wherever event support is on. Ilya deploys and replays the Step 2 check on his phone (Stage 7).

## Key Risks

- **High risk:** Google on iPhone. Impact: the main Subscribe path fails on Ilya's phone. Validation: Stage 1 probe, before Stage 5 builds the panel. Mitigation: the copyable URL with web steps; if the Google link never works on iPhone, the panel leads with the URL there.
- **High risk:** floating times. Impact: every timed event shows hours off in Google. Validation: Stage 1 probe, before Stage 2 fixes the time format. Mitigation: send times in the site's default zone, or send timed events as all-day with the time in the title. Either fallback is a Step 3 revision.
- Route clash. Impact: `/threads/<id>.ics` is read as a thread ID. Validation: a Stage 4 route test. Mitigation: match `.ics` before the thread route.
- Feed and page disagree. Impact: Google shows events the calendar hides, such as an unapproved author's. Validation: a Stage 3 test that compares the feed with the grid for the same dates. Mitigation: both read `CalendarQuery::eventsBetween` and `EventStatus::forThreads`.

## Stage 1

**Estimate: 0.05**

- Goal: settle both Google risks with a hand-written probe feed before any dependent code.
- Dependencies: none. Needs Ilya for about five minutes on his iPhone.
- Expected changes: `docs/plans/calendar_ics_feed/google_probe.ics` on the feature branch. It holds one timed event with no zone, one three-day all-day event, one tentative and one confirmed event. Ilya subscribes to its raw GitHub URL with the Google subscribe link on the phone, then with "From URL" on calendar.google.com. He also opens one Google event-template link.
- Verification approach: Ilya reports what Google shows: time as written or shifted, span length, status, and whether the subscribe link worked in the app. Results go in the Step 4 summary.
- Estimate details: driver: waiting on a manual check; uncertainty: whether Google fetches raw GitHub URLs promptly.
- Risks or open questions:
  - Impact: the probe shows a shifted time. Mitigation: stop and revise Step 3 with a fallback before Stage 2.
  - Impact: Google is slow to fetch. Mitigation: the "From URL" path on the web usually fetches at once.
- Canonical components/API contracts touched: none (probe file only).

## Stage 2

**Estimate: 0.12**

- Goal: one iCal writer that turns event rows into a valid calendar document.
- Dependencies: Stage 1 result on time format.
- Expected changes: new `src/ForumRewrite/Calendar/CalendarIcs.php` with `document(list $events, array $states, string $siteUrl, string $name): string`. Each event gets a UID from the thread ID and site host, DTSTAMP, all-day or floating start, an exclusive end day, STATUS, SUMMARY, LOCATION, URL and a DESCRIPTION with the phrase, link and thread URL. Text is escaped, lines folded at 75 octets, and lines end in CRLF. New flag `FORUM_CALENDAR_FEED_ENABLED` ("Calendar feed"), default on, requiring `FORUM_EVENT_SUPPORT_ENABLED`, in the AUTHORING group.
- Verification approach: new `tests/CalendarIcsTest.php`: one-day, multi-day, timed, tentative, confirmed, commas and newlines in text, long lines, non-ASCII titles. Check one output with a standard iCal validator. `./v3 test --smoke` plus the new test and the feature-flag tests.
- Estimate details: driver: iCal escaping and folding rules; uncertainty: low.
- Risks or open questions: Impact: folding splits a multi-byte character. Mitigation: fold by octets on character boundaries, with a test.
- Canonical components/API contracts touched: new `CalendarIcs` (nothing writes iCal today); `FeatureFlagRegistry.php`.

## Stage 3

**Estimate: 0.12**

- Goal: `/calendar.ics` and `/calendar/<tag>.ics` serve the feed.
- Dependencies: Stage 2.
- Expected changes: `CalendarQuery::eventsBetween` also selects `event_link` and `event_when`. New `CalendarPageController::feed(?string $tag): ?string` reads events from 90 days ago to two years ahead, drops declined ones, and calls `CalendarIcs`. The two routes in `Application.php` send `text/calendar; charset=utf-8` and 404 when the flag or event support is off. Both routes join `isApplicationRoute`; `membersOnlyRequestAllowed` stays unchanged, so members-only instances keep the feed behind sign-in.
- Verification approach: extend `tests/CalendarPageTest.php`: the feed lists exactly the grid's events for a shared month; an unapproved author's and a declined event are absent; the tag feed matches `/calendar/couches`; flag off returns 404. Fetch both URLs on a local server.
- Estimate details: driver: keeping the feed's selection identical to the grid's; uncertainty: low.
- Risks or open questions: Impact: a large range is slow. Mitigation: the same indexed query the grid uses; time it on the local fixture.
- Canonical components/API contracts touched: `CalendarQuery.php`, `EventStatus.php`, `CalendarPageController.php`, `Application.php` routes and `isApplicationRoute`.

## Stage 4

**Estimate: 0.10**

- Goal: an event thread page offers "Add to Google Calendar" and a one-event `.ics` download.
- Dependencies: Stage 2.
- Expected changes: a `/threads/<thread_id>.ics` route placed before the `/threads/<id>` route, returning one event through `CalendarIcs`, or 404 for a thread with no event. `thread_root_card.php` shows the two links after the event block on the thread page only, while the flag is on. A small helper builds the Google template link from the same fields.
- Verification approach: route tests for `.ics`, an ordinary thread ID and a thread with no event; a render test that rows and cards show no links. Open the Google link in a browser.
- Estimate details: driver: route order and the thread-page-only condition; uncertainty: whether `thread_root_card.php` knows it is on the thread page.
- Risks or open questions: Impact: links leak into board cards. Mitigation: pass an explicit thread-page flag, with a test.
- Canonical components/API contracts touched: `Application.php` `/threads/<id>` route, `templates/partials/thread_root_card.php`, `event_block.php` (unchanged), `CalendarIcs`.

## Stage 5

**Estimate: 0.10**

- Goal: calendar pages show a quiet Subscribe panel.
- Dependencies: Stages 1 and 3.
- Expected changes: `templates/pages/calendar.php` gets a collapsed disclosure that works without JavaScript. Inside: the feed URL as selectable text with a copy button, a `webcal://` link, the Google subscribe link, a line about Google's refresh delay, and the calendar.google.com steps. A tag calendar shows its own tag feed. The panel uses existing disclosure and copy-button styles.
- Verification approach: extend `tests/browser/calendar.mjs`: panel closed by default, opens, shows the right URL on `/calendar` and `/calendar/couches`, copy works; check on a 375-pixel-wide viewport and with JavaScript off.
- Estimate details: driver: fitting the panel into the phone layout, and moving the copy code; uncertainty: low.
- Risks or open questions: Impact: the panel crowds the phone grid. Mitigation: a single small link line until opened.
- Canonical components/API contracts touched: `templates/pages/calendar.php`, `public/assets/calendar.css`, and the copy behavior inline in `templates/pages/agent.php`. That code already handles iPhone Safari without the Clipboard API. Move it into a shared asset rather than write a second copy.

## Stage 6

**Estimate: 0.05**

- Goal: documentation and concepts match the delivered behavior.
- Dependencies: Stages 3 to 5.
- Expected changes: a "Subscribing" section in `docs/reference/calendar.md`; drop "export" from the Calendar concept's Boundary; new `docs/concepts/calendar_feed.md`; mention the feed in `docs/reference/agent_api.md` events.
- Verification approach: check links and paths; `git diff --check`.
- Estimate details: driver: none; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: calendar reference and concept docs.

## Stage 7

**Estimate: 0.03**

- Goal: replay the Step 2 known-user check on zenmemes.com.
- Dependencies: Stages 1 to 6, and Ilya's deploy.
- Expected changes: none beyond the Step 4 summary.
- Verification approach: Ilya subscribes from `/calendar` on his iPhone, posts or confirms a test event, and sees it in Google Calendar after refresh; he taps "Add to Google Calendar" on its thread. Evidence: his report or screenshot, recorded as pending until it arrives.
- Estimate details: driver: Google's refresh delay; uncertainty: refresh may take hours.
- Risks or open questions: Impact: the check outlasts the session. Mitigation: mark it pending in the summary; technical completion is recorded separately.
- Canonical components/API contracts touched: none.

## Study checkpoint

- Step 3 handoff, 2026-11-17 UTC: projected total 0.50 → 0.55. New understanding, same scope v2: the probe stage and the route-order fix. Stage work 0.05/0.12/0.12/0.10/0.10/0.05/0.03, not summed. Claude single exposed rater; R1 hashes not re-verified this session.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
