# Forte Hidden Content Permalinks — Step 1: Solution Assessment

## Problem
Activity-view links to identity/bootstrap/approval-only content fall back to the classic (non-Forte) `/posts/`/`/threads/` pages, because those threads are excluded entirely from the Forte board's own thread query, so `/forte?selected=<id>` can't resolve them.

## Options

**Option A — Resolve one hidden thread on demand, kept out of the list**
- Server: when `?selected=<id>` doesn't match the board's normal (filtered) thread set, fetch that one thread directly, feed it only to the content pane (not the row list/tag groups/counts), and build its reply tree.
- Client JS: let `restoreSelectionFromUrl()` select a thread with no matching visible row.
- Pros: Reuses the existing `/forte?selected=...&created_post_id=...` URL convention already used everywhere in Forte (reply permalinks, profile links, board's own "#" permalink) - no new scheme; reuses the board's existing single content-pane rendering path.
- Cons: Touches ~4-5 files (render function, two templates, client JS); needs care so the injected thread never leaks into the list, tag groups, or counts.

**Option B — Dedicated single-thread page**
- A new, separate route/page (e.g. `/forte/thread/<id>`) that renders just one thread, list-less, used only for links to excluded content.
- Pros: Fully isolated from the main board's list/tag-group logic - no risk of leaking a hidden thread into it.
- Cons: A second way to render a thread, inconsistent with the pattern this codebase already follows (one shared rendering path, reused, not forked - e.g. the Commits view work reused `activity_commit_manifest.php` rather than forking it); more new surface area than Option A, not less.

**Option C — Re-skin the classic pages only**
- Leave routing as-is; give `/posts/`/`/threads/` Forte-like CSS so they at least *look* consistent.
- Pros: Smallest possible change - pure template/CSS, no board-selection or JS logic touched.
- Cons: Doesn't meet the actual goal - it's a lookalike, not real Forte navigation, and still lacks the paned reply tree/reactions/compose affordances.

**Option D — Modal summary, with a "load full thread" link**
- A scrollable summary dialog (post/thread preview), opened in place from wherever the link is clicked - reuses the existing `showProfileSummary()`/`profileSummaryDialog` pattern already shipped for profiles (native `<dialog>`, API-fetched content, loading/error states, a "full page" link) rather than inventing a new mechanic. A "View full thread" link inside it loads the thread into the normal board panes.
- Pros: Solves "stay in Forte" immediately and cheaply for the common case (glancing at content) - no changes to the board's shared thread array, tag groups, or `restoreSelectionFromUrl()`'s row-matching; smaller, self-contained surface (one dialog partial, one small endpoint, one link-interception handler), matching an established pattern instead of a new one.
- Cons: Doesn't by itself solve the harder problem - the "View full thread" link still needs *something* like Option A's board-side resolution to actually land on a working page for a hidden thread, so it decouples the easy 80% case from the hard 20% rather than replacing it. If "full thread" access matters immediately, Option A's mechanism is still needed eventually, just deferred.

## Decision
**Option D**, with Option A's board-side resolution built as its "View full thread" destination rather than as a standalone feature. This ships the high-value, low-risk piece first (a working in-place preview for every hidden-content link, reusing the profile-summary dialog pattern with no board/JS surgery) and folds Option A's harder, riskier board changes in as the modal's secondary action - so the full-thread case still gets solved, just as part of a smaller, better-sequenced change instead of the first thing shipped.
