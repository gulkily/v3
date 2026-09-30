# Step 2: Feature Description — Merge Same-Author Reply Chains on Thread Pages

## Problem
On thread pages, a burst of quick same-author replies each renders as its own full card (repeated byline, repeated action row), so one continuous piece of writing reads as several disconnected posts instead of one.

## User Stories
- As a thread reader, I want a run of quick same-author replies to look like one continuous post so repeated headers/actions don't fragment the reading experience.
- As a thread reader, I want to still like/flag/reply to (or request an agent response to) an individual piece within a merged run, so I don't lose any per-post interaction.
- As a thread reader, I want a deep link to a specific reply inside a merged run to still scroll to and highlight that exact piece.
- As a thread reader, I want the root post's header decluttered (no duplicated first line, no redundant metadata lines) so the byline reads as one clean line.

## Core Requirements
- Presentation-only: no changes to the data model, post ids, permalinks, per-post like/flag/agent-request endpoints, or how agent-authored replies render.
- The server (not client-side JS) marks a post as a "continuation" when its author matches the previous post's author, the previous post was ≤15 minutes earlier (named constant), and neither post is agent-authored; this must work with JS disabled.
- A run of continuations renders as one visually merged card (shared border, paragraph-style spacing between pieces) while each piece keeps its own hover/tap-revealed action row and a hover-revealed timestamp.
- The root card's header collapses to a single meta line (author · time · like · reply count) that counts only true replies, not continuations, and the body no longer repeats the title's first line.
- Agent-authored replies remain unaffected — still their own separate, visually distinct cards, never merged into a run.

## Shared Component Inventory
- `templates/partials/thread_root_card.php` — renders the root post; extended in place (continuation marking doesn't apply to the root itself as a continuation target, but it can be the first card in a run; header cleanup lands here).
- `templates/partials/post_card.php` — renders each reply; extended in place to add the continuation flag/data attributes.
- `templates/pages/thread.php` — the loop assembling root + replies; extended to compute the continuation flag per post while iterating (still passes a flat post list to the partials, per the Step 1 decision).
- `templates/partials/thread_card.php` (board/tag list thread summary) — a separate, unrelated rendering context; not touched, and its own reply-count display is out of scope here.
- CSS: `public/assets/site.css`, `content-interactions.css`, and the theme override files (`theme-word97.css`, `theme-sticker.css`, `theme-arena.css`, etc.) — extended in place with new rules for `.continuation`; no new stylesheet needed.
- No new UI component is introduced; this reuses and extends the two existing post partials and the existing stylesheets.

## Simple User Flow
1. Reader opens a thread page with a root post, several quick same-author replies, and one agent reply.
2. The root post and its same-author replies render as one visually continuous card; the agent reply renders as its own separate card.
3. Reader hovers/taps a piece within the merged card and sees its timestamp in the gutter plus its own like/flag/reply/agent-request actions.
4. Reader follows a deep link to one piece inside the merged run; the page scrolls to and highlights that exact piece, as before.
5. Reader sees the root card's header as one line (e.g. "guest · Sep 27, 23:42 · like · 1 reply") with no duplicated first line of body text.

## Success Criteria
- On `thread-20260927234240-a72307ac`, the root post plus its 6 continuations render as a single visual card with one byline and one action row, followed by a separate `reply-agent` card.
- Every continuation's own action row and permalink stay independently reachable (hover or tap) and functional — like/flag/agent-request still act on the correct individual post id.
- A deep link to a continuation's anchor still scrolls to and highlights that specific piece.
- The root card's header shows no duplicated first line and one merged meta line whose reply count reflects true replies only.
- Verified in light theme, dark theme, and at phone width, and confirmed to still work correctly with JS disabled.
