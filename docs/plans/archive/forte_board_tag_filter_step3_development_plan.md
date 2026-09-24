# Forte Board Tag Filter Step 3 Development Plan

## Stage 1 - Server-side tag query param handling
- Goal: extend the `/forte` route to read an optional `tag` query parameter, resolve it against real tag names, and fall back to "All Threads" when missing or unrecognized.
- Dependencies: none.
- Expected changes: route dispatch for `^/forte/?$` passes the request's `tag` query value into `renderForteBoard(string $requestedTag = '')`; resolution checks the value against the tag names already produced by `groupThreadsByTag()`, discarding anything that doesn't match.
- Verification: manual check — `GET /forte?tag=bug` resolves to `bug`; `GET /forte?tag=doesnotexist` and `GET /forte` both resolve to "no tag" (All Threads).
- Risks/open questions: keep tag comparison as a plain exact string match, consistent with how the folder tree and thread rows already compare tags.
- Touches: `Application.php` (route dispatch + `renderForteBoard()` signature only).

## Stage 2 - Server-computed initial selection and visibility
- Goal: use the resolved tag from Stage 1 to pre-select the matching folder and pre-mark non-matching thread rows as hidden, from the very first render.
- Dependencies: Stage 1 (resolved tag available).
- Expected changes: `paned_folder_tree.php` takes the resolved tag and renders that folder item with the existing "selected" styling instead of always defaulting to "All Threads"; `paned_board_thread_list.php` takes the same value and sets each row's initial `hidden` attribute using the same tag-membership check the client-side script already performs (full thread list still renders every row — only the initial `hidden` state differs).
- Verification: manual check — viewing the page source of `GET /forte?tag=bug` shows the `#bug` folder marked selected and only bug-tagged rows without a `hidden` attribute; `GET /forte` (no tag) is unchanged from today.
- Risks/open questions: the server-side and client-side tag-membership checks must stay in lockstep (same field, same exact-match rule) so initial state and subsequent clicks never disagree.
- Touches: `paned_folder_tree.php`, `paned_board_thread_list.php` (one new optional parameter each; still invoked from `forte_board.php`).

## Stage 3 - Push URL state on tag selection
- Goal: clicking a folder updates the URL to `/forte?tag={tag}` (or `/forte` for "All Threads"), with no page reload, in addition to today's instant filtering.
- Dependencies: none (can be built and verified independently of Stages 1-2).
- Expected changes: the existing folder-click handler in `paned_board_reader.js` also calls `history.pushState` with the new URL after filtering; re-clicking the already-selected folder does not push a duplicate history entry.
- Verification: manual check — clicking through several tags updates the address bar to match each one, confirmed via browser dev tools that no new document request fires.
- Risks/open questions: none expected; purely additive to the existing click handler.
- Touches: `paned_board_reader.js` only.

## Stage 4 - Back/forward support
- Goal: browser back/forward moves between previously-selected tag filters, matching the URL at each step.
- Dependencies: Stage 3 (history entries must exist to navigate between).
- Expected changes: a `popstate` listener in `paned_board_reader.js` re-applies the same filtering logic used for clicks (reading the tag from `location.search`), routed through a shared "apply filter" function that does not itself call `pushState` (only the click handler does), so back/forward can't recursively rewrite history.
- Verification: manual check — click through three different tags, then use back/forward repeatedly; folder selection and visible thread list correctly track each step in both directions.
- Risks/open questions: confirm the click handler and the `popstate` handler share the same underlying filter-application code so behavior can't drift between the two entry points.
- Touches: `paned_board_reader.js` only.
