# Forte Thread Selection URL Sync — Step 2: Feature Description

## Problem
Selecting a thread on Forte's board never updates the URL — only tag/sort changes do — so reloading or sharing a link loses the current thread selection. Worse, even the URLs that *do* carry a thread/reply today (permalinks, reply redirects) only restore that state via client-side JS after the page has already painted its default "nothing selected" view — so every one of those loads flashes the wrong state first.

## User Stories
- As a Forte board-view reader, I want reloading the page to restore both my tag filter and my selected thread, so I don't lose my place.
- As anyone opening a Forte URL that names a tag, thread, and/or reply, I want the correct state visible in the very first paint, not flashed-in a moment later by JS.
- As a Forte board-view reader, I want a deliberate click on a thread to be a real, back-able navigation, so pressing Back returns me to the thread I was just on.
- As a Forte board-view reader browsing quickly with arrow keys or Prev/Next, I don't want every thread I pass through to pile up in my browser history.
- As a reader who wants different history behavior than the default, I want that adjustable without waiting on a settings UI to ship.

## Core Requirements
- A thread-row click updates the URL (`selected={threadId}`, tag preserved) via `history.pushState`, matching the existing tag/sort pattern — Back returns to the previous state.
- Arrow-key row navigation and Prev/Next toolbar stepping update the URL via `history.replaceState` by default — no new back-history entry per step (per approved Step 1, Option C).
- This click-vs-stepping split is a per-viewer `localStorage` preference, not a hardcoded rule, with three named values:
  - `"click-only"` (default, used when unset or invalid) — click pushes, keyboard/button stepping replaces.
  - `"always"` — every selection change pushes, including keyboard/button stepping.
  - `"never"` — every selection change replaces, including clicks.
  - No settings UI ships in this cycle; the value is only reachable by setting it directly (e.g. via devtools).
- **The board renders `selected=`/`created_post_id=` server-side, the same way it already renders `tag=`**: the requested thread's row and content article are marked selected/visible, its Reply button starts enabled (not the usual disabled-until-JS state), and the requested reply gets its highlight class — all directly in the initial HTML, mirroring the existing `$selectedTag`-driven `hidden` computation in `paned_board_thread_list.php`, not a new mechanism. This closes the flash for every URL carrying this state (permalinks and reply redirects included, not just new URL-sync traffic), by construction. Scroll position is the one thing this can't cover — the existing client-side scroll-into-view calls (list row and highlighted reply) still run after load and remain necessary; SSR only guarantees the *content* is already correct, not already scrolled to.
- **`selected` wins over `tag` on conflict**: if the requested thread doesn't carry the requested tag (only reachable via a hand-edited URL, or a thread's tags changing after a link was shared — normal clicking/pushState never produces this, since a click can only target an already-visible, correctly-tagged row), the server keeps the thread selected and drops the tag filter to "All Threads" rather than the other way around.
- Invalid or unrecognized `selected=`/`created_post_id=` values degrade to exactly today's default (no selection) — reusing the same validate-and-fallback pattern `resolveForteBoardTag()` already applies to `tag=`, not a new error path.
- Browser Back/Forward through pushed thread-selection states (no server round-trip involved) still needs client-side handling — the existing `popstate` listener only restores tag/sort today and must also restore thread selection.
- No change to tag/sort URL syncing, which already works today.

## Shared Component Inventory
- `$selectedTag`-driven server-side `hidden`/visibility rendering (`paned_board_thread_list.php`) — **pattern reused**, extended with the equivalent for `selected=`/`created_post_id=` across `paned_board_thread_list.php`, `paned_board_content_pane.php`, and `paned_thread_reply_tree.php`.
- The board's client-side `initialSelected`/`initialCreatedPostId` restore logic (`paned_board_reader.js`) — **kept, not removed**: harmless to re-apply over already-correct server-rendered state, and still the only mechanism available for the `popstate` (no-reload) case.
- `pushStateIfChanged` / `urlForState` — **extended**: gains the current thread selection alongside tag/sort; a parallel `replaceState` path added for the non-pushing case.
- This project's existing `localStorage` read pattern (theme/thread-density prefs, try/catch-wrapped, silent fallback) — **reused** for the new history-mode preference.

## Simple User Flow
1. Reader opens any Forte URL carrying `tag=`/`selected=`/`created_post_id=` (typed, reloaded, or shared) — the correct tag, thread, and highlighted reply are all visible immediately, no flash.
2. Reader clicks a thread — URL updates, tag preserved; Back will return to the prior state.
3. Reader arrow-keys or Prev/Next's through several threads — URL keeps reflecting the current thread without piling up history entries.
4. Reader presses Back after clicking a thread — the previous thread/tag state is restored without a full page reload.
5. A reader (or developer) who sets the `localStorage` preference to `"always"` or `"never"` gets that behavior instead, with no code changes.

## Success Criteria
- Opening `/forte?tag=X&selected=Y&created_post_id=Z` directly (fresh load, not a same-session click) shows the correct tag, selected thread, highlighted reply, and an already-enabled Reply button in the first rendered response — verifiable without JS running.
- Opening a URL where `selected=Y` doesn't carry tag `X` shows `Y` selected under "All Threads," not `Y` unselected under tag `X`.
- Opening a URL with a garbage or nonexistent `selected=`/`created_post_id=` renders identically to a URL with neither present.
- Reloading `/forte` after selecting a thread restores that exact thread and tag filter.
- A thread click is a real back-history entry; Back returns to the previous thread/tag state without a server round-trip.
- Rapid keyboard/button stepping through many threads does not create one history entry per step, by default.
- Setting the `localStorage` preference to `"always"` or `"never"` measurably changes this behavior.
- Tag/sort URL syncing, and the existing permalink/reply-redirect flows, are completely unaffected in outcome (only when the correct state becomes visible changes).
