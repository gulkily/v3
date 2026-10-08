# Step 1: Solution Assessment — Merge Same-Author Reply Chains on Thread Pages

## Problem
Quick same-author replies within a short window each render as a full, separately-bordered `post-card` with repeated byline/actions, so one continuous piece of writing reads as many disconnected posts instead of one.

## Context from current code
- `templates/pages/thread.php` already splits `$posts` into root + flat `$replyPosts`, rendering each reply via `partials/post_card.php` as a direct sibling `<article class="card post-card">` under `section.stack`.
- `public/assets/site.css:337` has a direct-child rule `.stack > * + *` that spaces every top-level card; several theme files (`theme-word97.css`, `theme-sticker.css`, `theme-arena.css`) also key off `.post-card` / `.stack > * + *` directly.
- The thread root card's title-duplication issue is already fixed (`thread_root_card.php:50-54`, from a prior `thread_root_card_dedupe_title_first_line` slice) and the reply-count line was already removed from the root card's meta block (prior `thread_root_card_remove_reply_count` slice) — `reply_count` now only feeds the `data-heat` calculation. Re-introducing a "1 reply" count in a merged meta line means partially reversing that prior removal, not a fresh addition.
- No `is_agent_authored` column exists; agent authorship is derived as `author_label === 'reply-agent'`.

## Option A — Flag continuations in place, merge visually with CSS (as specified in the task doc)
Keep every post as a flat sibling `<article class="post-card">` (root and replies, unchanged loop structure in `thread.php`). Add a `continuation` class + `data-time`/`data-author` attributes to qualifying posts server-side; do all visual merging (hidden borders/margins, hover-reveal actions/timestamp/permalink) with CSS adjacency selectors (`:has(+ .continuation)`, `.continuation ~ …`).
- Pros: smallest diff — only touches `post_card.php`/`thread_root_card.php` (add class/data attrs) and CSS; no change to `thread.php`'s render loop or post ordering/grouping logic; every existing direct-child selector (`.stack > * + *`, theme overrides) keeps working untouched; each post stays independently addressable by id/anchor with zero change to permalink/scroll behavior.
- Cons: relies on `:has()` and adjacent-sibling CSS, which gets fiddly for "hide until hover, but only for all-but-last-in-a-run" behavior; grouping is implicit in CSS rather than visible in markup, which is a little harder to reason about when debugging layout.

## Option B — Group runs into wrapper containers in the template
In `thread.php`, walk `$replyPosts` (and the root) server-side and wrap each same-author run in a `<div class="post-run">` container, so continuations are structurally nested rather than flat siblings merged by CSS.
- Pros: simpler, more conventional CSS (no adjacency `:has()` hacks); the DOM directly expresses grouping, which is easier to reason about and extend later (e.g. the optional "Continue" button follow-up).
- Cons: bigger diff — introduces new grouping/loop logic in `thread.php` itself (not just per-post flags); breaks the `section.stack > article.card.post-card` direct-child assumption relied on by `site.css:337` and multiple theme files, requiring those to be audited/updated too; more surface area to get wrong for something that's supposed to be presentation-only.

## Recommendation
**Option A.** It matches the task doc's own spec exactly, keeps the change confined to the two post partials plus CSS, and leaves the `.stack` direct-child structure — and everything else keyed off it (base CSS, five+ theme files, permalink/anchor behavior) — untouched. Option B's cleaner CSS isn't worth the wider blast radius for what is explicitly a presentation-only task; it's worth reconsidering only if the optional "Continue" button follow-up is picked up later and explicit grouping becomes load-bearing.
