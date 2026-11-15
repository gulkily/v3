Cycle: `v3/thread_activity_record_links`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/thread_activity_record_links`; [host ledger](../effort_estimation_trial.md#cycle-v3thread_activity_record_links); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.12**
Status: planned; next permitted action: implement after the planning commit

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

Pending.

## Study history

- 2026-11-15T20:58:00Z, intake: **0.12**, recorded before implementation. Not pristine: I rated it after reading `PostRecordList`, `SignalActors` and `viewer_tags`, and after describing option 1 at 0.10–0.15. Scope v1 as above. R1, P2, single exposed Claude rater.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
