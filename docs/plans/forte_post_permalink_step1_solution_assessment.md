# Forte Post Permalink — Step 1: Solution Assessment

## Problem
Forte has no shareable direct link to one specific post — classic has `/posts/{id}`, but Forte's own board can only ever be linked at the thread level (`/forte?selected={threadId}`), with no way to point at one specific post/reply within it.

## Option A: Reuse the existing `created_post_id` highlight mechanism verbatim
Render a `#` permalink anchor on each post/reply pointing to `/forte?selected={threadId}&created_post_id={postId}#post-{postId}` — the exact same query param `paned_board_reader.js` already reads on load to select a thread, find the post, highlight it, and scroll to it (originally built for the post-reply redirect, but functionally generic).
- Pros: zero JS changes — the read/select/highlight/scroll logic already exists and is already proven; thread ID is already known at render time for every post/reply, so no new server route or lookup is needed either, just a template addition.
- Cons: parameter name (`created_post_id`) reads oddly for a link that isn't about creation — a naming nit, not a functional issue.

## Option B: Add a dedicated, accurately-named query param
Same mechanism as A, but introduce `highlighted_post_id` (or similar) as a second, generically-named param the existing on-load logic also checks.
- Pros: clearer naming for future readers of the code.
- Cons: touches the same well-tested on-load logic that's currently only used for the reply/creation path, for a purely cosmetic naming gain.

## Option C: Pure client-side hash permalink (`/forte#post-{id}`, no query param)
On load, search the DOM (every thread's content is already pre-rendered) for the target post, walk up to find its thread, then select/highlight/scroll — entirely client-side, no thread ID needed in the URL at all.
- Pros: cleanest possible URL.
- Cons: new client-side "find enclosing thread" logic that doesn't exist today, for a URL-aesthetics gain only; more surface area than A or B for the same outcome.

## Shaping finding (applies to all options)
A permalink must not silently fail when the target thread doesn't match the current tag filter — `selectThread()` skips hidden rows. Permalinks should omit `tag=` (default to All Threads) so the target row is always visible, regardless of which option is chosen.

## Recommendation
**Option A.** It needs no new code beyond a template link, reuses an already-proven mechanism verbatim, and the naming quirk is a pure readability nit not worth new code or touching working logic to avoid.
