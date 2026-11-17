> **Feature plan:** [Step 1](./thread_activity_drawer_step1_solution_assessment.md) · [Step 2](./thread_activity_drawer_step2_feature_description.md) · [Step 3](./thread_activity_drawer_step3_development_plan.md) · [Step 4](./thread_activity_drawer_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/thread_activity_drawer`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. No parent cycle; follows on from `v3/post_records`.

Extended estimates: enabled

Cycle estimate: 0.55

## Original Query

> this is fantastic. can you please add a user-facing version for this type of page: https://zenmemes.com/threads/thread-20261023160949-8d04e756
> it should include likes/flags/etc and also when a user adds a label by using hashtag text without any additional text.
> look at pollyanna's #notext for guidance please
> ideally we want to collapse those into a drawer so that they don't pollute the reply space

## Understood Intent

`post_records` added a technical Records list to each post's own page. The maintainer wants a reader-facing version on the thread page, kept in a collapsed drawer so it doesn't crowd the conversation. It would cover two things:

- **Reactions:** likes, flags and labels on the root and on each reply.
- **Hashtag-only replies:** a reply whose body is only hashtags, with no other text. Its tags already become thread labels (`Source-Post-ID`), but today the reply also takes up a full reply card.

Problem: The thread page mixes conversation with bookkeeping. Tag-only replies take up full reply cards, and the record of who liked, flagged or labelled what is spread across one-line signal summaries.

**Pollyanna's `#notext`** (`gulkily/pollyanna` at `7a5666d`):
- `index_text_file.pl` (around line 1162) labels any message with no text left after its tokens are removed as `#notext`. Its hashtags are meant to apply to the parent.
- `item_page.pl` (around line 359) shows `#notext` replies only in the "advanced" view.
- The new, related, random and welcome queries leave them out.
- A todo in `doc/compost/brainstorm.txt` says they "should be displayed as small things with just the tag/token".
- Carries over: decide "no text" by stripping tokens, and keep those items out of the main flow, shown small.
- Doesn't carry over: an indexed `#notext` label. v3 can decide this from the reply body when the page renders.

Concepts touched: [post presentation](../../concepts/post_presentation.md) (changed: tag-only replies leave the reply flow); [reaction record](../../concepts/reaction_record.md) (used); [post record list](../../concepts/post_record_list.md) (used: drawer rows link to it when that flag is on); [effective tags](../../concepts/effective_tags.md) (unchanged). Candidate new concept: **thread activity**, the reactions and tag-only replies of one thread.

Related feedback: none found.

## Shared by every option

- A reply counts as tag-only when its body, after removing hashtags and whitespace, is empty. This is the Pollyanna rule, applied when the page renders.
- A tag-only reply that has replies of its own stays in the conversation, so no thread is orphaned.
- A new feature flag, off by default.
- Every entry names who did what and on which post, and links to the post.

## Option A - Activity drawer on the thread page (recommended)

**Estimate: 0.55**
Main driver: gathering a thread's reactions from the read model in one query, plus pulling tag-only replies out of the reply loop. Uncertainty: medium, in how per-reply reactions are shown without a timestamp column in `viewer_tags`.

- **Drawer:** a collapsed **Activity (N)** drawer near the end of the thread page, above the reply box. It lists likes, flags and labels on the root and each reply, grouped by post: for example, "Liked by a, b · Flagged by c" on "Reply by d".
- **Tag-only replies** leave the reply flow and appear in the drawer as small entries: "e tagged #bug".
- **Data:** reactions come from `viewer_tags`, already indexed by target. Tag-only replies come from the reply list the page already loads, so the drawer adds no file scans.
- Pro: the conversation shows only text, and everything else is one click away.
- Pro: it reuses data already on hand, so the page stays fast.
- Con: `viewer_tags` has no time per reaction, so entries are grouped by post, not shown as a timeline.

## Option B - Compact tag-only replies in place

**Estimate: 0.25**
Main driver: one reply-card variant. Uncertainty: low.

- Tag-only replies stay where they are, but shrink to a one-line chip ("e tagged #bug"), as in Pollyanna's todo.
- No drawer; reactions keep today's signal lines.
- Pro: smallest change, and it keeps the chronology.
- Con: the bookkeeping still sits among the conversation, just smaller.
- Con: there's no single place to see all of a thread's reactions.

## Option C - Stop storing tag-only replies as posts

**Estimate: 0.70**
Main driver: a write-path change and the effects on existing records. Uncertainty: high.

- When a reply is only hashtags, write only the thread label records and no post.
- The drawer then shows reactions alone.
- Pro: there's nothing to hide.
- Con: it changes what gets signed and stored, and existing tag-only replies still need display handling.
- Con: removing the reply loses its place in the conversation and its own page.

## Recommendation

Choose Option A. It matches the drawer idea and Pollyanna's direction (out of the main flow, shown small), and it needs no changes to stored records.

First vertical slice: with the flag on, a thread page has a collapsed Activity drawer listing reactions by post, and tag-only replies without replies of their own move into it.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

**Approval:** Approved Step 1 by the maintainer, 2026-11-14T19:28:00Z, with Option A.

## Study checkpoint

2026-11-14T19:05:00Z, `railspike-effort` / `v3/thread_activity_drawer`, intake and Step 1 handoff. Scope v1: Option A as recommended. Starting revision `68724f9b7aa51f195754dc2a23209a0aab71993f`. R1, P2, single exposed Claude rater. Before rating, Pollyanna's `#notext`, the thread template, the reaction tables and the hashtag label writes were inspected, so there is no pristine intake. Projected total **0.55**. Option estimates 0.55, 0.25 and 0.70 are alternatives and don't add up. Ledger update pending until the planning commit.

## Enhancement Requests

- A timeline view of the drawer, with a time per reaction. Disposition: needs a timestamp in the read model; out of scope unless Step 2 adds it.
