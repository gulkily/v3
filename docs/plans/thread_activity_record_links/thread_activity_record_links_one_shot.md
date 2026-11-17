Cycle: `v3/thread_activity_record_links`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/thread_activity_record_links`; [cycle ledger](../effort_ledger/thread_activity_record_links.md); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.12**
Status: implemented and verified locally; next permitted action: push and PR (merging and deploying are the maintainer's)

Predecessor: [`v3/thread_activity_drawer`](../thread_activity_drawer/thread_activity_drawer_step4_implementation_summary.md), merged in #246.

## Original request

2026-11-15, after #246 was merged: "And unless I'm missing something, there's no way to go to the item that added the like from the drawer." I replied with two options: (1) look up each reaction's record when the page renders, or (2) store `record_id` in `viewer_tags`. I recommended option 1.

## Authorization

The maintainer then wrote: "I already merged the PR, so it would have to be a new cycle or a one shot." I read this as approval to do option 1, the one I recommended, as a one-shot on a fresh branch from `origin/main`, ending with a pushed branch and a PR. Merging and deploying are not included. I said so in my reply before starting.

## Outcome and work type

Application change. Normal entry: `/threads/<id>` with `FORUM_THREAD_ACTIVITY_DRAWER_ENABLED` and `FORUM_POST_RECORDS_ENABLED` both on.

Outcome: in the drawer's "Liked by …" and "Flagged by …" lists, each name links to the record that added that like or flag, at `/records/<Record-ID>`. If an actor has more than one current add record for the same post and tag, the newest one is used.

Unchanged:
- With Post records off, names still link to profiles, because `/records/` pages exist only behind that flag.
- When no record is found for an actor, the name links to the profile.
- Tag-only entries ("ann tagged #bug") keep their profile link; their date already links to the reply.
- The signal lines on post cards are untouched.

Excluded: storing record IDs in the read model (option 2), and any change to the post page's Records list.

## Approach and risks

The controller looks up record IDs only when both flags are on and the thread has at least one like or flag to show. It makes one `git grep` per record directory, covering all the thread's post IDs together: `records/post-reactions` matches `Target-ID`/`Post-ID` lines, and `records/thread-labels` matches the root's `Target-ID`/`Thread-ID` lines, for thread likes. It returns a map from target, tag and author identity to record ID. `ThreadActivity::build` attaches a `record_id` to each matching actor, and the partial links names that have one.

Risks:
- **Page cost.** The thread page stops running only on read-model data when both flags are on. Impact: two `git grep` runs per view. Mitigation: they run only with both flags on and something to show; `PostRecordList` already does the same per post page. Validation: time the lookup on a fixture against the page.
- **A wrong record linked.** Impact: a name points at a removed or older like. Mitigation: retracted records are deleted, so grep only finds live files; use `add` operations only, newest `Created-At` first.
- **Lookup failure.** Impact: a broken drawer. Mitigation: grep errors already return no matches in `PostRecordList::grep`; the template's `partition()` fallback still applies; an empty map means profile links.

## Reuse decisions

- Record lookup: extend `Canonical/PostRecordList.php` with a public batch method that reuses its `grep()` and record parsing, rather than a second grep helper. Its pointer lines (`Target-ID`/`Post-ID` for reactions, `Target-ID`/`Thread-ID` for root thread labels) are the same.
- Actors: `ReadModel/SignalActors.php` output, unchanged; matched on `identity_id`.
- Entries and markup: `View/ThreadActivity.php` and `templates/partials/thread_activity_drawer.php` from the predecessor.
- Links: `/records/<Record-ID>` from `ThreadAndPostPageController::record`.

## Completion conditions

- With both flags on, each "Liked by"/"Flagged by" name in the drawer opens that actor's record page.
- With Post records off, or with no record found, the drawer is as before (profile links).
- A lookup failure leaves profile links and no error.
- No schema change, rebuild or new dependency. Handoff: pushed branch and an open PR; production check after merge is the maintainer's.

## Concepts touched

[Thread activity](../../concepts/thread_activity.md) (changed: names link to records when Post records is on); [post record list](../../concepts/post_record_list.md) (used; the batch lookup is noted in its Sources).

## Verification and recovery plan

- Unit: `PostRecordList` batch lookup on a fixture with a post like, a post flag, a thread like, a non-`add` record and two adds by one actor (newest wins).
- `ThreadActivityTest`: actors get `record_id` from the map; no map means no `record_id`.
- `PostSignalsTemplateTest` drawer test: with both flags on, names link to `/records/<id>` and the record page opens; with Post records off, profile links.
- Timing of the lookup on the fixture, recorded here.
- Smoke set plus changed tests; full suite not run, per `AGENTS.md`.

## Starting revisions and branch

Host `main` at `c54c3375d` (merge of #246); branch `feat/thread-activity-record-links`.

## Planning commit

`d81d7ee36` (plan and index entry only; confirmed with `git log` before implementation).

## Dated execution history

- 2026-11-15, planning commit `d81d7ee36`, then implementation on `feat/thread-activity-record-links`.
- **Discovery (correction to the approach):** the planned lookup, one anchored-regex `git grep` per directory with a pattern per post ID, was slow on a synthetic repository of 13,000 reaction and thread-label records. It took 132 ms for a 10-post thread and 1,085 ms for a 200-post thread; the regex was the cost, not parsing (50 records parse in about 1 ms). A fixed-string search with a pattern per post ID was still about 620 ms at 200 posts. The final lookup makes a single `git grep -F` call for the header names alone (`Target-ID: `, `Post-ID: `, `Thread-ID: `) across both directories, then keeps whole-line matches in PHP. It took **85 ms at 10 posts and 110 ms at 200**. About 50 ms of that is Git scanning the directory, so the cost grows with the total number of records, not the thread's size. `PostRecordList::grep` is unchanged; the new helper `grepHeaderLines` is used only by the batch lookup.
- Recovery addition: the controller wraps the lookup in a `try`, so any failure leaves profile links. `grepHeaderLines` already returns nothing when Git finds no match or fails.
- No enhancement requests.

## Changes

- `Canonical/PostRecordList.php`: `reactionRecordIds()` (batch lookup; `add` only, newest wins, targets limited to the thread's posts), `reactionKey()`, and the private `grepHeaderLines()`.
- `Http/ThreadAndPostPageController.php` (`thread`): computes the map only when the drawer and Post records flags are on and a like or flag is shown, and passes `threadReactionRecordIds`. It reads both flags once.
- `View/ThreadActivity.php`: `partition()`/`build()` take the map and add `record_id` to matching actors.
- `templates/partials/thread_activity_drawer.php`: like and flag names link to `/records/<Record-ID>` when there is a record, otherwise to the profile.
- `templates/pages/thread.php`: passes the map.
- Tests: `PostSignalsTemplateTest::testThreadActivityDrawerLinksEachReactorToTheRecordThatAddedIt` (new), `ThreadActivityTest` (record assertions).
- Concepts: [thread activity](../../concepts/thread_activity.md) and [post record list](../../concepts/post_record_list.md).

## Outcome

Delivered as scoped. With both flags on, each name in "Liked by"/"Flagged by" opens the record that added it. With Post records off, or with no record found, names link to profiles as before. Tag-only entries and post-card signal lines are unchanged.

Limitation: about 85–110 ms per thread view against 13,000 records, when both flags are on and the thread has a like or flag to show. The cost grows with the size of the record directories. If that is too slow in production, the next step is to store `record_id` in `viewer_tags` (option 2), or to load the links when the drawer is opened.

## Verification

- New lookup test: thread like, post like, post flag, an older and a newer like by the same actor (newer wins), a like on a post outside the thread (ignored), and a later `remove` record (ignored). The empty post list returns nothing.
- Page test: with both flags on, the drawer links each name to its record, never the older like, and `/records/post-reaction-link-new` opens. With Post records off, the drawer has no `/records/` links.
- `ThreadActivityTest`: record IDs attach only to the matching actor and tag, and no map means no `record_id`.
- Smoke set plus `PostSignalsTemplateTest`, `ThreadActivityTest`, `TagOnlyLeafFilterTest`, `ThreadLabelsTest` and the drawer page test: 478 of 478 passed. The full suite was not run, per `AGENTS.md`.
- Timing on the synthetic repository, as above; the repository was removed afterwards.
- No browser check: the change adds `href`s to existing links inside the drawer's list, with no new markup, CSS or script. The predecessor's theme checks cover the list.
- No production check: that is the maintainer's after merge.

## Final review

I reviewed `d81d7ee36..` for scope, duplication and docs.
- All files are within the plan.
- `grepHeaderLines` overlaps with `grep`, but it searches differently (fixed header names, whole lines checked in PHP). I didn't switch `grep`'s other callers to it, because that would change the post page outside this scope.
- No feedback item matches, so there is no `Feedback:` trailer.

## Commit/merge/publication status

Planning `d81d7ee36`; completion `a47b82dad`; pushed to `origin/feat/thread-activity-record-links`; PR opened (see the handoff).

## Study history

- 2026-11-15T20:58:00Z, intake: **0.12**, recorded before implementation. Not pristine: I rated it after reading `PostRecordList`, `SignalActors` and `viewer_tags`, and after describing option 1 at 0.10–0.15. Scope v1 as above. R1, P2, single exposed Claude rater.
- Execution entry: 0.12 reaffirmed after the planning commit.
- 2026-11-15T21:40:00Z, closeout: retrospective **0.15** (not a measured actual), up from intake because of the timing discovery and the rewritten lookup. Scope v1 delivered unchanged. Production check, full suite and maintainer judgment are pending, so there is no accepted-count increment.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
