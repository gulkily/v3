# Thread Root Card Consolidation Plan v1

## Goal

The thread page currently renders two adjacent cards for the root post:

1. A thread summary card in `templates/pages/thread.php` with the title, author/date metadata, labels, reply count, and thread-level Like action.
2. The root post's normal post card from `templates/partials/post_card.php`, immediately below it, with the same author/date metadata, body, Reply/Flag actions, and permalink.

This makes the top of a thread feel duplicated. Consolidate those two cards into one root-thread card that presents the thread title and root post content together, while leaving later reply cards unchanged.

## Current Behavior

The screenshot `Screenshot 2026-05-22 at 22-23-40 do you know any existing test threads on this server.png` shows:

- The first card contains the title `do you know any existing test threads on this server`.
- It shows `by ilyag on May 13, 2026 at 12:11 UTC`.
- It shows labels, reply count, and Like.
- The next card repeats `by ilyag on May 13, 2026 at 12:11 UTC`.
- The next card contains the actual root post body and actions.

The relevant code paths are:

- `templates/pages/thread.php`: renders the thread summary card, then loops over all `$posts`.
- `templates/partials/post_card.php`: renders every post card, including the root post.
- `public/assets/thread_reactions.js`: expects a `data-thread-reactions-root` container for thread-level reactions.
- `public/assets/site.css`: defines shared `.card`, `.post-card`, `.thread-reaction-row`, and `.post-card-actions` styling.
- `src/ForumRewrite/Application.php`: builds the thread page model with `$thread`, `$posts`, reaction state, post analysis state, and agent reply state.

## Proposed UI Shape

On a thread page, render the root post as one combined card:

- `h1` thread title at the top.
- One author/date metadata line for the root post.
- Optional labels.
- Optional reply count.
- Root post body.
- One action row containing:
  - `Reply`
  - root post `Flag`
  - thread-level `Like`
  - root post permalink
  - post analysis details when available for the root post

Then render only non-root replies as ordinary post cards.

The root card should keep:

- `id="post-<root_post_id>"` so existing anchors still work.
- `data-post-id="<root_post_id>"` so post-level actions still work.
- `data-thread-reactions-root` and `data-thread-id="<root_post_id>"` so thread-level Like still works.
- Existing feedback elements for post-level and thread-level reaction JavaScript.

## Implementation Steps

1. Split root post from replies in `templates/pages/thread.php`.
   - Treat `$posts[0]` as the root post when present.
   - Render a new combined root card before the reply loop.
   - Loop over `array_slice($posts, 1)` for reply cards.
   - Keep a defensive fallback for an empty `$posts` array, even though a valid thread should normally include the root post.

2. Add a dedicated root-thread partial.
   - Create `templates/partials/thread_root_card.php`.
   - Reuse the same variables and helper conventions as `post_card.php`.
   - Include the root title, root metadata, labels, reply count, body, actions, permalink, post analysis, agent-authored marker if applicable, and possibly-related block if the root post has related content.
   - Avoid duplicating large chunks of post analysis markup if possible. If the duplication becomes substantial, extract the post analysis details into a second partial used by both `post_card.php` and `thread_root_card.php`.

3. Keep ordinary reply cards stable.
   - Leave `templates/partials/post_card.php` behavior unchanged for replies and standalone `/posts/<id>` pages unless a small extraction is needed.
   - Do not add thread title or thread-level Like to ordinary reply cards.

4. Adjust CSS only where the combined root card needs spacing.
   - Add a specific class such as `.thread-root-card`.
   - Keep the existing `.card` and `.post-card` base behavior.
   - Ensure the title, metadata, labels, body, and action row read as one card rather than two stacked cards.
   - Preserve the bottom-right permalink positioning if using `.post-card`.

5. Verify JavaScript selectors still work.
   - Confirm thread Like still finds `data-thread-reactions-root`.
   - Confirm root post Flag still finds `data-action="apply-post-tag"` and `data-post-id`.
   - Confirm feedback elements remain distinct:
     - `data-role="thread-reaction-feedback"` for Like.
     - `data-role="post-reaction-feedback"` for Flag.

6. Update or add tests.
   - Extend the local smoke coverage in `tests/LocalAppSmokeTest.php` if it already asserts rendered thread HTML.
   - Otherwise add a focused render assertion that a thread page includes the root author/date metadata only once before the first reply.
   - Assert that `data-thread-reactions-root`, `data-post-id`, the thread title, root body, Reply, Flag, and Like all appear in the combined card.

7. Manually check the visual result.
   - Start the local app.
   - Open the thread shown in the screenshot, or another thread with replies.
   - Confirm there is no separate title-only card above the root post body.
   - Confirm replies still render as separate cards.
   - Confirm the inline reply composer remains below the reply list.

## Acceptance Criteria

- The thread page renders a single top card containing both the thread title and root post body.
- The root author/date metadata appears only once at the top of the thread.
- Thread labels, reply count, Like, root Reply, root Flag, root permalink, and root post analysis remain available.
- Reply cards after the root post are visually and behaviorally unchanged.
- Standalone post pages still render correctly through `post_card.php`.
- The PHP test suite passes with `php tests/run.php`.

## Risks

- Extracting shared post analysis markup could accidentally change standalone post rendering. Keep the extraction minimal and compare generated HTML around post analysis.
- Moving `data-thread-reactions-root` onto a card that also has post-level actions could expose assumptions in `thread_reactions.js`. Verify both Like and Flag manually or with DOM-level checks.
- If `$posts[0]` is not always the root post, the template should locate the post whose `post_id` equals `$thread['root_post_id']` instead of relying only on order. The SQL currently orders by `sequence_number ASC`, so the root should be first, but an explicit lookup is more robust.

## Execution Log

- Step 0: Created this plan on branch `thread-root-card-consolidation`.
- Step 1: Split the root post from replies in `templates/pages/thread.php` and added `templates/partials/thread_root_card.php` for the combined root-thread card.
- Step 2: Added smoke assertions covering the combined root card, root metadata de-duplication, and root-before-reply ordering.
- Step 3: Verified the implementation with `php tests/run.php`; all tests passed.
