# Post approval label — one-shot

Cycle: `v3/post_approval_label`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](post_approval_label_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/post_approval_label`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/post_approval_label.md))
Extended estimates: enabled (new-cycle default; no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.08**

Status: planned; next permitted action: implementation after the planning commit.

## Request and authorization

Feedback item: none matched in `docs/feedback/items/` (closest is PF-20261116-F01, moving a guest post onto an identity, which stays separate). A new item is created in the completion commit.

Original request, 2026-11-18 (Claude session): the maintainer needs to add a guest's post to the calendar. Claude explained that the calendar lists only events by approved members (cycle [calendar_approved_events](../calendar_approved_events/calendar_approved_events_one_shot.md)), so an anonymous or unsigned guest's event has no way on. The maintainer: "I think I rather want to add an approve or approved hashtag to the post as a general way of approving things made by unsigned posts."

Claude proposed: the label approves that one thread, not the author; it unlocks what is gated on an approved author at read time; it is added through the existing label paths and undone by the existing label retraction; any approved member may approve; `#approved` is the label, with `#approve` also accepted. The maintainer: "I love your plan. I think it's straightforward enough for one shot?" Claude agreed and named the defaults. This authorizes the one-shot through an open PR. Exclusions: merge, deployment, write-time gates, per-feature grants, a new record type.

## Outcome and work type

Application change (read-model queries). A thread labeled `#approved` (or `#approve`) by an approved, unretired member counts as approved wherever a thread is gated at read time on its author being approved. Today that is the calendar: `/calendar`, `/calendar/<tag>` and their day pages, plus the calendar feeds that use the same query. A retracted label stops counting on the next render.

Not covered, by design: write-time gates such as image uploads (`assertImageAuthor` rejects the image before any label could exist). The Prompts library already has its own approved-labeler rule for `#prompt` and is unchanged.

## Approach and reuse decisions

- **Reuse the Prompts library's approved-label rule** (`src/ForumRewrite/ReadModel/PromptLibrary.php`): the label must still be on the thread (`threads.thread_labels_json`, which drops fully retracted labels) and an approved, unretired member must have written it (`viewer_tags` row in `thread-labels` joined to `profiles`). Same shape, different tag.
- **One small shared helper**, `ApprovalLabel` in `src/ForumRewrite/ReadModel/`, holding the tag names and the SQL condition, so later read-time gates call one place. `CalendarQuery::eventsBetween` uses it as `author approved OR approval label`. Not folded into `PromptLibrary` in this cycle (its `#prompt` rule is a different tag with its own `#notprompt` counterpart).
- **Labels are written by the existing paths** (`LocalWriteService::applyThreadTag`, signed reactions, hashtag labels); no write change. `applyThreadTag` already requires an OpenPGP identity, so every label has an author to check.
- **`#approve` is accepted as typed**, not rewritten to `#approved`: records keep what their author sent, and the check matches either tag. (Correction to the chat plan, which said `#approve` would be stored as `#approved`.)
- No schema change, route, dependency, UI copy or concept inventory change.

## Concepts touched

None in the concept inventory.

## Risks

- Any approved member can put any guest thread on the calendar. Intended (the maintainer's choice); retraction undoes it.
- `#approved` is a fairly generic word; a member tagging a thread `#approved` for another reason also puts its event on the calendar. Accepted; it only matters for event threads.

## Completion conditions and verification plan

- Normal entry: label a guest event thread `#approved` as an approved member; it appears on `/calendar`, the tag calendar and its day page.
- End to end in `CalendarPageTest`: an anonymous event is off the calendar; an unapproved labeler's `#approved` leaves it off; an approved member's `#approved` or `#approve` puts it on; an unapproved-again labeler takes it off again.
- Recovery: removing or retracting the label takes it off on the next render (read-time check).
- Checks: `./v3 test --smoke CalendarPageTest PromptLibraryTest`. Full suite not run (maintainer runs it).

Starting revision and branch: `31e16b066774dbdf8f4d3709ae4ad021bfff9516` on `main`; branch `feat/post_approval_label`.

Planning commit: pending.

## Dated execution history

- 2026-11-18T07:04Z: plan written after inspecting `CalendarQuery`, `PromptLibrary`, `ReadModelSchema` (`viewer_tags`), `ReadModelBuilder` label projection and `LocalWriteService::applyThreadTag`. No implementation edits yet.

## Study history

- Intake, 2026-11-18T07:04Z: **0.08** whole-cycle projection, scope v1, R1/P2. Exposure: recorded after inspecting the files above, so not a blind intake; a pre-inspection intake is missing. Single exposed rater (Claude).

## Attribution

Claude (claude-opus-5-5), 2026-11-18 UTC.
