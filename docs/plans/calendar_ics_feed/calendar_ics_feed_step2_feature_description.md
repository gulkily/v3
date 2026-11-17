# Calendar iCal feed Step 2 feature description

> **Feature plan:** [Step 1](./calendar_ics_feed_step1_solution_assessment.md) · [Step 2](./calendar_ics_feed_step2_feature_description.md) · [Step 3](./calendar_ics_feed_step3_development_plan.md) · [Step 4](./calendar_ics_feed_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/calendar_ics_feed`; [host ledger](../effort_ledger/calendar_ics_feed.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.50**

Approved direction: Step 1 Revision 1, Option B+C. The maintainer replied "approved step 2" while Step 1 was awaiting approval (2026-11-17). We read it as approval of Step 1 Revision 1. This Step 2 is new and still needs its own approval.

## Problem

Nobody can follow the ZenMemes calendar from Google Calendar or another calendar app, and nobody can put one event into their own calendar. The calendar exists only as HTML pages.

## User stories

- As a member, I want to subscribe to the ZenMemes calendar from Google Calendar so that new and changed events show up there without copying.
- As a host of a tag calendar such as `/calendar/couches`, I want a feed for just that tag so that I can share it on its own.
- As a reader of an event thread, I want to add that one event to Google Calendar in one tap so that I don't retype it.
- As a reader who uses Apple Calendar or another app, I want a standard `.ics` file or feed so that I am not tied to Google.
- As an operator, I want one flag to turn all of this off so that a bug or an unwanted feature can be switched away.

## Core requirements

- `/calendar.ics` and `/calendar/<tag>.ics` list the same events as the matching calendar page: approved authors only, declined events left out. Open events are marked tentative and confirmed events confirmed. The feed covers events from 90 days ago onward.
- Each event carries its title, dates (all-day when there is no time), start time, place, link, the `Event-When` phrase when shown, and the thread URL. The thread ID is the event's stable ID, so an edit or a confirmation updates the subscriber's copy.
- Times go out without a time zone, so a calendar app shows them as written, in the subscriber's own zone.
- Each calendar page shows a quiet Subscribe link. It offers the feed URL to copy, a `webcal://` link, and an "Add to Google Calendar" link.
- Each event thread page shows "Add to Google Calendar" and a one-event `.ics` download, at `/threads/<thread_id>.ics`. Board rows, cards and day pages stay as they are.

## Delivery scope

Work type: application change. It covers the two feed routes, the one-event route, the Subscribe link, the per-event links, one feature flag, the calendar reference doc, and the concept entries. No record, header or read-model change, and no new dependency.

## Completion boundary

- Normal entry: the Subscribe link on `/calendar` or `/calendar/<tag>`; the event links on an event thread page.
- End-to-end: Ilya subscribes from `/calendar`, an approved member posts a new event, and the event appears in Google Calendar after Google's next refresh. Separately, "Add to Google Calendar" on a thread opens Google's event form filled in.
- Recovery: the Subscribe panel says Google refreshes on its own schedule, often hours apart. If the Google link fails on a device, the copyable URL can be pasted into Google Calendar on the web under "Other calendars → From URL".
- Release condition: the flag ships on wherever event support is on. Ilya tries it on zenmemes.com after deploy.

## Known user or environment

Ilya on zenmemes.com, using an iPhone SE (3rd gen) and Google Calendar. Entry: he taps Subscribe on `/calendar` on the phone and adds the feed to his Google account. Failure handled: the Google link opens the Google Calendar app, which may not accept a subscription link. Next action without help: copy the feed URL from the panel and add it at calendar.google.com, which the panel explains. Verification: a known ZenMemes event appears in Google Calendar on the phone. Evidence: Google Calendar on Ilya's phone showing a ZenMemes event, plus the feed checked with a standard iCal validator. A desktop test alone does not satisfy this.

## Risks

- **Google on iPhone**: Google's subscribe link may hand off to the app and fail. Impact: the main path breaks on Ilya's own phone. Validate first, before the Subscribe panel is built, by trying the link on an iPhone. Mitigate with the copyable URL and plain steps for the Google web page.
- **Floating times**: Google may read a zone-less time in a feed as UTC rather than local. Impact: every timed event shows hours off. Validate early with one hand-written feed in Google. If Google shifts times, fall back to the site's default zone, or to all-day events with the time in the text.
- **Route clash**: `/threads/<id>` today matches any segment, so `abc.ics` would be read as a thread ID. Impact: the one-event file returns 404 or a wrong page. Mitigate by matching `.ics` before the thread route, with a test.
- **Members-only instances**: an instance in members-only mode puts calendar pages behind sign-in, and Google cannot sign in. Impact: the feed fails there. This cycle keeps the feed behind the same gate and says so in the docs. A private feed is Step 1 Option D, for later.

## Shared component inventory

- `src/ForumRewrite/Calendar/CalendarQuery.php` `eventsBetween`: the one event selection, with its approval rule. The feed reuses it and adds the link and phrase columns it needs, so the feed and the pages never disagree.
- `src/ForumRewrite/Calendar/EventStatus.php` `forThreads` and `forThread`: open, confirmed and declined. Reused for the declined filter and the tentative or confirmed mark.
- `src/ForumRewrite/Http/CalendarPageController.php`: grid and day pages, and `basePath` for tag paths. Extended with the feed responses and the Subscribe link, or a small sibling controller if that reads better.
- `templates/partials/event_block.php`: the event display shared by thread pages, rows and cards. Left unchanged. The per-event links go into `templates/partials/thread_root_card.php`, the thread page only.
- `templates/pages/calendar.php`: the grid page. It gets the Subscribe link.
- `src/ForumRewrite/Application.php`: the `/calendar` and `/threads/<id>` routes, `isApplicationRoute` and `membersOnlyRequestAllowed`. New routes join both lists.
- `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php`: one new flag, "Calendar feed", requiring `FORUM_EVENT_SUPPORT_ENABLED`, like `FORUM_EVENT_WHEN_ENABLED`.
- New: one iCal writer used by both the feed and the one-event file. Nothing in v3 writes iCal today.

## User flow

1. Ilya opens `/calendar` on his phone and taps Subscribe.
2. The panel shows the feed URL, an Apple Calendar link and an "Add to Google Calendar" link.
3. He taps the Google link and confirms in Google Calendar.
4. A member posts an event; after Google's refresh it appears in his Google Calendar.
5. On another event's thread page he taps "Add to Google Calendar", and Google's event form opens filled in.

## Success criteria

- `/calendar.ics` and `/calendar/couches.ics` pass an iCal validator and list exactly the events that the matching pages show for the same dates.
- A subscribed Google Calendar on Ilya's iPhone shows a ZenMemes event with the right date, time and title.
- A confirmed event shows as confirmed; a declined event leaves the feed.
- "Add to Google Calendar" opens a filled form; `/threads/<thread_id>.ics` imports into Apple Calendar as one event.
- With the flag off, the routes return 404 and no links show, as on `main`.

Concepts touched: [Calendar](../../concepts/calendar.md) (its Boundary drops "export"), [Post event metadata](../../concepts/post_event_metadata.md) (read only), [Date phrase](../../concepts/date_phrase.md) (the phrase goes into the description), [Feature flags](../../concepts/feature_flags.md) (one flag). Proposed inventory addition: "Calendar feed" (iCal views of the calendar and of one event, for subscribing and adding).

Enhancement requests: none pending. A site time zone setting stays a later cycle.

Change from Step 1 defaults: none. Floating times, tentative open events, the 90-day window and thread-page-only links are all kept.

## Study snapshots

- Step 2 handoff, 2026-11-17 UTC: 0.50 projected total reaffirmed for scope v2. Claude single exposed rater; R1 hashes not re-verified this session.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
