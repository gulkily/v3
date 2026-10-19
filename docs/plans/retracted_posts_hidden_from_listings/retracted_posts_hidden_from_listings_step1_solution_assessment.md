> **Feature plan:** [Step 1](./retracted_posts_hidden_from_listings_step1_solution_assessment.md) · [Step 2](./retracted_posts_hidden_from_listings_step2_feature_description.md) · [Step 3](./retracted_posts_hidden_from_listings_step3_development_plan.md) · [Step 4](./retracted_posts_hidden_from_listings_step4_implementation_summary.md)

Debug mode: enabled

## Original Query
When a post is deleted/retracted, it should no longer show up in listing pages.

## Understood Intent
Retracted posts currently stay in listings as a "retracted" notice row. The goal is for them to disappear from listing pages (board, tag, author/activity-style lists). Direct permalinks to a retracted post and its thread page are assumed to keep working. Which listings are in scope and how retracted roots with live replies behave are open questions for Step 2.

## Problem
Retracted threads and posts still appear in listing pages as placeholder rows instead of being omitted.

## Option A: Filter in each listing query
Add a `retracted_at IS NULL` condition to every listing query (board threads, authored content, activity, and so on), leaving the shared row selection otherwise untouched. Direct lookups such as `byId` stay as they are.

- Pros: smallest change; no schema change; permalinks and thread pages unaffected
- Cons: each listing needs its own edit, and a new listing can miss the rule; retracted rows still load for any code that reuses the shared selection

## Option B: Central visibility rule shared by all listings
Introduce one shared listing-visibility rule (a common SQL fragment or repository helper) that all listing queries use. Direct lookups bypass it. Existing hidden-post and bootstrap-tag exclusions can move into the same rule.

- Pros: one definition of "listable"; later listings get it by default; easy to test in one place
- Cons: wider refactor of working queries; more regression surface than A

## Option C: Filter at render time
Drop retracted rows in controllers or templates after the data is loaded, so the rows are loaded and then discarded.

- Pros: no query changes
- Cons: pagination, counts, and "last activity" ordering can be wrong; filtering is duplicated across templates; retracted content is loaded into pages that never show it

## Recommendation
Option B, scoped as a vertical slice. The slice is: a retracted thread root disappears from board and tag listings and from author/activity lists, while its permalink, thread page, and replies stay reachable. Option A is an acceptable fallback if Step 2 narrows scope to the board listing only. Option C is not recommended because it breaks counts and paging. No read-model schema change is expected, since `retracted_at` already exists on `threads` and `posts`.

## Enhancement Requests
None yet.
