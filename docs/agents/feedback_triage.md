# Feedback triage

Turn new site feedback into proposed changes to [feedback items](../feedback/README.md), show them for review, then write the accepted ones. Use this when asked to "triage feedback", usually right after `./v3 import-instance`.

Item format: [feedback item record](../specs/feedback_item_record_v1.md). Identity, workflow and closure rules: [production feedback management](../reference/production_feedback_management.md).

## 1. Settle the window

- Take the window from the request ("since Nov 1", "the last two days"). If none is given, start after the newest `Created` date among items, and say so.
- Record the window in UTC and in the maintainer's local time (America/New_York). Resolve relative windows once, against the time you start.
- Read posts from the local import (`state/local_repository/records/posts/`, or the repository root the maintainer names). Do not import or fetch again. If the import looks stale or partial, say so in the proposals.

## 2. Find feedback

- Select posts by their `Created-At` header, not file time. Include roots and replies, with or without tags.
- For terse replies ("also this", "+1"), read the parent and thread to recover what is meant. Label that older context as context, not new feedback.
- Separate actionable requests about this project from social posts, bootstrap records, automated agent replies, thread markers and feedback about other products.
- Keep attribution honest: agent-written proposals stay labeled as agent proposals; do not present someone else's suggestion as the maintainer's request.

## 3. Match against existing items

- Search `docs/feedback/items/` by source post ID first, then by outcome. Match on intended behavior and completion, not shared keywords.
- A post already listed in an item's `Sources:` needs nothing; a rerun on the same window must propose no changes for it.
- A repeat of an open item's outcome is an **attach**. A repeat of a closed item reopens it only with a contrary report; otherwise attach and say it is closed.
- One post asking for two independent things is two outcomes; it may be listed as a source on both.
- When unsure whether two requests are the same, keep them separate and say so in `Notes:`.

## 4. Propose

Write the proposals to `docs/feedback/triage/<intake date>_proposals.txt` in the repository (never `/tmp` or another scratch location), then point the maintainer at that file. It is the record of the run and is committed with the item changes.

- Start with `Key: value` headers: `Title`, `Status` (`awaiting review`, then `applied` or `rejected`), `Intake-Date`, the UTC and local window, the import commit, posts screened, and the per-post classification counts (attach, create, skip) that add up to posts screened. Then outcome counts (new items by kind, existing items updated); say which counts are posts and which are outcomes.
- **create**: proposed ID, kind, title (a few words), the request in the poster's own terms with qualifiers like "maybe" or "brainstorm" kept, and every source URL.
- **attach**: the item ID and title, the new source URLs, and anything new the post adds.
- **skip**: the post and a short reason (social, other product, already covered, agent-only idea, and so on). Every post in the window ends up in exactly one of create, attach or skip.

Allocate IDs as `PF-<intake date>-<F|B|O><nn>`, using the next unused number for that date and kind. Do not invent implementation decisions, root causes or estimates; leave `Estimate` out unless someone gave one.

When the maintainer asks for edits, change the proposals file itself, so the committed file matches what was applied.

## 5. Write

After the maintainer accepts (with any edits):

- Create or update item files. New items start `Workflow: untriaged`, `Evidence: unknown`. Attach adds the source line and a short dated note.
- Run `./v3 feedback check` and fix every problem. Read each `Review:` line: a shared source is fine for a split request; otherwise it is a missed attach.
- Run `./v3 feedback index`.
- Set the proposals file's `Status: applied`.
- Show the diff summary (created, updated, skipped counts) and leave the commit to the maintainer unless asked to commit. The proposals file and the item changes go in the same commit.

## Recovery

- No item is written before review. A rejected run keeps its proposals file with `Status: rejected` and a one-line reason, or the maintainer deletes it.
- A wrong write is undone with Git before commit (`git checkout -- docs/feedback/` or `git clean` for new files, after checking `git status`).
- If check or index fails partway, fix the named file and rerun; the index is written whole or not at all.
