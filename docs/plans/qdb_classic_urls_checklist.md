# QDB Classic URLs Checklist

Working checklist, not a committed FDP artifact. One item at a time, commit
+ update this file after each, same pattern as `qdb_theme_fidelity_checklist.md`.
All of this is scoped to the `qdb` site profile only (`SiteConfig::siteName()
=== 'qdb'`) — no routing changes for zenmemes/chouse.

Both forms of each classic URL should resolve the same way: the qdb.us-style
bare path (`/latest`) and the bash.org-style query flag on `/` (`/?latest`).

Decided out of scope for this pass: **Queue** (would need a real pre-publish
moderation concept this app doesn't have — skipped per your call).

## Checklist

- [x] **Latest** — `/latest`, `/?latest` → same as `?view=all&sort=newest`,
  rendered directly (no redirect).
  _Done: new early block in `Application::handle()`, gated on
  `SiteConfig::siteName() === 'qdb'`, before the generic `/` handler._
- [x] **Top** — `/top`, `/?top` → same as `?view=all&sort=top`, rendered
  directly.
  _Done alongside Latest, same block._
- [x] **1337 (leetness)** — `/leetness`, `/?leetness` → sort by
  `abs(1337 - score_total)` ascending (closest to exactly 1337 first), per
  your formula. Needs a new sort case in `BoardPageController`; added to
  `BoardViewOptions::normalizeSort()`'s allowlist but **not** to
  `sortOptions()`'s visible pills, so other profiles' sort UI is untouched.
  _Done: `compareLeetness()` added to `BoardPageController`, 'leetness'
  added to the shared `normalizeSort()` allowlist (so it isn't silently
  coerced to 'newest'), both URL forms wired. Verified ordering for real
  with distinctive scores (2000 and 100): `abs(1337-2000)=663 <
  abs(1337-100)=1237`, confirmed the score-2000 thread ranked first in
  both `/leetness` and `/?leetness`. Scores reset afterward._
- [x] **Add Quote** — `/add`, `/?add` → same as `/compose/thread`, rendered
  directly.
  _Done: calls `composeAndAccountKeyController()->composeThread($query)`
  directly, same as the real `/compose/thread` route. Verified both forms
  render the compose form._
- [x] **Random** — `/random`, `/?random` → picks a random thread from this
  instance's quote list and redirects (302) to `/threads/<id>`.
  _Done: reuses `ThreadRepository::fetchThreads()` (same filtering as the
  board itself) + `array_rand()`, 302 via the existing `sendRedirect()`
  helper. Verified: response code 302, redirect target a real thread id._
- [x] **Search** — `/search`, `/?search` → new feature, not just a route.
  Simple `?q=` full-text-ish match over quote bodies, rendered with the
  existing `quote_card.php` partial for result consistency. No `q` param
  shows an empty search form.
  _Done, using the term param name the original actually used
  (`?search=<term>`, found in the archived nav, e.g. `?search=Ninety`)
  rather than inventing a `?q=` param: `/search` and `/?search` both
  resolve to a new `BoardPageController::search()` + `qdb_search.php`
  template, reusing `quote_card.php` per result. Bare `?search`/`/search`
  (no value) shows an empty form; `search=<term>` does a `stripos` match
  over `root_post_body`, reusing `ThreadRepository::fetchThreads()` rather
  than a new SQL query. Refactored the viewer-upvote/downvote/flag lookup
  out of `board()` into a shared private method so `search()` reuses it
  too. Verified all 4 URL combinations, including an actual term match._
- [x] **Numeric-style permalinks** — `/?<id>` (query flag) and bare `/<id>`
  (path) → resolve to the same page as `/threads/<id>`, for any real thread
  id in this instance (ours aren't numeric, but the mechanism works the
  same). Bare `/<id>` must be a last-resort fallback (checked only after
  every other real route fails to match) to avoid colliding with anything.
  _Done: both forms 302-redirect to the canonical `/threads/<id>` (using
  `ThreadRepository::byId()` as the lightweight existence check) rather
  than duplicating the thread-rendering logic at a second URL. The bare
  `/<id>` form is the literal last check in `handle()`, right before the
  final `notFound()`, and only active for the qdb profile. Verified: both
  forms redirect correctly for real thread ids, `/does-not-exist` still
  404s, an unrecognized bare query flag still falls through to the normal
  board render, and the zenmemes profile is completely unaffected (still
  404s on a bare path that would redirect under qdb)._

## Done

All items checked. Everything here is additive and scoped to
`SiteConfig::siteName() === 'qdb'` — no routing/behavior change for
zenmemes/chouse anywhere in this checklist.

## Post-checklist follow-up

- **Nav links:** this checklist made the classic routes *work*, but nothing
  pointed at them — the top nav still showed the generic Board/About/Users/
  Tools/Account for the qdb profile too. Replaced it (qdb profile only)
  with Latest/Top/Random/Add Quote/Search/Account in
  `TemplateRenderer::navItems()`.
- **Bug found while verifying:** Latest, Top, and Random all shared the
  same `board` nav section, so all three lit up `is-active` together on
  any board-rendered page. Fixed by giving `BoardPageController::board()`
  an explicit `$activeSection` parameter and distinct section values per
  nav item, so exactly one highlights at a time. Verified on `/latest`,
  `/top`, `/add`, `/search`.
- **Reload-loop bug, reported by the operator:** `/latest`, `/top`,
  `/leetness`, `/add`, `/random`, `/search` were never added to
  `Application::isApplicationRoute()`'s allowlist. That allowlist gates
  `shouldResumeViewerSession()` — without it, the PHP session never
  resumes on these pages even when the browser already has one, so the
  viewer's identity never resolves there. Combined with a pre-existing,
  unrelated client-side quirk (`private_site_auth.js`'s "already
  authenticated" short-circuit reads a `data-authenticated-identity-id`
  body attribute that `layout.php` always renders empty, so it never
  short-circuits), every load on one of these pages re-ran the browser's
  auto re-authentication flow, which — once the identity resolved as
  approved — called `location.replace()` back to the *same* page,
  reloading it, re-running the same sequence, forever.
  Fixed by adding the six paths to `isApplicationRoute()`'s allowlist, so
  the session resumes there like it does on every other real route.
  Verified with a simulated two-request session (set
  `authenticated_identity_id`, then request each affected path reusing
  that session): the `data-private-site-auth-state` marker (the thing
  that triggers the client-side auto-re-auth flow) is now absent on all
  six, matching `/threads/<id>` and every other pre-existing route. A
  fresh visitor with no session still gets a normal 200 render.
  The `data-authenticated-identity-id=""` client-side quirk itself is
  pre-existing (not touched by this feature, not fixed here) — it just
  happened to be harmless before because no pre-existing route was ever
  missing from the allowlist in the way these six were.
