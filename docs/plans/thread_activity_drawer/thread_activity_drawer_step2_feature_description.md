> **Feature plan:** [Step 1](./thread_activity_drawer_step1_solution_assessment.md) · [Step 2](./thread_activity_drawer_step2_feature_description.md) · [Step 3](./thread_activity_drawer_step3_development_plan.md) · [Step 4](./thread_activity_drawer_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/thread_activity_drawer`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.55**

## Problem

A thread page mixes conversation with bookkeeping. A reply that is only hashtags takes a full reply card. Who liked, flagged or labelled what is spread across one-line signal summaries.

## User stories

- As a reader, I want only replies with something to say in the conversation, so that the thread reads cleanly.
- As a reader, I want one collapsed place listing a thread's likes, flags and labels, so that I can see who reacted to what when I care to.
- As a member who tags a thread with a hashtag-only reply, I want my tag to show as a small entry, so that it counts without crowding the thread.

## Core requirements

- **Tag-only replies.** A reply is tag-only when its body is empty after removing quoted lines (`>`), hashtags and whitespace. Hashtags are found the same way `extractThreadLabelsFromBody` finds them for labels. A tag-only reply that has no visible replies of its own leaves the reply flow; one with replies stays in place.
- **The drawer.** A collapsed "Activity (N)" drawer sits after the last reply and before the reply box. N counts its entries. It lists:
  - for each tag-only reply: who tagged which hashtags, and when, linking to the reply's own page
  - for the root and each reply: likes, flags and other labels, using the same eligible actors as the visible signal lines (`SignalActors`), grouped by post with a short label such as "Root post" or "Reply by d"
- **Links.** With `FORUM_POST_RECORDS_ENABLED` also on, each post group links to its `#records` list. Without it, the group links to the post.
- **When empty.** With nothing to show, there is no drawer.
- **Flag.** A new flag, off by default. With it off, the thread page is unchanged.
- **No new data.** The drawer is built from data the thread page already loads: replies, `SignalActors` and thread labels. It adds no file scans.

## Delivery scope

- Work type: application change.
- Covers the main thread page, `/threads/<id>`. Forte, static detail pages and the post page are unchanged in this slice.

## Completion boundary

- Normal entry: open a thread on zenmemes.com with the flag on.
- End-to-end outcome: hashtag-only replies are gone from the conversation and listed in the drawer. The drawer lists the thread's likes and flags by post, and opening it shows each entry with links.
- Recovery: if the drawer data fails to load, the thread renders as today, without a drawer.
- Release condition: deployed with the flag on, and checked on a real thread that has likes and a hashtag-only reply.

## Risks

- **Hiding a reply people answered.** Impact: an orphaned conversation. Earliest validation: a test with a tag-only reply that has its own reply. Mitigation: only leaves are moved.
- **Counting the wrong thing as tag-only.** For example, a reply of `#bug see above`, or one with an image or attachment. Impact: real content goes into the drawer. Earliest validation: a table of body examples in tests. Mitigation: any non-hashtag text, or any attachment, image or event metadata, keeps the reply in place.
- **Reply numbering and continuation runs.** Removing replies could shift same-author run grouping. Earliest validation: page tests with runs around a removed reply. Mitigation: filter before the template computes runs, as is already done for scripture expansions.
- **Counts that don't match the signal lines.** Mitigation: reuse `SignalActors` rather than a second query.

## Shared component inventory

- Thread controller: `Http/ThreadAndPostPageController.php` (`thread`). Already filters scripture expansions out of the reply list; reuse that pattern for tag-only leaves.
- Template: `templates/pages/thread.php`. Reply partition, continuation flags and `RetractedLeafFilter`. Extend with the drawer partial.
- Actors: `ReadModel/SignalActors.php`. Reuse as is.
- Hashtag rule: `extractThreadLabelsFromBody` in `Write/LocalWriteService.php`. Extract a small shared helper so the write and read sides agree.
- Records link: `post_records`' `#records` anchor and flag. Reuse.
- Flags: `Support/FeatureFlags/FeatureFlagRegistry.php`. Add one flag.
- New: partial `thread_activity_drawer.php` and a small builder for its entries.

Concepts touched: [post presentation](../../concepts/post_presentation.md) (changed: tag-only leaves move to the drawer); [reaction record](../../concepts/reaction_record.md) (used); [post record list](../../concepts/post_record_list.md) (used for links). New concept: **thread activity**.

## User flow

1. A reader opens a thread. The conversation shows only replies with text.
2. Below the last reply, a closed "Activity (5)" drawer appears.
3. Opened, it shows "ilyag tagged #bug · Nov 14" and "Root post — Liked by ilyag, claude · Flagged by x".
4. A link opens that post's Records list, or the post itself.

## Success criteria

- A body of `#bug #ux` is tag-only; `#bug see above` and `> quote\n#bug` with text are not; a body that only quotes and tags is.
- A tag-only reply with a reply of its own stays in the conversation.
- The drawer's like and flag names match the signal lines on the same page.
- With the flag off, the thread HTML is unchanged.

## Study checkpoint

2026-11-14T19:35:00Z, `railspike-effort` / `v3/thread_activity_drawer`, Step 2 handoff. Scope v1, unchanged since Step 1 approval. Revision `047d00c`. R1, P2, single exposed Claude rater. Projected total reaffirmed at **0.55**. Ledger update pending until the planning commit.

## Enhancement Requests

Carried from Step 1: a timeline view with a time per reaction (out of scope; needs a timestamp in the read model).

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

**Approval:** Approved Step 2 by the maintainer, 2026-11-14T19:36:00Z ("great job, please commit up to step 2"), after reviewing a drawer mockup shared in session (not kept in the repo). Step 3 continues in a new conversation from this commit.
