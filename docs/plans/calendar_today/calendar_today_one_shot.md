# Calendar today — one-shot

Cycle: `v3/calendar_today`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](calendar_today_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/calendar_today`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.08**

Status: technically complete on `feat/calendar-today`; pushed, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Feedback item: [PF-20261108-F01](../../feedback/items/PF-20261108-F01.txt), created for this cycle (no existing item matched).

Original request, 2026-11-08 (https://zenmemes.com/threads/thread-20261108034244-8b24dcc8, by ilyag): "I would like for the calendar to highlight the current date and also to have a button to go to the month with the current date if it's not already selected".

Authorization, 2026-11-08: the maintainer pasted the thread link into a session with no other words. Under [thread to pull request](../../agents/thread_to_pr.md) the paste authorizes a one-shot cycle through an open PR and a reply in the thread. Exclusions: merge, deployment.

## Outcome and work type

Application change (UI). On `/calendar` and `/calendar/<tag>`, today's square carries a ring and a bold day number, and its day link gets `aria-current="date"`. When neither shown month contains today, a Today link appears between "← Earlier" and "Later →" and opens today's month.

## Approach and reuse decisions

- **Whose today.** The viewer's own clock and time zone, not UTC. The requester is in New York; at the time of the request it was already Nov 8 in UTC and still Nov 7 locally. Marking the UTC date would mark the wrong square for most evenings in the Americas.
- **Static markup.** CONTRIBUTING requires server HTML identical for every viewer with viewer-specific UI decided before first paint in the client. The server renders the Today link `hidden` with the calendar's base path; a small inline script at the end of the calendar template (the same pattern as `templates/partials/visitor_intro.php`) runs during parsing, adds `calendar-day--today` to the matching `data-calendar-day` cell, or fills the link's `?month=` and unhides it. No server work, cookie or new asset file.
- **Reuse.** Existing `.calendar-nav` link row, existing `data-calendar-day` cell attribute, `--active-line` theme token (already used for confirmed days and hover). The ring is a 2px inset box-shadow, which keeps showing on hover (hover uses a 1px one).
- **Considered and rejected:** rendering the Today link server-side when the shown range excludes the UTC date (works without JavaScript, but disagrees with the viewer's date near midnight and puts date-dependent content in otherwise static markup).
- No new dependency, route, table or copy beyond the one word "Today".

## Concepts touched

None in the concept inventory.

## Risks

- Without JavaScript neither the mark nor the link appears; the calendar is otherwise unchanged. Accepted.
- The default month (no `?month=`) is still chosen by the server in UTC. Near a month boundary a viewer behind UTC may land on next month; the Today link then appears and fixes it in one click. Left as is.

## Verification and recovery plan

- `CalendarPageTest::testTodayLinkAndMarkAreLeftToTheViewersClock`: link present and hidden on all and tag calendars, no server-side today class, absent with events off; existing "same for every viewer" test still passes.
- Headless Chrome against `./v3 start` with events on: desktop and phone, New York and Tokyo time zones, current, January and October views, Today link click, six themes.
- Recovery: revert the commit.

Starting revision and branch: `d6e068623eb87bbe2bd268c397daaf70a54ad7aa` on `main`; branch `feat/calendar-today`.

## Study history

- **Intake, 2026-11-08T03:44Z, 0.08.** Single exposed rater: Claude (claude-opus-5-5). Pristine intake missing: the calendar controller, template, CSS and tests were read before rating. Scope v1 as above. Compared with R1's duplicate preview suppression (0.10).
- **Execution entry, 2026-11-08T03:45Z, 0.08 reaffirmed.** No new evidence.
- **Closeout, 2026-11-08.** Projection 0.08 reaffirmed; retrospective scope assessment **0.08**, same single exposed rater. Corrections: none. Additions: none. Delivered scope v1 in full. Maintainer fit and acceptance pending. No active-work measurement collected.

## Dated execution history

- 2026-11-08: record created; implemented template link and script, CSS, and test.
- 2026-11-08: verified in headless Chrome (see Verification).

## Outcome

Delivered as bounded. Limitations: needs JavaScript; production changes only after deployment.

## Verification

- Passed: `./v3 test CalendarPageTest`, 8 run, 8 passed.
- `./v3 test --smoke CalendarPageTest`: all passed except `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, which fails identically on `main` with this change stashed (pre-existing, unrelated).
- Passed, headless Chrome (`./v3 start` with `FORUM_EVENT_SUPPORT_ENABLED=1`): New York zone marks 2026-11-07 with `aria-current="date"` and keeps Today hidden at desktop 1100px and phone 390px; `?month=2026-01` shows Today linking to `?month=2026-11`, and clicking it lands on November with the 7th marked; `?month=2026-10` (November as second month) marks the 7th with Today hidden; Tokyo zone marks 2026-11-08. Ring checked in the dark, Word97, Midnight, Console, Chicago and Gloss themes; visible in each, no layout shift.
- Not run: the full suite (maintainer runs it). Not checked: other themes beyond those six.

## Commit, merge and publication status

Completion commit: pending (this record ships in it). Pushed to origin; not merged, not deployed.

*— Claude · 2026-11-08 UTC · Model: claude-opus-5-5*
