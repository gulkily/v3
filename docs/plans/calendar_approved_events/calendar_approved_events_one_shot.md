# Calendar approved events — one-shot

Cycle: `v3/calendar_approved_events`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](calendar_approved_events_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/calendar_approved_events`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.06**

Status: technically complete on `feat/calendar_approved_events`; pushed for review, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Feedback item: [PF-20261108-F02](../../feedback/items/PF-20261108-F02.txt), created for this cycle in the completion commit (no existing item matched).

Original request, 2026-11-08 (https://zenmemes.com/threads/thread-20261109001832-f640d603, filed by `claude` for the maintainer): adding items that show up on the calendar should be a separate permission from posting.

Authorization, 2026-11-08, in a Claude session: asked whether this was a one-shot, Claude answered that "only approved identities can put things on the calendar, with a read-time filter" is a one-shot and a separate per-feature grant is not. The maintainer replied: "Yes, let's just do that for now. But add another feature request for a for a per-feature permission system." This authorizes the approval-gated slice through an open PR and a thread reply. Exclusions: merge, deployment, a new permission type.

## Outcome and work type

Application change (read model query). `/calendar`, `/calendar/<tag>` and their day pages list an event only when its thread's author is an approved member (approved at retirement, for a retired key) and the post's attribution counts toward that author. Other events are still stored and still show in their thread's event block; they are just absent from the calendar. If the author is approved later, the event appears with no rewrite.

## Approach and reuse decisions

- **Read-time filter in `src/ForumRewrite/Calendar/CalendarQuery.php`**, the one query behind every calendar page (`CalendarPageController` grid and day). This covers every write path (unsigned compose, prepare/create, single-request signed posts) and records arriving by sync or import, and it follows approval changes, all without a rebuild.
- **Reuse.** The approval expression already selected in that query as `author_is_approved` (the same `retired_at ? was_approved : is_approved` rule used by board and thread queries), and `posts.attribution_counted`, which `VerifiedAttribution` already maintains so that unverified posts naming an approved member stop counting for that member.
- **Why `attribution_counted`.** Unsigned posts can name any `author_identity_id`. Without this condition, an unsigned post naming an approved identity would reach the calendar. With it, the calendar follows the site's verified-attribution setting exactly as profile counts do.
- **Considered and rejected for this slice:** rejecting event fields at write time (what images do in `assertImageAuthor`). It needs changes on four write paths plus a pre-paint eligibility check in the composer like `image_upload.js`, and it still wouldn't cover synced records. It may be worth doing later so unapproved members aren't surprised; listed as a follow-up.
- **Follow-up, separate cycle:** per-feature permission grants (https://zenmemes.com/threads/thread-20261109011357-ce8c5503).
- No new dependency, route, table, schema change or UI copy.

## Concepts touched

None in the concept inventory.

## Risks

- An unapproved member who adds an event sees it on their thread but not on the calendar, with no explanation. Accepted for this slice (the composer and day page are unchanged); the write-time follow-up would address it.
- Existing events by unapproved or unverified authors disappear from the calendar on deploy. Intended.
- Test fixtures create events as anonymous unsigned threads; they need an approved author.

## Completion conditions and verification plan

- Normal entry: `/calendar`, `/calendar/<tag>`, `/calendar/<date>`.
- End to end: an event by the approved fixture operator appears; an anonymous event and one by an unapproved identity do not; the unapproved one still shows its event block on its thread page.
- Recovery: approving the author later makes the event appear on the next render (read-time check).
- Checks: `./v3 test --smoke CalendarPageTest` plus any test that renders calendar output. Full suite not run (maintainer runs it).

Starting revision and branch: `fac52df649aed789757c1ae5eb88306362a9945d` on `main`; branch `feat/calendar_approved_events`.

Planning commit: `3f595fd312dafe79fdc0b29dfb905ce8fec5c959` (2026-11-09T01:15:56Z), containing only this record and its index link.

## Dated execution history

- 2026-11-09T01:15Z: chronology disclosure. Before writing this plan, Claude edited `CalendarQuery.php` directly in the working tree. On reading the one-shot gate, it stashed that edit uncommitted, created the branch, and wrote this plan; the edit is reapplied only after the planning commit.
- Correction, 2026-11-09T01:19Z: the plan as committed stamped its entries 01:20Z, ahead of the actual planning commit at 01:15:56Z. Entries here now use the commit time.
- 2026-11-09T01:16Z: reapplied the `CalendarQuery` filter. Test fixtures that created events anonymously now author them as the approved fixture operator (`CalendarPageTest::event()` defaults to it; the no-JavaScript day-page post sends it). Added `testOnlyApprovedMembersPutEventsOnTheCalendar`.
- 2026-11-09T01:18Z: discovery. `tests/browser/calendar.mjs` posts from a freshly generated, unapproved browser key and from anonymous no-JavaScript and API posts. Updated it to assert the signed post is off the calendar, approve its key with `scripts/inject_approval.php approve` (the existing operator path), then assert it appears; the anonymous no-JavaScript post now asserts it stays off the calendar; the five "busy" API posts name the operator. Correction to the test plan, not a scope change.
- Request in the same message (2026-11-08): file a feature request for a per-feature permission system. Disposition: done outside this cycle as https://zenmemes.com/threads/thread-20261109011357-ce8c5503; no implementation here.

## Study history

- Intake, 2026-11-09T01:15Z: **0.06** whole-cycle projection, scope v1, R1/P2. Exposure: recorded after inspecting `CalendarQuery`, `LocalWriteService` and the calendar tests, so this is not a blind intake; a true pre-inspection intake is missing. Single exposed rater (Claude).
- Execution entry, 2026-11-09T01:15Z: 0.06 reaffirmed against the outcome, approach and verification above.
- Closeout, 2026-11-09T01:20Z: delivered scope v1 as planned. Retrospective scope assessment **0.08**: the browser test needed a new approve-then-appear flow. Maintainer fit and acceptance pending.

## Outcome

Delivered: the calendar grid, tag calendars and day pages list only events by approved members whose post counts toward them, checked at read time. Event data is still written for everyone and still shows on the thread. Not delivered (follow-ups): write-time feedback for unapproved members who add an event; per-feature grants (separate thread above).

## Verification

- `./v3 test --smoke CalendarPageTest`: all 9 `CalendarPageTest` tests pass, including the new one; smoke set 448 of 449, the one failure being `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, which fails identically on unmodified `main` (`fac52df`).
- `./v3 test EventStatusTest`: 3 of 3.
- `CHROMIUM_PATH=/opt/google/chrome/chrome node tests/browser/calendar.mjs`: ok (signed post off the calendar until its key is approved, then on; anonymous no-JavaScript post off; phone and desktop layout; all themes).
- `node tests/browser/nav_slots.mjs`: ok.
- `./v3 feedback check`: 0 problems; `./v3 feedback index` regenerated.
- Not verified: full suite (maintainer runs it); the `attribution_counted` condition has no dedicated test with verified attribution enabled and a cutoff set, though it reuses the column profile counts already rely on; no production replay.

## Final review

Diff limited to `CalendarQuery.php`, the two calendar tests, this record and index line, the feedback item and index, and the study ledger. No duplicated approval logic: the query reuses its existing approval expression via one constant. No UI copy, schema, route or dependency change. No concept inventory entries touched.

## Commit, merge and publication status

Planning commit `3f595fd`; completion commit pending at time of writing (reference added in the PR). Pushed for review; not merged or deployed.

## Attribution

Claude (claude-opus-5-5), 2026-11-09 UTC.
