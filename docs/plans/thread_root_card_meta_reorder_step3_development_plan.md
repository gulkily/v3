# Step 3: Development Plan — Thread Root Card Meta Reorder

## Stage 1
- Goal: Move the root post's byline, labels, and reply-count lines (and the agent-authored-reply marker, which sits in the same block) from between the title and the body to after the body, so `<h1>` is immediately followed by `<div class="body">`
- Dependencies: none
- Expected changes: in `templates/partials/thread_root_card.php`, relocate the four conditional `<p class="meta">` lines currently between `<h1>` and `<div class="body">` (byline via `$contentMeta`, `Labels: …`, reply count, agent-authored-reply marker) to immediately after `<div class="body">`, before the `button-row` actions div; no change to each line's condition, text, or markup, only position
- Verification approach: view a thread page with labels + replies (e.g. the referenced poem thread) and confirm `<h1>` is immediately followed by `<div class="body">` in the rendered HTML; confirm byline/labels/reply-count/agent-marker still render correctly and in their prior relative order, now after the body; spot-check a thread with no labels and zero replies to confirm those lines still correctly stay hidden; spot-check an agent-authored root post if one exists
- Risks or open questions:
  - Success criteria requires zero `<p class="meta">` lines between title and body in all cases, including agent-authored root posts, so the agent-authored-reply marker (not explicitly called out as in-scope in Step 2) must move too — noting this as a small necessary extension of Step 2's stated scope, not new functionality
  - `thread_card.php` (board/tag list) and `post_card.php` (reply posts) are separate templates and are not touched
- Canonical components/API contracts touched: `templates/partials/thread_root_card.php` (markup only, no PHP logic/data changes)

Reply **Approved Step 3** to create the feature branch and begin Step 4.
