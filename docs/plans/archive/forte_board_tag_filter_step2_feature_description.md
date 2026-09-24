# Forte Board Tag Filter Step 2 Feature Description

## Problem
Selecting a tag in the Forte board view's folder pane already filters the thread list client-side, but there's no way to independently verify or share/bookmark a filtered view, which has made the filtering hard to trust. This combines instant client-side filtering with URL state and server-side rendering of that same state.

## User Stories
- As a Forte board view user, I want clicking a tag to instantly filter the thread list so that browsing stays fast with no page reload.
- As a Forte board view user, I want the URL to reflect the tag I've selected so that I can bookmark, share, or reload it and land back on the same filtered view.
- As a Forte board view user, I want to use the browser's back/forward buttons to move between tag selections so that navigation feels normal.
- As a reviewer, I want loading a tag-scoped URL to show the correct filtered list from the very first byte of HTML so that I can confirm filtering is real without relying on JavaScript having run.

## Core Requirements
- Clicking a tag keeps today's instant, client-side filtering (no page reload) — this behavior doesn't change.
- Clicking a tag also updates the URL to `/forte?tag={tag}` (or plain `/forte` for "All Threads") via `history.pushState`, and browser back/forward restores the corresponding filter state.
- Loading `/forte?tag={tag}` directly (fresh load, reload, or shared link) renders the full thread list server-side with exactly that tag's threads visible and the folder tree showing that tag pre-selected — full matching list, no truncation (unlike the existing `/tags/` index page's 5-preview-per-tag pattern).
- The full thread list still ships in the page either way (server-computed visibility, not a server-side subset), so that switching to a *different* tag after a direct/shared load stays instant and client-side, with no new request.
- An unrecognized or missing `tag` value falls back to "All Threads" rather than erroring or showing an empty, unexplained list.
- No new database fields, tables, or API endpoints — one optional query parameter on the existing `/forte` route.

## Shared Component Inventory
- Existing `/forte` route / `renderForteBoard()` — extended to read an optional `tag` query parameter and compute each thread's initial visibility server-side, reusing the same tag-membership logic already used for the folder tree and client-side filtering.
- Existing folder tree partial — extended to accept which tag (if any) should render as pre-selected, instead of always defaulting to "All Threads."
- Existing thread list partial — extended so each row's initial `hidden` state reflects the requested tag, instead of always starting fully visible.
- Existing client-side filtering (`paned_board_reader.js`'s `selectFolder()`) — extended to also call `history.pushState` and to respond to `popstate`; the row-hiding logic itself is reused unchanged.

## Simple User Flow
1. User opens `/forte`, or opens a shared/bookmarked `/forte?tag=x` link directly.
2. If a tag was specified, the folder tree shows it selected and the thread list is already filtered to it, visible in the page's initial HTML.
3. User clicks a different tag; the list filters instantly and the URL updates to match, with no reload.
4. User presses back; the previous tag selection and matching list are restored.
5. User copies the current URL and shares it; opening it reproduces the exact same filtered view.

## Success Criteria
- Loading `/forte?tag=x` server-renders with exactly the threads carrying tag `x` visible, and no others, with no truncation.
- The folder tree shows `x` as selected when loaded via `?tag=x`.
- Clicking any tag afterward updates the visible list instantly and updates the URL without a page reload.
- Browser back/forward moves correctly between previously-selected tags.
- An invalid or missing `tag` value falls back to "All Threads."
- No new database fields, tables, or endpoints — only one new optional query parameter on the existing route.
