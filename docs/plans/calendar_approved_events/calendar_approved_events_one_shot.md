# Calendar approved events — one-shot

Cycle: `v3/calendar_approved_events`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](calendar_approved_events_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/calendar_approved_events`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))
Extended estimates: enabled (new-cycle default; no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.06**

Status: planned on `feat/calendar_approved_events`; next permitted action: implement and verify.

## Request and authorization

Feedback item: PF-20261108-F02, created for this cycle in the completion commit (no existing item matched).

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

Planning commit: pending.

## Dated execution history

- 2026-11-09T01:20Z: chronology disclosure. Before writing this plan, Claude edited `CalendarQuery.php` directly in the working tree. On reading the one-shot gate, it stashed that edit uncommitted, created the branch, and wrote this plan; the edit is reapplied only after the planning commit.

## Study history

- Intake, 2026-11-09T01:20Z: **0.06** whole-cycle projection, scope v1, R1/P2. Exposure: recorded after inspecting `CalendarQuery`, `LocalWriteService` and the calendar tests, so this is not a blind intake; a true pre-inspection intake is missing. Single exposed rater (Claude).
- Execution entry, 2026-11-09T01:20Z: 0.06 reaffirmed against the outcome, approach and verification above.
