# Forte Step 4 Implementation Summary

## Stage 7 - Post-delivery revision: full isolation and mockup fidelity
- Changes (per user feedback after initial delivery, reflected in the updated Step 2 doc):
  - Removed the "Open in Forte view" link from `templates/pages/thread.php` and the "Back to standard view" link from Forte's toolbar — the two pages are now fully isolated in both directions; Forte is reachable only via its direct URL (`/threads/{id}/forte`). This supersedes the "Final Verification" note below about link-based reachability.
  - Forte no longer renders through the shared `layout.php` (site nav bar, theme selector, global status bar). Added `TemplateRenderer::renderStandalonePage()` and `templates/standalone_layout.php` — a minimal doctype/head/body shell with just the page's own CSS/JS, no site chrome — and switched `renderForte()` to use it.
  - `.paned-window` now sizes to `min(94vw, 1600px)` with a small margin, centered on a fixed desktop-colored backdrop (`body.paned-reader-body` overriding the `--body-background` token), instead of being constrained to the standard page's 760px content column.
  - Toolbar rebuilt with icon+label buttons (New, Reply, Prev, Next, Refresh) matching the reference mockup; New/Reply/Refresh are inert (`disabled`) since Forte has no compose/refresh functionality, Prev/Next are wired in `paned_reader.js` to step the selection through the currently visible list rows.
  - Content pane restyled with a gray meta bar (`.paned-content-head`) showing subject and From/Date above the post body, matching the mockup; the Reply/Permalink action row was removed entirely (Forte is read-only).
  - Added a status bar (`.paned-statusbar`) showing post count and a static "Forte reader" label.
  - Added a small SVG icon to the title bar, matching the mockup.
- Verification:
  - `php -l` / `node --check` on all touched files; CSS brace-balance check: clean.
  - Screenshotted Forte at 1280px and 420px with headless Chromium: chrome renders as intended at both widths, backdrop margin visible, no nav bar/theme selector present.
  - Screenshotted the standard thread page: no Forte link present anywhere, page otherwise unchanged.
  - Re-ran the Puppeteer interaction script: row selection and collapse/expand still work correctly with zero console errors after the markup rewrite.
  - Confirmed via `curl` that the standard thread page's HTML contains zero occurrences of "forte"/"Forte".
- Notes:
  - The main board/index page has no Forte view — Forte was scoped to single-thread reading only. Flagged to the user as a question rather than assumed; a board-level Forte view (listing threads, not replies) would be a separate follow-up feature if wanted.

## Final Verification (Step 4 After)
- Ran the full automated suite (`./v3 test`) on `feature/forte`: 404 passing, 4 failing.
- Ran the same suite on `main` (pre-Forte) for comparison: identical 404 passing / same 4 failing, with the same failure messages (a missing/broken `profile.php` template lookup, a stale `profiles` table in a test's SQLite fixture, and a null-argument bug in an unrelated `testSqliteViewerIncludesSchemaExplorerContract` test). Confirms all 4 are pre-existing environment issues, not regressions introduced by this feature.
- Feature is reachable through normal UI navigation, not just direct URLs: the standard thread page links to Forte ("Open in Forte view") and Forte links back ("Back to standard view"), both confirmed via screenshots in Stage 6.
- No new roles, migrations, or schema changes were introduced at any stage (per Step 2's explicit non-goal), so there is nothing pending there.
- Commit count on this branch is 7 (1 planning-doc commit + 6 stage commits), matching FDP's `1 + stage count` minimum; each stage commit includes the corresponding update to this summary in the same commit.

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

## Stage 5 - Client-side pane sync
- Changes:
  - `public/assets/paned_reader.js`: new script, delegated click handling on the list pane. Clicking a row selects it (shows its `paned-content-post` block, hides the rest, marks the row `paned-list-row--selected`); clicking a row's disclosure toggle collapses/expands its descendant rows (by walking forward through the flat row list until a row at the same or shallower depth is reached) and flips the toggle glyph and the `[+N]` count marker's visibility.
  - `renderForte()` now passes `['/assets/paned_reader.js']` as the page's `scriptPaths`, following the same convention as `renderThread()`.
- Verification:
  - `node --check public/assets/paned_reader.js`: no syntax errors.
  - Confirmed the fingerprinted script URL the layout emits (`/assets/paned_reader.{hash}.js`) resolves and serves with `Content-Type: application/javascript` via `FrontController::resolveFingerprintedAssetPath()`, with no physical fingerprinted file needing to exist on disk (it resolves back to the source file dynamically) — same mechanism every other page's scripts already rely on.
  - Real interactive check with headless Chromium (via a throwaway Puppeteer script, not committed) against the live local server: loaded `/threads/root-001/forte`, clicked the reply row and confirmed the content pane swapped (root's block became `hidden`, reply's became visible) and the row gained the selected class; then clicked the root row's disclosure toggle and confirmed the reply row became `hidden`, the toggle glyph flipped to "▸", and the `[+N]` count marker became visible. Zero console/page errors throughout.
- Notes:
  - Browser back/forward and reload were not separately exercised (this stage has no URL/history state to restore — selection is pure in-memory DOM state, so reload simply resets to the root post, which is the intended default), narrowing the plan's original verification note about that risk to a non-issue for this implementation.

## Stage 6 - Layout integration and polish
- Changes:
  - `templates/pages/forte.php`: wrapped the list/content panes in a `.paned-window` chrome shell — title bar (thread title + decorative window controls), static menu bar, and a toolbar holding the "Back to standard view" link (moved here from the plain link used in Stages 1-4).
  - `public/assets/site.css`: appended a scoped block of rules (`.paned-window` and descendants) implementing the Forte-Agent-styled chrome — beveled borders, gradient title bar, columned list header, selected-row highlight, monospace post body — using a fixed palette local to `.paned-window` rather than the site's active theme tokens, since the goal is to evoke a classic newsreader regardless of theme (documented as a deliberate choice via a CSS comment). No existing selectors were changed; the standard thread page's styling is untouched.
  - The page intentionally does not override `.shell`'s existing 760px column width, since "fills the main content area at the same footprint as the standard view" (confirmed with the user earlier in Step 2) just means it should sit in the same content column, not break out of it.
- Verification:
  - Brace-balance check on `site.css` (533 → 534 open/close pairs, matching the one new rule block added) and `php -l` on `forte.php`: both clean.
  - Screenshotted the Forte page with headless Chromium at two widths (1280px and 420px): chrome renders as intended (title bar, menu bar, toolbar, columned list, bordered content pane) at both; at 420px the columns compress without any horizontal overflow.
  - Screenshotted the standard thread page at 1280px: pixel-identical to its pre-Stage-6 appearance (still shows the Stage 1 "Open in Forte view" link at top), confirming the new CSS is fully scoped to `.paned-window` and doesn't leak.
  - Re-ran the Stage 5 Puppeteer interaction script against the Stage 6 markup: row selection and collapse/expand both still work correctly with zero console errors, confirming the new wrapper markup didn't break the existing selectors the script depends on.
- Notes:
  - At the 420px width the Subject column gets quite narrow (long titles truncate hard against the fixed 9rem From/Date columns) — a real rough edge, but not a page-breaking one, and narrowing/hiding secondary columns responsively would be a reasonable follow-up rather than something this stage needs to solve.
  - The deep-nesting indentation cap flagged as an open question in Stage 3 was not specifically revisited; the fixed-width chrome accommodates the depths seen in testing without needing one yet.
