# Forte Step 3 Development Plan

## Stage 1 - New Forte route and entry-point link
- Goal: add a new, separate page/route for the Forte paned reader for a given thread, plus a simple entry-point link from the standard thread page. No panes rendered yet — a stub page confirms routing works.
- Dependencies: none.
- Expected changes: new route pattern mirroring the existing `/threads/{id}` route (e.g. `/threads/{id}/forte`), served by a new handler/template; standard thread page template gains a small link to it.
- Verification: manually open the link; confirm it loads the correct thread's stub page. Open the standard thread page and the Forte page in separate tabs simultaneously and confirm both work independently with no shared state.
- Risks/open questions: confirm the new route segment doesn't collide with any existing reserved path.
- Touches: routing dispatch (`FrontController.php`/`Application.php`), new page template; standard thread page template touched only to add the link.

## Stage 2 - Build reply-tree data shaping
- Goal: turn the existing flat, `sequence_number`-ordered post list into a nested tree using each post's existing `parent_id`.
- Dependencies: Stage 1 (route exists to eventually render this into).
- Expected changes: new pure function, e.g. `buildReplyTree(array $posts): array`, called from the new Forte route handler only; no new SQL, no change to `fetchThread()`/`fetchThreadPosts()`, and no effect on the standard thread page's rendering path.
- Verification: manual check — log/dump the built tree for a thread with multiple nested replies and confirm parent/child relationships and ordering match `parent_id` values exactly.
- Risks/open questions: orphaned `parent_id` (parent not in the fetched set, e.g. deleted) — decide fallback (treat as root) before Stage 3 consumes the tree.
- Touches: `src/ForumRewrite/Application.php` (new function only, existing fetch functions untouched).

## Stage 3 - List-pane partial with nesting
- Goal: render the nested tree as an indented list (subject/from/date columns, agent-reply badge, collapse/expand per branch).
- Dependencies: Stage 2 (tree structure available).
- Expected changes: new partial, e.g. `templates/partials/paned_list_pane.php`, consuming the Stage 2 tree; reuses existing per-post fields (author, date, subject, agent-reply flag) already read by `post_card.php`/`thread_root_card.php` rather than re-deriving them.
- Verification: manual check on a thread with 3+ reply depths — indentation and collapse/expand match actual reply structure; agent-reply badge appears only on flagged posts.
- Risks/open questions: deep nesting on narrow viewports may need an indentation cap; flag for Stage 6 polish rather than solving here.
- Touches: new partial only; existing `post_card.php`/`thread_root_card.php` fields read, not modified.

## Stage 4 - Content-pane partial
- Goal: render the full content of a single selected post (defaults to the root post).
- Dependencies: Stage 2 (same post records available).
- Expected changes: new partial, e.g. `templates/partials/paned_content_pane.php`, reusing the same body/author/date/reactions/agent-marker fields and reply/reaction controls already rendered by `post_card.php`, without duplicating that markup's logic.
- Verification: manual check — content pane shows correct fields for the root post by default; reply/reaction controls behave identically to the standard view.
- Risks/open questions: none expected; this is a straight field reuse.
- Touches: new partial only; reuses existing per-post render fields.

## Stage 5 - Client-side pane sync
- Goal: selecting a row in the list pane updates the content pane in place, no page navigation, no new backend calls.
- Dependencies: Stage 3 and Stage 4 (both panes exist on the page).
- Expected changes: small client-side script that shows/hides pre-rendered per-post content blocks (all posts' content already rendered server-side, per Step 2's "no new API calls" requirement) based on the selected list row; e.g. function `selectPost(postId)`.
- Verification: manual check — clicking each row in a multi-post thread updates the content pane correctly; browser back/forward and page reload behave sanely (no broken state).
- Risks/open questions: rendering every post's full content into the page up front (for client-side swap) vs. fetching on demand — confirm the "no new API calls" requirement means the former; flag if payload size becomes a concern on very long threads.
- Touches: new script only; no existing components modified.

## Stage 6 - Layout integration and polish
- Goal: paned reader fills its own page at full width/height (it's a standalone route, not a region toggled within the standard page), with toolbar/menu-bar chrome styled per the Step 2 reference.
- Dependencies: Stages 1-5 complete.
- Expected changes: page-level CSS scoped to the Forte route only; chrome styling (title/menu/toolbar bar) as static presentation, no new interactive surface beyond Stage 5; standard thread page's styles and layout untouched.
- Verification: manual check across at least two viewport widths — Forte page fills its own layout correctly; separately confirm the standard thread page is visually and functionally unaffected, including when both are open in separate tabs at once.
- Risks/open questions: confirm chrome styling doesn't conflict with the site's existing global header/nav present on every page.
- Touches: Forte page layout only; no shared global styles modified.
