# Forte Thread Selection URL Sync — Step 3: Development Plan

## Stage 1
- Goal: Resolve the effective selected thread and tag server-side, including the mismatch/invalid-value rules from Step 2.
- Dependencies: none (Step 2 approved)
- Expected changes: new method, e.g. `resolveForteBoardSelection(array $threads, string $requestedTag, string $requestedSelected): array` returning a validated `{tag, selectedThreadId}` pair — mirrors `resolveForteBoardTag()`'s validate-and-fallback shape; `selectedThreadId` is `''` unless `requestedSelected` matches a real thread; if it does but that thread doesn't carry `requestedTag`, returned `tag` is `''` (drops to All Threads) instead of dropping the selection.
- Verification approach: unit-level check via a few representative inputs (valid match, valid thread + mismatched tag, nonexistent thread ID, empty/garbage input) confirming the returned pair matches Step 2's stated rules.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: new resolver method only — no callers wired yet.

## Stage 2
- Goal: Pre-select the thread's row and enable Reply in the initial HTML.
- Dependencies: Stage 1
- Expected changes: `renderForteBoard()` calls the new resolver and passes `selectedThreadId` down; `paned_board_thread_list.php` renders `paned-list-row--selected`/`aria-selected="true"`/`tabindex="0"` for the matching row (mirroring how `$selectedTag` already drives `hidden`); the board's Reply toolbar button renders without `disabled` when a thread is pre-selected.
- Verification approach: `curl` (no JS) a URL with a valid `selected=`, confirm the row's selected classes/attributes and the enabled Reply button appear in the raw response.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `renderForteBoard()` (extended), `paned_board_thread_list.php` (extended), `forte_board.php` (Reply button markup extended).

## Stage 3
- Goal: Pre-show the selected thread's content article.
- Dependencies: Stage 1 (Stage 2 not required, independent template)
- Expected changes: `paned_board_content_pane.php`'s per-thread article's `hidden` attribute becomes conditional on `selectedThreadId` instead of unconditional.
- Verification approach: `curl` the same URL, confirm only the target thread's article lacks `hidden` and the placeholder article gains it instead (or vice versa when nothing is selected).
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `paned_board_content_pane.php` (extended).

## Stage 4
- Goal: Pre-highlight the requested reply within the selected thread.
- Dependencies: Stages 1 and 3 (need a resolved thread to validate the reply against)
- Expected changes: `created_post_id` is validated against the resolved thread's own reply posts (ignored if it belongs to a different thread or doesn't exist) and threaded down to `paned_thread_reply_tree.php`, which applies `paned-highlight-new` to the matching node's initial markup.
- Verification approach: `curl` a URL with a valid `selected=`+`created_post_id=` pair, confirm the target reply node carries the highlight class in the raw response; confirm a `created_post_id` belonging to a *different* thread is silently ignored.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `paned_thread_reply_tree.php` (extended), `renderForteBoard()` (passes the validated ID through).

## Stage 5
- Goal: Push/replace the URL on interactive thread selection, per the three-mode `localStorage` preference.
- Dependencies: none (client-side only; independent of Stages 1-4)
- Expected changes: `urlForState()` gains the current thread selection; a `history-mode` read helper (try/catch-wrapped, matching this project's existing `localStorage` preference pattern) resolves to `"click-only"`/`"always"`/`"never"`; the row-click handler and the keyboard/Prev-Next stepping paths call `pushStateIfChanged` or a new `replaceStateIfChanged` counterpart according to that mode.
- Verification approach: headless-browser test per mode — default (`"click-only"`): click pushes (Back works), stepping replaces (no history growth); `"always"`: stepping also pushes; `"never"`: clicking also replaces.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `paned_board_reader.js` (extended: `urlForState`, click/stepping handlers, new `localStorage` read).

## Stage 6
- Goal: Restore thread selection on Back/Forward (no server round-trip).
- Dependencies: Stage 5
- Expected changes: the existing `popstate` listener (currently tag/sort only) also reads `selected=`/`created_post_id=` from the now-current URL and calls the existing `selectThread()`/highlight logic, matching what the initial-load path already does.
- Verification approach: headless-browser test — click two different threads (pushing history each time under `"always"` or after a click under default mode), press Back, confirm the previous thread is restored without a network request.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `paned_board_reader.js` (`popstate` handler extended).

## Stage 7
- Goal: Full regression check.
- Dependencies: Stages 1-6
- Expected changes: none (verification only)
- Verification approach: confirm the flash is gone for existing permalink/reply-redirect URLs (not just new ones) via `curl`; re-verify tag/sort URL syncing is unaffected; re-verify `forte_reactions` and `forte_post_permalink` flows end-to-end; confirm all three `localStorage` modes behave correctly together with the mismatch/invalid-value fallback rules; confirm board load timing at current volume stays reasonable with the added server-side resolution work (no new queries expected — resolution works over already-fetched `$threads`/reply data).
- Risks or open questions: none identified.
- Canonical components/API contracts touched: none new — integration check across Stages 1-6.
