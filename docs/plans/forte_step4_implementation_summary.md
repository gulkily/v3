# Forte Step 4 Implementation Summary

## Stage 1 - New Forte route and entry-point link
- Changes:
  - `src/ForumRewrite/Application.php`: added route `^/threads/([^/]+)/forte/?$` (right after the standard thread route) dispatching to a new `renderForte(string $threadId): ?string`, which calls the existing `fetchThread()` (returns `null` → 404 on a missing thread) and renders a stub page via `renderPageTemplate('forte.php', ...)`. No new SQL, no change to existing thread rendering.
  - `templates/pages/forte.php`: new minimal stub page template (thread title, placeholder text, link back to the standard thread view).
  - `templates/pages/thread.php`: added one link, "Open in Forte view", pointing to `/threads/{id}/forte`, using the same `$e($thread['root_post_id'])` convention as the existing thread-card link.
- Verification:
  - `php -l` on all three touched/added files: no syntax errors.
  - Started the app locally (`./v3 start 127.0.0.1:8791`) against an existing thread (`root-001`).
  - `GET /threads/root-001/forte` → `200`, renders the stub page with the correct thread title and a working "Back to standard thread view" link.
  - `GET /threads/does-not-exist/forte` → `404`, confirming the missing-thread path matches the standard thread route's behavior.
  - `GET /threads/root-001?created_post_id=` (forces PHP-fallback rendering rather than the cached static artifact) → shows the new "Open in Forte view" link pointing to `/threads/root-001/forte`.
  - Confirmed both routes are independent, stateless GET requests with no shared session/toggle state, satisfying the "open in separate tabs simultaneously" requirement by construction.
- Notes:
  - `root-001`'s default `/threads/root-001` response is served from a prebuilt static HTML artifact (`route-source: static-html`), so the new link won't appear there until the next `php scripts/build_static_artifacts.php` run — this is expected/existing static-artifact behavior, not a Stage 1 defect, and isn't part of this stage's scope.
  - No naming collision found with `/tags/`, `/posts/`, or other existing route segments.

## Stage 2 - Build reply-tree data shaping
- Changes:
  - `src/ForumRewrite/Application.php`: added `buildReplyTree(array $posts): array`, a pure function that nests the flat, `sequence_number`-ordered post list from `fetchThreadPosts()` into a tree using each post's `parent_id`. A post whose `parent_id` is missing or not present in the fetched set falls back to being treated as a root. No changes to `fetchThread()`/`fetchThreadPosts()`, no new SQL.
  - `renderForte()` now calls `fetchThreadPosts()` and `buildReplyTree()` and passes the result as `replyTree` into the (still-stub) page template's data; the stub template does not consume it yet — that's Stage 3.
- Verification:
  - `php -l`: no syntax errors.
  - Wrote a throwaway script (not committed) that used reflection to call the private `buildReplyTree()` directly with a synthetic post set covering: a root, a 3-deep reply chain (p1→p2→p3→p4), a second branch (p1→p5→p6), and an orphaned `parent_id` pointing at a post not in the set (p7). Output matched expectations exactly: both branches nested correctly under p1 in original sibling order, and the orphan (p7) fell back to appearing as a root rather than being dropped or erroring.
- Notes:
  - Orphan-parent fallback (treat as root) was the open question flagged in the Step 3 plan for this stage; resolved as above and confirmed by the test above.

## Stage 3 - List-pane partial with nesting
- Changes:
  - `templates/partials/paned_list_pane.php`: new partial rendering the Stage 2 reply tree as an indented list — a disclosure toggle and hidden `[+N]` collapse-count marker per branch (shown/toggled by Stage 5's script), an "AGENT" badge derived the same way `post_card.php`/`thread_root_card.php` already derive it (`author_label === 'reply-agent'`), and From/Date columns reusing the existing `$author`/`$timestamp` template helpers. Subject falls back to a short body excerpt when a post has no subject, since reply posts commonly don't set one.
  - `templates/pages/forte.php`: stub now renders the list pane via `$partial('partials/paned_list_pane.php', ['replyTree' => $replyTree])`.
- Verification:
  - `php -l`: no syntax errors.
  - Real-data check via the running app: `GET /threads/root-001/forte` renders a correct two-row list (root + one reply) with correct depth/indentation, correct author rendering, and a `[+1]` collapse marker on the root — no PHP warnings in the server log.
  - Synthetic check (throwaway script, not committed): rendered the partial directly against a fabricated 4-post tree with a 3-level-deep chain (p1→p2→p3, p3 authored by `reply-agent`) plus a second branch (p1→p4). Confirmed: correct indentation at each depth, AGENT badge appears only on the agent-authored post, `[+N]` counts are correct per branch (`[+3]` at the root, `[+1]` at the mid-level node), and the no-subject fallback truncates long bodies to ~60 characters with a trailing ellipsis.
- Notes:
  - Real fixture data only had one level of replies available, so the deeper-nesting check relied on the synthetic render — consistent with how Stage 2's tree-building was also verified synthetically.

## Stage 4 - Content-pane partial
- Changes:
  - `templates/partials/paned_content_pane.php`: new partial that renders every post in the thread as a content block (subject-or-body-excerpt heading, `$contentMeta`/`$br` for author+date+body, the same agent-authored marker text used elsewhere), all `hidden` except the root post's block by default. Rendering every post up front (rather than fetching on demand) is what lets Stage 5 swap the visible block with no new backend call, per Step 2's "no new API calls" requirement.
  - Reply/permalink links reuse the exact existing href patterns (`/compose/reply?thread_id=...&parent_id=...`, `/posts/{id}`) already used by `post_card.php`.
  - `renderForte()` now also passes `posts` into the page template data.
  - `templates/pages/forte.php`: renders the content pane after the list pane.
- Scope note (deviation from the Step 3 plan's literal wording): the plan described reusing `post_card.php`/`thread_root_card.php` directly. Doing that verbatim would require reusing every context array those partials read (post analysis, Codex handoff, LLM exchanges, viewer like/flag state) — a much larger, viewer-permission-sensitive data-assembly surface than a single stage. Instead this stage reuses the same *fields and helpers* (`$author`/`$timestamp` via `$contentMeta`, `$br`, the `author_label === 'reply-agent'` check, the same link hrefs) in a new, smaller partial, matching Step 2's Core Requirement ("full body, author, and date, reusing the same fields already rendered today") without pulling in the analysis/Codex/reaction-button subsystems. Reaction buttons (like/flag) and Codex/analysis panels are deferred — flagging this rather than silently expanding or silently shrinking scope.
- Verification:
  - `php -l`: no syntax errors.
  - `GET /threads/root-001/forte`: content pane renders both posts' blocks; the root post's block has no `hidden` attribute and the reply's block does, matching the "content pane shows the root post by default" requirement. Body, author link, and timestamp all render correctly via existing helpers; no PHP warnings in the server log.
- Notes:
  - Follow-up (not required by Step 2's success criteria, since reactions/handoff/analysis were never in this feature's explicit scope): if a future iteration wants like/flag buttons or Codex/analysis panels inside Forte, that should be scoped as its own stage/feature rather than folded in here, since it requires importing the signing/identity JS assets onto the Forte page.
