# Forte Deprecate Single-Thread View — Step 1: Solution Assessment

## Problem
Forte has two views: the single-thread reader (`/threads/{id}/forte`) and the board view (`/forte`). The board view fully subsumes the single-thread one (folder tree + thread list + content pane, all in one page) and is the only one anything actually links to. The single-thread view predates it, has no discoverable entry point anywhere in the app, and was already excluded from the just-shipped `forte_identity_signing` work specifically because of this planned deprecation. Carrying it forward means every future Forte cycle (permalinks, nav bridge, etc.) has to keep deciding whether to also extend a view nobody can reach.

## Scope note
This cycle removes the route, templates, JS, and server-side logic used exclusively by the single-thread view. It does not touch the board view. It also isn't a user-facing migration in the usual sense: since nothing links to `/threads/{id}/forte` today, there's no UI change for a reader to notice — the only "migration" is in the codebase.

## Finding that shapes the options
Traced the full dependency graph before picking an approach:

- **Route:** `Application.php:436`, `preg_match('#^/threads/([^/]+)/forte/?$#'...)` dispatching to `renderForte()` (`Application.php:806`).
- **Template:** `templates/pages/forte.php`, used only by `renderForte()`.
- **Partials used only by `forte.php`** — despite similar naming to the board's partials, usage-grepped (not filename-assumed): `paned_list_pane.php` and `paned_content_pane.php` (which itself nests `paned_compose_panel.php`). By contrast, `paned_folder_tree.php` and `paned_thread_reply_tree.php` live in the same `partials/` directory but are actually board-only/shared — confirmed by grepping every `partials/` include site, not by name.
- **JS:** `public/assets/paned_reader.js` (211 lines), loaded only by `renderForte()`; no `window.*` globals shared with `paned_board_reader.js` (429 lines, separate file, separate load path).
- **CSS:** `public/assets/forte.css` is loaded by both `renderForte()` and `renderForteBoard()` — shared, stays untouched. (Some of its single-thread-specific rules become unused after this cycle; that's a follow-up trim, not something blocking this one.)
- **Dead server branch once the route is gone:** `resolveComposeReplyReturnTo()` (`Application.php:5081-5092`)'s first `preg_match` branch whitelists `/threads/{id}/forte` as a valid `return_to` value. It becomes unreachable once nothing can submit that value anymore — `paned_compose_panel.php`, the only place that ever generates it (`returnTo' => '/threads/' . $rootPostId . '/forte'`), is deleted in this same cycle.
- **No committed automated test references any of the above** — grepped `tests/` for `paned`/`forte`/`renderForte`, zero hits. All Forte verification to date has been manual headless-browser runs recorded in each feature's Step 4 summary, so nothing in the test suite needs updating as a result of this removal.
- **Confirmed zero inbound links:** grepped every template for `forte` outside the `paned_*`/`forte_board.php` files themselves. Classic's only Forte entry point is the Tools page → `/forte` (the board view, `Application.php:1436`) — nothing points at `/threads/{id}/forte`.

## Option A: Remove everything in one stage
Route branch, `forte.php`, the three single-thread-only partials, `paned_reader.js`, and the dead `resolveComposeReplyReturnTo` branch, deleted together as one atomic change.
- Pros: matches `forte_fdp_cycles.md`'s own risk read ("confirmed low-risk... nothing outside Forte links to this route"); no reader ever sees an in-between state; nothing to stage since the pieces are already fully isolated (per the finding above).
- Cons: a single larger diff/stage instead of several small ones; if something unexpected does depend on the route, there's no interim signal before it's gone.

## Option B: Redirect first, remove later
Stage 1 makes `/threads/{id}/forte` 301/302-redirect to `/forte`; a follow-up cycle deletes the now-dead templates/JS/route after the redirect has been live for a while.
- Pros: gives a soft landing for any external bookmark this assessment missed.
- Cons: protects against a risk with no identified source — there's no discoverable path to this route to begin with (unlike, say, a public API endpoint); leaves the dead-code branches (templates, JS, the `return_to` whitelist entry) alive for an extra cycle for no benefit; no precedent for redirect-then-delete elsewhere in this codebase.

## Option C: Leave the route in place; stop extending it
Reclassify as a permanent low-priority maintenance burden instead of deprecating now.
- Pros: zero work now.
- Cons: this is the status quo that prompted the cycle — every future Forte feature keeps having to decide whether to also touch an unreachable, duplicate view; leaves `resolveComposeReplyReturnTo` accepting a `return_to` shape that (after identity-signing's board-only scoping) nothing legitimate produces anymore, a latent inconsistency that only gets easier to trip over later, not now.

## Recommendation
**Option A.** Nothing surfaced during this assessment — zero inbound links, zero test coverage, zero shared JS state, a fully self-contained partial/JS/CSS split — that argues for a softer, staged removal. This matches the cycle's own framing in `forte_fdp_cycles.md`: confirmed low-risk, queued right after identity-signing specifically so no later cycle invests further in a view already slated for removal.
