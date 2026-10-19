> **Feature plan:** [Step 1](./retracted_posts_hidden_from_listings_step1_solution_assessment.md) · [Step 2](./retracted_posts_hidden_from_listings_step2_feature_description.md) · [Step 3](./retracted_posts_hidden_from_listings_step3_development_plan.md) · [Step 4](./retracted_posts_hidden_from_listings_step4_implementation_summary.md)

Debug mode: enabled

## Problem
Retracted threads and posts still appear in listing pages as placeholder rows. A retracted post should no longer show up in listings.

## User stories
- As a reader, I want retracted threads to be absent from the board and tag listings so that I only browse live content.
- As an author, I want my retracted posts to leave my authored-content lists so that retracted work is not surfaced again.
- As a reader following an old link, I want a retracted post's permalink to still show its retraction notice so that I understand what happened.

## Core requirements
- A retracted thread root does not appear in board, tag, author, or activity listings.
- A retracted reply does not appear as its own row in post listings (author and activity). It still shows its retraction notice in place inside its thread page.
- Listing counts, paging, and ordering ignore retracted items, so no gaps or wrong totals appear.
- Permalinks and thread pages for retracted content keep working, with the existing retraction notice. A retracted root with live replies stays reachable by permalink with its replies.
- Retracting a post updates listings the same way as other read-model changes (no manual rebuild).

## Delivery scope
- Work type: application change.
- Out of scope: the retraction flow itself, who may retract, thread-page rendering of retracted replies, search and related-content results, and offline snapshot behavior (it already excludes retracted threads).

## Completion boundary
- Entry: a reader or author opens a board, tag, author, or activity listing.
- Outcome: a post retracted before or after the page loads is absent from the listing on the next load.
- Recovery: the retracted post stays reachable through its direct link, and its source record is unchanged, so nothing is lost.
- Release: tests for each in-scope listing pass, and a manual check on a retracted thread confirms it is gone from listings but present at its permalink.

## Risks
- **A listing is missed.** Impact: retracted rows still appear somewhere. Earliest validation: inventory all listing queries in Step 3. Mitigation: one shared listing rule, plus a test per listing.
- **Counts and paging drift.** Impact: totals or "load more" cursors include hidden rows. Validation: tests with retracted rows in the middle of a page. Mitigation: filter in the query, not after loading.
- **Reply counts and last activity.** Impact: a thread's reply count or ordering still counts a retracted reply. Validation: decide in Step 3 whether this changes. Mitigation: leave unchanged unless inconsistent with the listing.
- **Read-model schema.** Impact: extra requirements from `CONTRIBUTING.md`. Validation: none expected, since `retracted_at` exists. Mitigation: stop and replan if a schema change becomes necessary.

## Shared component inventory
- Board thread listing: `ThreadRepository::boardRowsSql` and its editable-listing sibling. Extend with the shared rule.
- Single-thread lookup (`ThreadRepository::byId`) and reply lists: permalink paths. Leave unchanged.
- Authored content lists: `AuthoredContentRepository::visibleThreads`, `visiblePosts`, `countVisible`. Extend with the shared rule.
- Activity feed: `ActivityService` view queries and totals. Extend with the shared rule.
- Retraction notice rendering (`RetractedPostNotice`, retracted-post templates): keep for permalink pages; listing rows that used it are removed.
- Directory and tag listing queries in `Application.php` and the user directory controller: confirm in Step 3 whether they list posts.
- No new component is needed.

## Simple user flow
1. An author retracts a post.
2. A reader opens the board or a tag listing and does not see the thread.
3. The author opens their own content list and does not see it.
4. Anyone with the direct link opens the post and sees the retraction notice.

## Success criteria
- Zero retracted threads or posts in the in-scope listings, verified by tests with retracted fixtures.
- Counts and paging match the visible rows.
- Permalinks for retracted posts return the same notice page as before.
- Existing retraction and listing tests still pass.

## Enhancement Requests
None yet.
