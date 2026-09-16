# Forte Thread Selection URL Sync — Step 4: Implementation Summary

## Stage 1 - Server-side selection resolver
- Changes:
  - `Application.php`: new `resolveForteBoardSelection(array $threads, array $tagGroups, string $requestedTag, string $requestedSelected): array` returning `{tag, selectedThreadId}` — mirrors `resolveForteBoardTag()`'s validate-and-fallback shape, reuses `findTagGroup()` for the mismatch check.
- Verification:
  - `php -l` clean.
  - Unit-level check via `ReflectionMethod` against six representative inputs: valid tag+thread match, mismatched tag (thread lacks the requested tag), no tag with a valid thread, nonexistent thread ID, empty `selected`, and a nonexistent tag — all six returned exactly the pair Step 2's rules specify (selected wins over a mismatched tag; invalid/missing values fall back to no selection, same as today's default).
- Notes:
  - No callers wired yet, as planned — this stage only adds the resolver.

## Stage 2 - Pre-select the row and enable Reply
- Changes:
  - `renderForteBoard()`: calls the Stage 1 resolver (replacing the direct `resolveForteBoardTag()` call), passes `selectedThreadId` to the page; route dispatch reads `?selected=` from the query string.
  - `paned_board_thread_list.php`: the matching row gets `paned-list-row--selected`, `aria-selected="true"`, and the tab stop (`tabindex="0"`) when a thread is pre-selected — the "first visible row gets the tab stop" fallback only applies when nothing is selected.
  - `forte_board.php`: the Reply toolbar button renders without `disabled` when a thread is pre-selected.
- Verification:
  - `php -l` clean.
  - `curl` (no JS) `/forte?selected=root-001`: raw response shows `class="paned-list-row paned-list-row--selected"`, `aria-selected="true"`, `tabindex="0"` on the matching row, and the Reply button with no `disabled` attribute.
  - `curl` `/forte` (no `selected=`): zero `paned-list-row--selected` occurrences, Reply button still `disabled` — default case unaffected.
  - Re-ran the broader interactive regression suite (New Thread dialog, Reply flow, multi-thread Like) — all still pass, zero console errors.
- Notes: none identified.

## Stage 3 - Pre-show the selected thread's content article
- Changes:
  - `paned_board_content_pane.php`: the placeholder article gets `hidden` when a thread is pre-selected; each thread's own article's `hidden` attribute becomes conditional on matching `selectedThreadId` instead of unconditional.
- Verification:
  - `php -l` clean.
  - `curl` `/forte?selected=root-001`: the target article has no `hidden` attribute, the placeholder does.
  - `curl` `/forte` (no `selected=`): placeholder visible (no `hidden`), all 520 thread articles still `hidden` — default case unaffected.
  - Re-ran the broader interactive regression suite — all still pass, zero console errors.
- Notes: none identified.

## Stage 4 - Pre-highlight the requested reply
- Changes:
  - Route dispatch reads `?created_post_id=` and passes it to `renderForteBoard()`.
  - `renderForteBoard()`: validates it against the *resolved selected thread's own* reply posts (found while building `$replyTreesByThreadId`/`$allPostIds` — no extra query), ignoring it if it belongs to a different thread or doesn't exist; passes the validated `highlightedPostId` to the page.
  - `paned_thread_reply_tree.php`: applies `paned-highlight-new` to the matching node's initial class list.
- Verification:
  - `php -l` clean.
  - `curl` a valid `selected=`+`created_post_id=` pair (a thread and one of its own replies): the target node's class list includes `paned-highlight-new` in the raw response.
  - `curl` the same `created_post_id` against a *different* `selected=` thread: zero `paned-highlight-new` occurrences anywhere — cross-thread mismatch correctly ignored.
  - Re-ran the broader regression suite plus the permalink round-trip test — all still pass, zero console errors; the permalink flow now gets its highlight server-side too, not just via the pre-existing client JS.
- Notes: none identified.

## Stage 5 - Push/replace the URL on interactive selection
- Changes:
  - `paned_board_reader.js`: `urlForState()` gains a `selectedThreadId` param; new `replaceStateIfChanged()` counterpart to the existing `pushStateIfChanged()`; new `readHistoryMode()` (try/catch-wrapped `localStorage.getItem("forte-board-history-mode")`, validating `"always"`/`"never"`, defaulting to `"click-only"`) read once at load; `syncSelectionUrlForClick()`/`syncSelectionUrlForStepping()` wrap the push-vs-replace decision per Step 1's Option C, wired into the row-click handler, the list's arrow-key keydown handler, and `stepSelection()` (Prev/Next). The tag-click and sort-click URL builders now also pass through the current selection so switching tag/sort doesn't drop it from the URL.
- Verification:
  - `node --check` clean.
  - Headless-browser test, default mode (unset preference): two clicks each push a history entry (Back returns to the prior thread); two subsequent arrow-key steps add zero history entries (`history.length` delta 0), confirming `replaceState`.
  - Headless-browser test, `"always"`: two arrow-key steps *do* push (`history.length` delta 2).
  - Headless-browser test, `"never"`: two clicks push zero entries (`history.length` delta 0) and the URL still reflects the final selection.
  - Re-ran the broader regression suite — all still pass, zero console errors.
- Notes: none identified.

## Stage 6 - Restore thread selection on Back/Forward
- Changes:
  - `paned_board_reader.js`: extracted the initial-load restoration block into a shared `restoreSelectionFromUrl(scrollRowIntoView)`, now also resetting to the placeholder (`resetContentPane()`) when the current URL names no thread (or one that's hidden/nonexistent) — needed for popping all the way back to a bare `/forte`. Both the initial-load path and the `popstate` handler now call this same function, so their behavior can't drift apart.
- Verification:
  - `node --check` clean.
  - Headless-browser test: clicked two different threads (content pane correctly updates each time); pressed Back — the *content pane*, not just the list row, correctly reverted to the first thread (this exact restoration never existed before this stage — `popstate` previously only handled tag/sort); pressed Back again — content pane correctly reset to the placeholder with nothing selected.
  - Re-ran the permalink round-trip and the broader regression suite — all still pass, zero console errors.
- Notes: none identified.
