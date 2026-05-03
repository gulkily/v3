# Thread Inline Reply Form Plan V1

## Goal

Add a compact reply composer directly on thread pages so a reader can start replying without leaving the thread. The composer should render small at rest, expand into the full reply form when clicked or focused, and submit through the existing signed compose reply flow.

## Current State

- Thread pages render the thread header and post cards in `templates/pages/thread.php`.
- Each post card has a `Reply` link to `/compose/reply?thread_id=...&parent_id=...` from `templates/partials/post_card.php`.
- The full reply form lives in `templates/pages/compose_reply.php`.
- Reply submission is handled by `Application::handleComposeReplySubmit()` and redirects back to the thread with `created_post_id`.
- Thread pages already load `/assets/openpgp.min.js` and `/assets/browser_signing.js`, so inline compose markup can reuse the existing identity, signing, normalization, and draft code.
- `browser_signing.js` binds the first `[data-compose-root]` and its `[data-compose-form]`, so the first implementation should keep one inline composer per thread page unless that binding is generalized.

## Product Behavior

- Show one compact "Reply to thread" composer near the bottom of the thread after the existing posts.
- The compact state should take little vertical space: a single-line prompt or short textarea-like control plus no advanced fields.
- Clicking, focusing, or keyboard-opening the compact composer expands it to the full reply form.
- The expanded form should include the same functional fields as `/compose/reply`: hidden `thread_id`, hidden `parent_id`, hidden `author_identity_id`, board tags, body, normalization status, identity status, and submit button.
- The inline reply should default to replying to the thread root post. Existing per-post `Reply` links remain available for replying to a specific post.
- After successful submission, the existing redirect should return to `/threads/{thread_id}?created_post_id=...`.
- Without JavaScript, the user should still be able to reach a working reply path. Either keep the existing `/compose/reply` link visible in the compact form or use native `details` so expansion is possible without custom JS.

## Implementation Plan

## Stage 1 - Extract Shared Reply Form Markup

- Create a reusable reply form partial, likely `templates/partials/reply_form.php`.
- Move the field markup from `templates/pages/compose_reply.php` into the partial without changing field names or `data-compose-*` attributes.
- Keep page-specific wrapper content in `compose_reply.php`, including the title, feedback partial, and thread/parent ID display.
- Parameters should cover `threadId`, `parentId`, `boardTags`, `body`, submit label, and an optional compact/inline class.
- Verification:
  - `/compose/reply?thread_id=root-001&parent_id=root-001` still renders the same required fields.
  - Existing compose normalization smoke tests still pass.

## Stage 2 - Render Compact Inline Composer On Thread Pages

- Add a reply composer card after the rendered posts in `templates/pages/thread.php`.
- Use the shared reply form partial with `threadId = $thread['root_post_id']` and `parentId = $thread['root_post_id']`.
- Wrap it in a single `[data-compose-root]` so `browser_signing.js` can bind existing identity and draft behavior.
- Use a compact container class such as `inline-reply-composer` and an expanded-state class/attribute.
- Include a plain link to `/compose/reply?thread_id=...&parent_id=...` as fallback or secondary action.
- Keep existing post-card reply links unchanged.
- Verification:
  - Thread smoke test asserts the inline composer exists with root thread hidden IDs.
  - Existing post-card reply links are still present.

## Stage 3 - Add Expansion Behavior And Styling

- Add CSS in `public/assets/site.css` for the compact and expanded composer states.
- Prefer stable dimensions: compact state should not cause large layout shifts around the post list, and expanded state should match the existing form spacing.
- Add small JS only if native `details` is not sufficient. If JS is needed, create a focused asset such as `/assets/inline_reply_form.js`.
- Expansion triggers:
  - click or focus inside the compact control
  - keyboard activation on the compact trigger
  - draft restoration with existing body text should start expanded so saved text is visible
- Avoid hiding required form fields from assistive technologies in a way that blocks completion after expansion.
- Verification:
  - Manual browser check: thread page loads compact, expands on click/focus, accepts typing, and submits.
  - Check mobile width for no overlapping controls or clipped button text.

## Stage 4 - Tests And Static Artifacts

- Extend `LocalAppSmokeTest::testApplicationRendersCoreRoutes` for the inline reply composer.
- Add or extend browser-signing JS tests if expansion behavior touches `browser_signing.js`.
- If a new JS asset is introduced, confirm it is included only on thread pages and receives the versioned asset path from the layout.
- Run `php tests/run.php`.
- If static artifact generation depends on anonymous thread rendering, confirm the new inline form and asset references appear in generated thread artifacts.

## Design Notes

- Reuse the existing compose POST endpoint. Do not add an API or database change.
- Keep one inline composer for the first version. Multiple inline forms would require generalizing `browser_signing.js` from `querySelector("[data-compose-root]")` to multiple compose roots and resolving draft-key collisions carefully.
- The inline composer should feel like part of the thread page, not a modal. A compact card with an expanding body is enough.
- The `/compose/reply` page remains useful for direct links, no-JS fallback, and specific parent replies from individual posts.

## Risks

- Duplicating reply form markup could let inline and standalone compose behavior drift; extracting a partial reduces this risk.
- If the compact form renders hidden fields incorrectly, draft restore or signing could fail because `browser_signing.js` depends on field names and `data-compose-kind="reply"`.
- If expansion uses only JavaScript, no-JS users could lose the inline path; keep a direct compose link or native expansion fallback.
- Adding multiple compose roots on a page without updating `browser_signing.js` would bind only one form.

## Implementation Progress

## Stage 1 - Extract Shared Reply Form Markup

- Status: complete.
- Changes:
  - Added `templates/partials/reply_form.php` for the shared signed reply form fields.
  - Updated `templates/pages/compose_reply.php` to render the shared partial while preserving the existing page wrapper and identity status placement.
- Verification:
  - `php tests/run.php LocalAppSmokeTest BrowserSigningNormalizationTest` passed.

## Stage 2 - Render Compact Inline Composer On Thread Pages

- Status: complete.
- Changes:
  - Added a single inline reply composer at the bottom of `templates/pages/thread.php`.
  - The inline composer uses the shared reply form partial and defaults to replying to the thread root post.
  - Existing per-post reply links remain unchanged, and the inline composer includes a direct full-page compose fallback link.
- Verification:
  - `php tests/run.php` passed.
  - Stage 4 will add explicit smoke coverage for the inline composer.
