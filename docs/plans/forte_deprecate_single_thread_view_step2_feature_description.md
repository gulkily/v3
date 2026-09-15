# Forte Deprecate Single-Thread View — Step 2: Feature Description

## Problem
The single-thread Forte reader (`/threads/{id}/forte`) is a fully duplicate, unreachable view — the board view (`/forte`) already does everything it does and is the only one anything links to. Carrying its route, templates, JS, and a related server-side dead branch forward costs real maintenance attention for zero reader benefit, and forces every future Forte cycle to keep deciding whether to also touch a view nobody can reach.

## User Stories
- As a maintainer picking up the next Forte cycle, I want the single-thread view's code gone, so I never have to ask "does this change need to touch the old reader too?" again.
- As a maintainer, I want the dead `resolveComposeReplyReturnTo()` branch removed alongside the view that made it necessary, so the reply-return-to logic only accepts shapes something can still legitimately produce.
- (No end-user story: nothing links to this route today, so no reader-facing behavior changes as a result of this cycle — see Success Criteria.)

## Core Requirements
- The route match for `/threads/{id}/forte` (`Application.php:436`) and `renderForte()` (`Application.php:806`) are removed; requesting that URL now falls through to the normal 404 path, same as any other unrecognized route.
- `templates/pages/forte.php` and the three partials used only by it — `paned_list_pane.php`, `paned_content_pane.php`, `paned_compose_panel.php` — are deleted.
- `public/assets/paned_reader.js` is deleted.
- `resolveComposeReplyReturnTo()`'s `/threads/{id}/forte` whitelist branch (`Application.php:5083-5085`) is removed; the `/forte` (board) branch and the default fallback are untouched.
- The board view (`/forte`, `renderForteBoard()`) is completely unaffected — no template, JS, CSS, or route changes there. Per Step 1's finding, `paned_folder_tree.php`, `paned_thread_reply_tree.php`, and `forte.css` are shared/board-only despite living alongside the removed files, and stay as-is.
- No database/schema changes — this is a pure code/route removal.
- No test suite changes needed — Step 1 confirmed zero automated tests reference any of the removed surface.

## Shared Component Inventory
- `public/assets/forte.css` — loaded by both views today. **Untouched**; some single-thread-specific rules become unused but trimming them is out of scope for this cycle (noted in Step 1, not blocking).
- `templates/partials/paned_folder_tree.php` / `paned_thread_reply_tree.php` — despite naming/directory proximity to the removed files, these are board-view components (confirmed by usage grep in Step 1). **Untouched.**
- `resolveComposeReplyReturnTo()` — **partially removed** (only the single-thread branch); the board-view branch and default fallback are reused unchanged.
- Tools page → `/forte` link (`Application.php:1436`) — already points only at the board view. **Untouched.**

## Simple User Flow
1. Nothing changes for any reachable path through the app — the Tools page still links only to `/forte` (the board view), exactly as it does today.
2. A request to the old `/threads/{id}/forte` URL (which nothing in the app generates or links to) now 404s instead of rendering the old reader.

## Success Criteria
- `GET /threads/{id}/forte` for a real thread ID returns 404, identical to a request for any nonexistent route.
- `forte.php`, `paned_list_pane.php`, `paned_content_pane.php`, `paned_compose_panel.php`, and `paned_reader.js` no longer exist in the repo.
- A full-codebase grep for `renderForte(`, `paned_reader.js`, `paned_list_pane`, `paned_content_pane`, `paned_compose_panel`, and the `/threads/.../forte` route pattern returns zero hits outside this feature's own planning docs and git history.
- The board view (`/forte`) — folder tree, thread list, reply/compose (including the just-shipped signed authorship), sort, tag filtering — is fully regression-verified as unaffected.
- `resolveComposeReplyReturnTo()` still correctly resolves the `/forte` (board) return path and the default fallback; only the single-thread branch is gone.
