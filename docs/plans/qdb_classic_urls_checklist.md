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
- [ ] **Add Quote** — `/add`, `/?add` → same as `/compose/thread`, rendered
  directly.
- [ ] **Random** — `/random`, `/?random` → picks a random thread from this
  instance's quote list and redirects (302) to `/threads/<id>`.
- [ ] **Search** — `/search`, `/?search` → new feature, not just a route.
  Simple `?q=` full-text-ish match over quote bodies, rendered with the
  existing `quote_card.php` partial for result consistency. No `q` param
  shows an empty search form.
- [ ] **Numeric-style permalinks** — `/?<id>` (query flag) and bare `/<id>`
  (path) → resolve to the same page as `/threads/<id>`, for any real thread
  id in this instance (ours aren't numeric, but the mechanism works the
  same). Bare `/<id>` must be a last-resort fallback (checked only after
  every other real route fails to match) to avoid colliding with anything.
