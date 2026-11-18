# Post approval label — one-shot

Cycle: `v3/post_approval_label`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](post_approval_label_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/post_approval_label`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/post_approval_label.md))
Extended estimates: enabled (new-cycle default; no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.08**

Status: technically complete on `feat/post_approval_label`; pushed for review, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Feedback item: none matched in `docs/feedback/items/` (closest is PF-20261116-F01, moving a guest post onto an identity, which stays separate). Created [PF-20261118-F01](../../feedback/items/PF-20261118-F01.txt) in the completion commit.

Original request, 2026-11-18 (Claude session): the maintainer needs to add a guest's post to the calendar. Claude explained that the calendar lists only events by approved members (cycle [calendar_approved_events](../calendar_approved_events/calendar_approved_events_one_shot.md)), so an anonymous or unsigned guest's event has no way on. The maintainer: "I think I rather want to add an approve or approved hashtag to the post as a general way of approving things made by unsigned posts."

Claude proposed: the label approves that one thread, not the author; it unlocks what is gated on an approved author at read time; it is added through the existing label paths and undone by the existing label retraction; any approved member may approve; `#approved` is the label, with `#approve` also accepted. The maintainer: "I love your plan. I think it's straightforward enough for one shot?" Claude agreed and named the defaults. This authorizes the one-shot through an open PR. Exclusions: merge, deployment, write-time gates, per-feature grants, a new record type.

## Outcome and work type

Application change (read-model queries). A thread labeled `#approved` (or `#approve`) by an approved, unretired member counts as approved wherever a thread is gated at read time on its author being approved. Today that is the calendar: `/calendar`, `/calendar/<tag>` and their day pages, plus the calendar feeds that use the same query. A retracted label stops counting on the next render.

Not covered, by design: write-time gates such as image uploads (`assertImageAuthor` rejects the image before any label could exist). The Prompts library already has its own approved-labeler rule for `#prompt` and is unchanged.

## Approach and reuse decisions

- **Reuse the Prompts library's approved-label rule** (`src/ForumRewrite/ReadModel/PromptLibrary.php`): the label must still be on the thread (`threads.thread_labels_json`, which drops fully retracted labels) and an approved, unretired member must have written it (`viewer_tags` row in `thread-labels` joined to `profiles`). Same shape, different tag.
- **One small shared helper**, `ApprovalLabel` in `src/ForumRewrite/ReadModel/`, holding the tag names and the SQL condition, so later read-time gates call one place. `CalendarQuery::eventsBetween` uses it as `author approved OR approval label`. Not folded into `PromptLibrary` in this cycle (its `#prompt` rule is a different tag with its own `#notprompt` counterpart).
- **Labels are written by the existing path**: a reply whose body carries the hashtag (`LocalWriteService::extractThreadLabelsFromBody`), which writes a `thread-labels` record; no write change. Only records with an author identity reach `viewer_tags`, so an unsigned reply's `#approved` is on the thread but never counts. (Correction: the plan first named `applyThreadTag`, which accepts only reactions such as like and flag.)
- **`#approve` is accepted as typed**, not rewritten to `#approved`: records keep what their author sent, and the check matches either tag. (Correction to the chat plan, which said `#approve` would be stored as `#approved`.)
- No schema change, route, dependency, UI copy or concept inventory change.

## Concepts touched

None in the concept inventory.

## Risks

- Any approved member can put any guest thread on the calendar. Intended (the maintainer's choice); retraction undoes it.
- `#approved` is a fairly generic word; a member tagging a thread `#approved` for another reason also puts its event on the calendar. Accepted; it only matters for event threads.

## Completion conditions and verification plan

- Normal entry: as an approved member, reply `#approved` to a guest event thread (the hashtag-only reply adds the label); it appears on `/calendar`, the tag calendar and its day page.
- End to end in `CalendarPageTest`: an anonymous event is off the calendar; an unsigned reply's `#approved` leaves it off; an approved member's `#approved` or `#approve` puts it on; an unapproved-again labeler takes it off again.
- Recovery: removing or retracting the label takes it off on the next render (read-time check).
- Checks: `./v3 test --smoke CalendarPageTest PromptLibraryTest`. Full suite not run (maintainer runs it).

Starting revision and branch: `31e16b066774dbdf8f4d3709ae4ad021bfff9516` on `main`; branch `feat/post_approval_label`.

Planning commit: `a8487ae536b5b20f268910f97372e6becfeadbd0` (2026-11-18T07:04:11Z), containing only this record and its index link.

## Dated execution history

- 2026-11-18T07:04Z: plan written after inspecting `CalendarQuery`, `PromptLibrary`, `ReadModelSchema` (`viewer_tags`), `ReadModelBuilder` label projection and `LocalWriteService::applyThreadTag`. No implementation edits yet.
- 2026-11-18T07:05Z: planning commit `a8487ae` confirmed. Added `ApprovalLabel` and the `OR` branch in `CalendarQuery::eventsBetween`, and `CalendarPageTest::testAnApprovedMembersApprovedLabelPutsAGuestEventOnTheCalendar`.
- 2026-11-18T07:07Z: discovery. The first test draft used `applyThreadTag`, which rejects topic labels ("tag must be one of: like, flag, upvote, downvote"). Switched to hashtag replies, the real path; added the unsigned-reply case. Correction to the test plan, not a scope change.
- Request, 2026-11-18T07:04Z (maintainer, mid-cycle): talk about label tenses; buttons should say Resolve, Approve, Close, while hashtags and states are undecided. Disposition: discussion, not in this cycle's scope. Claude suggested verbs on buttons, past participles for states and stored hashtags, with the verb accepted as a synonym (which this cycle already does for `#approve`). Approval status: none needed for discussion. Follow-up: a separate cycle for button wording on the reply-state row if the maintainer wants it.

## Study history

- Intake, 2026-11-18T07:04Z: **0.08** whole-cycle projection, scope v1, R1/P2. Exposure: recorded after inspecting the files above, so not a blind intake; a pre-inspection intake is missing. Single exposed rater (Claude).
- Execution entry, 2026-11-18T07:05Z: 0.08 reaffirmed.
- Closeout, 2026-11-18T07:10Z: delivered scope v1 as planned. Retrospective scope assessment **0.07**. Maintainer fit and acceptance pending.

## Outcome

Delivered: an approved, unretired member's `#approved` or `#approve` label puts a thread's event on `/calendar`, tag calendars, day pages and the calendar feeds (same query), whoever the author is. Checked at read time, so it follows the labeler's approval and label retraction. Not delivered: write-time gates (images) and per-feature grants; neither was in scope.

## Verification

- `./v3 test CalendarPageTest`: 16 of 16, including the new test.
- `./v3 test --smoke CalendarPageTest PromptLibraryTest EventStatusTest`: 501 of 501.
- `./v3 feedback check`: 0 problems; `./v3 feedback index` regenerated.
- Not run: the full suite (the maintainer runs it); `tests/browser/calendar.mjs` (no markup, script or style change, and the server-rendered list is covered by the PHP test); a real label retraction (the test empties `thread_labels_json`, the state a full retraction leaves); no production replay.

## Final review

Diff limited to `ApprovalLabel.php` (new), `CalendarQuery.php`, `CalendarPageTest.php`, this record and its index line, the feedback item and index, the study ledger and debug log. The condition mirrors `PromptLibrary`'s approved-label rule rather than inventing a new one; `PromptLibrary` itself is untouched. No UI copy, schema, route or dependency change. No concept inventory entries touched.

## Commit, merge and publication status

Planning commit `a8487ae`; completion commit `28ec4718` (implementation, record, item, ledger, debug log). Pushed as `feat/post_approval_label`; [PR #330](https://github.com/gulkily/v3/pull/330) open. Not merged or deployed.

## Attribution

Claude (claude-opus-5-5), 2026-11-18 UTC.
