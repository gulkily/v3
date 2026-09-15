# Forte Reactions — Step 1: Solution Assessment

## Problem
Forte's board has no Like/Flag reactions; classic already has this via `/api/apply_thread_tag` / `/api/apply_post_tag` + `thread_reactions.js`, but that script's thread-level binding (`document.querySelector("[data-thread-reactions-root]")`) assumes exactly one thread on screen — Forte's board pre-renders every thread's content pane and reply tree at once (just `hidden`), so hundreds of thread-level roots would coexist in the DOM simultaneously.

## Option A: Extend `thread_reactions.js`'s thread-level binding to `querySelectorAll`
Mirrors the pattern its own sibling function (`bindPostReactions`, one function below) already uses for post-level reactions, which is already multi-root-safe today. Add reaction markup to Forte's content-pane and reply-tree templates.
- Pros: small, symmetric fix (matches an already-adjacent pattern in the same file, not a new one); delivers both thread- and post-level reactions in one cycle; no fork.
- Cons: reply-tree markup (`paned_thread_reply_tree.php`) builds raw HTML strings today, not partials — adding button markup there is a real (if small) template change, not a drop-in reuse like `reply_form.php` was.

## Option B: Ship post-level (reply) reactions only this cycle
Skip thread-level Like; post-level already works multi-root via the existing `querySelectorAll`.
- Pros: zero JS changes needed to `thread_reactions.js`.
- Cons: delivers half the feature; defers the (small) thread-level fix rather than just doing it.

## Option C: Build Forte-native reaction handling from scratch
- Pros: none identified over Option A.
- Cons: forks working fetch/optimistic-UI/state logic for no functional gain — against this project's reuse-first preference.

## Recommendation
**Option A.** The DOM-shape mismatch has an already-proven, symmetric fix sitting one function away in the same file — not a novel problem like the earlier signing single-root case, which had no such sibling pattern to copy.
