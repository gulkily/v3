# Feedback triage

Turn new site feedback into proposed changes to [feedback items](../feedback/README.md), show them for review, then write the accepted ones. Use this when asked to "triage feedback", usually right after `./v3 import-instance`.

Item format: [feedback item record](../specs/feedback_item_record_v1.md). Identity, workflow and closure rules: [production feedback management](../reference/production_feedback_management.md).

## 1. Settle the window

- Take the window from the request ("since Nov 1", "the last two days"). If none is given, start after the newest `Created` date among items, and say so.
- Record the window in UTC and in the maintainer's local time (America/New_York). Resolve relative windows once, against the time you start.
- Read posts from the local import (`state/local_repository/records/posts/`, or the repository root the maintainer names). Do not import or fetch again. If the import looks stale or partial, say so in the proposals.

## 2. Catch up on completed work

Also run this reconciliation before reporting backlog counts or recommending
priorities, even when no new site feedback is being imported. For a history-only
run, record the audited application commit and history range; mark import and
post-intake counts not applicable instead of implying a fresh runtime audit.

On the first reconciliation of migrated records, inspect history from before
their creation too: an imported open status may predate a fix. Search item IDs,
source post IDs, outcome wording and linked implementation summaries. Exact
mentions are leads, not evidence that the outcome is complete. Read later
corrective commits and retained acceptance checks before proposing a transition.

Distinguish commits reachable from main from unmerged branch work with Git
ancestry checks. Existing branch work goes to review/merge, not a new build.
For already implemented work, name the remaining deployment or acceptance check
and who can perform it. Agents should perform available code and browser/API
checks themselves; reserve maintainer requests for unavailable devices or
explicit decisions and bundle those requests.

Counts and priority reports must separate untriaged, ready/planned/in-progress,
blocked and closed records. State explicitly that open is not a count of missing
implementations. List blocked work by its recorded next action (review/merge,
deployment, acceptance, or other); do not invent a blocker to fill a category.
Do not put implemented work back in a build queue without contrary evidence.
Use the existing item fields and generated index rather than another tracker.

Keep the reconciliation as a dated file in docs/feedback/triage/, including
proposed status changes, evidence links, unresolved matches and coverage limits.
After review, apply accepted transitions through step 6. A dated report is a
snapshot; the item files remain the authority for current status.

Find work that landed since the previous run without its item being updated. The previous run is the newest `*_proposals.txt` in `docs/feedback/triage/` with `Status: applied`; use the commit that added it as the starting point (with no earlier run, use the window start).

- Scan `git log <start>..HEAD` for `Feedback: PF-...` trailers (ignoring commits that only add a triage proposals file), and for PF IDs mentioned in commit messages or in plans changed in that range.
- For each item named, compare what landed with the item's `Workflow`, `Evidence` and `Done when:`. Propose a **status** update only when the item file was not already changed to match.
- Propose `done` only when its completion checks are met by recorded evidence; a landed commit alone usually means `Evidence: locally-verified` and `Workflow: blocked` awaiting deployment or production acceptance. Name the commit in the proposal.
- Do not infer completion from a commit that merely touches related code without naming the item; mention it as a possible match instead.

## 3. Find feedback

- Select posts by their `Created-At` header, not file time. Include roots and replies, with or without tags.
- For terse replies ("also this", "+1"), read the parent and thread to recover what is meant. Label that older context as context, not new feedback.
- Separate actionable requests about this project from social posts, bootstrap records, automated agent replies, thread markers and feedback about other products.
- Replies in the [Agent trouble reports](https://zenmemes.com/threads/thread-20261109051954-8e24340fcf0d516d00a73c054ce1791e) thread are feedback, even when `frm.py` wrote them automatically. Group them by `Route` and `Error`: repeated trouble is one outcome with each report as a source. A single automatic report with no clear fix may be skipped as `agent trouble, one-off`.
- Keep attribution honest: agent-written proposals stay labeled as agent proposals; do not present someone else's suggestion as the maintainer's request.

## 4. Match against existing items

- Search `docs/feedback/items/` by source post ID first, then by outcome. Match on intended behavior and completion, not shared keywords.
- A post already listed in an item's `Sources:` needs nothing; a rerun on the same window must propose no changes for it.
- A repeat of an open item's outcome is an **attach**. A repeat of a closed item reopens it only with a contrary report; otherwise attach and say it is closed.
- One post asking for two independent things is two outcomes; it may be listed as a source on both.
- When unsure whether two requests are the same, keep them separate and say so in `Notes:`.

## 5. Propose

Write the proposals to `docs/feedback/triage/<intake date>_proposals.txt` in the repository (never `/tmp` or another scratch location), then point the maintainer at that file. It is the record of the run and is committed with the item changes.

- Start with `Key: value` headers: `Title`, `Status` (`awaiting review`, then `applied` or `rejected`), `Intake-Date`, the UTC and local window, the import commit, posts screened, and the per-post classification counts (attach, create, skip) that add up to posts screened. Then outcome counts (new items by kind, existing items updated, status updates); say which counts are posts and which are outcomes.
- **create**: proposed ID, kind, title (a few words), the request in the poster's own terms with qualifiers like "maybe" or "brainstorm" kept, and every source URL.
- **attach**: the item ID and title, the new source URLs, and anything new the post adds.
- **status**: the item ID, its current and proposed `Workflow` and `Evidence`, and the commit or plan that shows it.
- **skip**: the post and a short reason (social, other product, already covered, agent-only idea, and so on). Every post in the window ends up in exactly one of create, attach or skip.

Allocate IDs as `PF-<intake date>-<F|B|O><nn>`, using the next unused number for that date and kind. Do not invent implementation decisions, root causes or estimates; leave `Estimate` out unless someone gave one.

When the maintainer asks for edits, change the proposals file itself, so the committed file matches what was applied.

## 6. Write

After the maintainer accepts (with any edits):

- Create or update item files. New items start `Workflow: untriaged`, `Evidence: unknown`. Attach adds the source line and a short dated note. A status update changes `Workflow` and `Evidence` and adds a dated `Notes:` line naming the commit.
- Run `./v3 feedback check` and fix every problem. Read each `Review:` line: a shared source is fine for a split request; otherwise it is a missed attach.
- Run `./v3 feedback index`.
- Set the proposals file's `Status: applied`.
- Show the diff summary (created, updated, skipped counts) and leave the commit to the maintainer unless asked to commit. The proposals file and the item changes go in the same commit, without `Feedback:` trailers (those mark work on an item, which this is not).

## Recovery

- No item is written before review. A rejected run keeps its proposals file with `Status: rejected` and a one-line reason, or the maintainer deletes it.
- A wrong write is undone with Git before commit (`git checkout -- docs/feedback/` or `git clean` for new files, after checking `git status`).
- If check or index fails partway, fix the named file and rerun; the index is written whole or not at all.
