# QDB Numeric Permalinks — Step 1: Solution Assessment

> **Feature plan:** [Step 1](./qdb_numeric_permalinks_step1_solution_assessment.md) · [Step 2](./qdb_numeric_permalinks_step2_feature_description.md) · [Step 3](./qdb_numeric_permalinks_step3_development_plan.md) · [Step 4](./qdb_numeric_permalinks_step4_implementation_summary.md)

## Original Query

For the next piece, I'd like for the link on `/latest` whose caption is
`#3` to go to `/3` and appear formatted the same way, please.

## Problem

On `/latest`, a quote's card shows caption `#3` but links to
`/threads/thread-<timestamp>-qdb-3` — the full internal post ID. There is
no short `/3` URL that resolves to that same quote today.

## Context found

- `templates/partials/quote_card.php` already computes `$displayNumber`
  (`3`) separately from the link `href`, which is hardcoded to
  `/threads/<full post ID>`. Only the `href` needs to change — the
  caption is already correct.
- A near-identical mechanism already shipped: `qdb_classic_urls_checklist.md`'s
  "Numeric-style permalinks" item added a last-resort bare-path handler
  in `Application::handle()` (gated on `SiteConfig::siteName() === 'qdb'`,
  checked only after every real route fails to match) that 302-redirects
  `/<id>` to `/threads/<id>` when `ThreadRepository::byId()` finds an
  exact match. Its own code comment even cites `/311057` as the example —
  written before real sequential numbers existed, so it only ever matched
  if someone typed the *full* internal ID as the path.
- `ThreadRepository::byId()` matches `threads.root_post_id` exactly, so a
  bare numeric path like `/3` does not match anything today — it has to
  be resolved to the full ID first (same numeric-suffix lookup pattern
  used by `docs/plans/qdb_quote_numbering_step1_solution_assessment.md`'s
  already-verified query, just as an equality check instead of `MAX`).
- Scope boundary: the destination page at `/threads/<id>` today renders
  the generic `thread_root_card.php` (not `quote_card.php`), which is a
  separately tracked, still-open gap (`qdb_todo.txt` item 1 — wrong vote
  buttons, no quote-number header on the permalink). This feature does
  not touch that. "Appear formatted the same way" is read here as: the
  destination page's appearance does not change — `/3` reaches the exact
  same, currently-unchanged page that `#3`'s link already reaches today,
  just via a shorter URL.

## Options

### Option A — Extend the existing bare-path classic-permalink handler (Recommended)
In the same last-resort block that already does `/<full-id>` → redirect,
add: if the path is all digits and the direct ID lookup misses, resolve
it as a qdb quote number (reusing the verified `CAST(substr(...))` lookup
pattern) and redirect the same way. Update `quote_card.php`'s anchor
`href` to `/<N>` instead of `/threads/<full-id>` for any quote that has a
display number.
- Pros: reuses the exact already-shipped mechanism and its 302-redirect
  convention; minimal diff; no new route, no schema change; doesn't touch
  the destination page's rendering at all (so no overlap with the
  separately tracked item 1 gap).
- Cons: adds one more conditional to an already-dense catch-all block.

### Option B — Separate dedicated route for `/\d+/?`
Add a new, independent regex route (checked earlier in `handle()`,
outside the last-resort catch-all) specifically for all-digit paths.
- Pros: isolates the new logic from the existing catch-all, lower risk of
  perturbing its other behavior.
- Cons: splits one "classic bare permalink" concept into two near-
  identical blocks with duplicated qdb-profile gating and redirect logic
  — against reusing/extending the existing mechanism.

### Option C — Render the quote directly at `/N` instead of redirecting
Skip the 302; call the same thread-rendering controller method directly
at `/N` and return its HTML, matching qdb.us's own URLs (which were the
page itself, not a redirect).
- Pros: no extra round trip; arguably closer to the original qdb.us feel.
- Cons: departs from the redirect convention the "Numeric-style
  permalinks" item already established and verified, without a stated
  reason to change it; worth doing only if the extra redirect hop is
  actually a problem worth solving.

## Recommendation

**Option A.** It is a small, direct extension of a mechanism that
already shipped and was verified for exactly this shape of URL, changes
one line in `quote_card.php`, and keeps a clean boundary against the
separately tracked permalink-formatting gap in `qdb_todo.txt` item 1.

**Estimated lift:** very small — a couple of hours, one or two Step 3
stages.
