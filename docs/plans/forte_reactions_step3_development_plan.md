# Forte Reactions — Step 3: Development Plan

## Stage 1
- Goal: Make `thread_reactions.js`'s thread-level (Like) binding safe for many roots on one page.
- Dependencies: none (Step 2 approved)
- Expected changes: in `bindThreadReactions`'s init call, replace the single `document.querySelector("[data-thread-reactions-root]")` with a loop over `document.querySelectorAll(...)`, mirroring the existing `bindPostReactions` loop immediately below it in the same file.
- Verification approach: `node --check`; load classic's thread page (still exactly one root there) and confirm Like still works with zero regression; full multi-root behavior gets its real test once Stage 2 adds more than one root to a page.
- Risks or open questions: none identified — this is a one-function change matching an adjacent, already-working pattern.
- Canonical components/API contracts touched: `public/assets/thread_reactions.js` (extended).

## Stage 2
- Goal: Add Like (thread) and Flag (root post) buttons to Forte's board content pane.
- Dependencies: Stage 1
- Expected changes: `paned_board_content_pane.php`'s per-thread article gains the same `data-thread-reactions-root`/`data-action="apply-thread-tag"`/`data-tag="like"` and `data-action="apply-post-tag"`/`data-tag="flag"` attributes `thread_root_card.php` already uses, styled as new Forte button markup (not a partial reuse, per Step 2); `renderForteBoard()` adds `thread_reactions.js` to the board's script list.
- Verification approach: headless-browser test — Like a thread, confirm optimistic pending state then confirmed state with no page reload; Flag a thread's root post; confirm a second thread's Like button works independently (the actual multi-root case Stage 1 exists for).
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `paned_board_content_pane.php` (extended), `renderForteBoard()` (script list extended).

## Stage 3
- Goal: Add Flag buttons to reply nodes in the reply tree.
- Dependencies: Stage 2 (`thread_reactions.js` already loaded)
- Expected changes: `paned_thread_reply_tree.php`'s raw-HTML node builder gains a Flag button per reply node with the same `data-action="apply-post-tag"`/`data-tag="flag"`/`data-post-id` contract `post_card.php` already uses.
- Verification approach: headless-browser test — Flag a reply post nested under a thread, confirm state updates in place; confirm it doesn't interfere with the existing reply-tree rendering (highlighting, nesting).
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `paned_thread_reply_tree.php` (extended).

## Stage 4
- Goal: Show each viewer's existing reaction state on board page load, not just after a fresh click.
- Dependencies: Stages 2-3 (buttons must exist to carry the disabled/label state)
- Expected changes: new method `viewerThreadTagsForThreads(array $threadIds, string $tag, string $identityId): array` (mirrors the existing `viewerPostTagsForPosts()`, no equivalent bulk thread-tag lookup exists yet); `renderForteBoard()` calls it plus the existing `viewerPostTagsForPosts()` (called once across every post ID on the board, not per-thread) to compute already-liked/already-flagged state; templates from Stages 2-3 render the disabled/label variants using that data.
- Verification approach: like/flag something, reload `/forte`, confirm the button renders already-disabled with the applied label; confirm an anonymous/no-identity viewer sees the normal enabled buttons (no errors from a null identity).
- Risks or open questions:
  - Confirm `viewerThreadTagsForThreads()` at ~500 threads stays a single bulk query, not one query per thread (same N+1 concern `forte_board_reply_tree` already solved once).
- Canonical components/API contracts touched: new `viewerThreadTagsForThreads()`, `viewerPostTagsForPosts()` (reused at larger scale), `renderForteBoard()` (extended).

## Stage 5
- Goal: Let a reaction click trigger identity preparation even if the reader never touched the reply composer.
- Dependencies: Stages 2-3 (buttons must exist)
- Expected changes: `lazy_compose_signing.js` gains a second trigger — a click listener on `[data-action="apply-thread-tag"], [data-action="apply-post-tag"]` site-wide — that calls its existing `loadSigningAssets()` (reused, same cached promise as the compose-field trigger already uses).
- Verification approach: headless-browser test — click Like without ever focusing the reply composer, confirm `openpgp_loader.js`/`browser_signing.js` load and the identity-prep flow runs; confirm the existing focus-triggered path is unaffected.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `public/assets/lazy_compose_signing.js` (extended).

## Stage 6
- Goal: Full regression check.
- Dependencies: Stages 1-5
- Expected changes: none (verification only)
- Verification approach: confirm classic's thread page reactions are byte-for-byte unaffected; confirm Forte's existing Reply/New-Thread flows still work; confirm Like/Flag across several threads on one board load all work independently; confirm board load time at current volume (~500 threads) stays reasonable with the new bulk viewer-state query added.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: none new — integration check across Stages 1-5.
