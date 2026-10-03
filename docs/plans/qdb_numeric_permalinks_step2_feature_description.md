# QDB Numeric Permalinks — Step 2: Feature Description

> **Feature plan:** [Step 1](./qdb_numeric_permalinks_step1_solution_assessment.md) · [Step 2](./qdb_numeric_permalinks_step2_feature_description.md) · [Step 3](./qdb_numeric_permalinks_step3_development_plan.md) · [Step 4](./qdb_numeric_permalinks_step4_implementation_summary.md)

## Problem

On the qdb site, a quote's card caption `#3` links to its full internal
post ID (`/threads/thread-<timestamp>-qdb-3`) instead of a short `/3`,
and no bare `/3` URL exists that reaches that same quote.

## User stories

- As a visitor browsing a qdb quote list, I want to click a quote's
  `#3` and land on a short `/3` URL, so I can share or bookmark it the
  way classic qdb.us quotes worked.
- As a visitor who already knows a quote's number, I want to type `/3`
  directly in the address bar and reach that quote, without needing to
  know its internal ID.

## Core requirements

- Visiting `/N` for a quote number that exists on the qdb site reaches
  the same page that quote's `#N` link already reaches today.
- Every quote card's `#N` link (`quote_card.php`, shared by `/latest`,
  `/top`, `/search`, `/random`, and the board) points its `href` at `/N`
  instead of the full internal post ID.
- `/N` for a number with no matching quote 404s, same as any other
  unmatched path today.
- The destination page's rendering is unchanged — same page, shorter URL
  only.
- Non-qdb site profiles, and any bare path that isn't purely numeric,
  keep their current behavior unchanged.

## Completion boundary

- **Normal entry:** clicking a quote's `#N` link on any page that renders
  `quote_card.php`, or typing `/N` directly.
- **End-to-end outcome:** the visitor lands on the quote's existing
  permalink page, reached via the short `/N` URL.
- **Recovery:** `/N` with no matching quote falls through to the existing
  bare-path handling and 404s, exactly as an unmatched path does today.
- **Release condition:** shippable alone; does not depend on the
  separately scoped "permalink quote-card parity" slice discussed and
  deferred during Step 1.

## Risks

- **Risk:** the numeric lookup fires somewhere other than strictly
  last-resort, shadowing a real route or an exact-ID bare path.
  *Impact:* a quote number could resolve to the wrong page, or break an
  existing route.
  *Earliest validation:* a Step 3 test confirming the numeric fallback
  only runs after every real route and the exact-ID lookup have missed.
  *Mitigation:* add the check inside the existing last-resort bare-path
  block, not before it.
- **Risk:** changing `quote_card.php`'s `href` affects every page that
  reuses that partial (`/latest`, `/top`, `/search`, `/random`, the
  board), not just `/latest`.
  *Impact:* could look like an unintended side effect if some of those
  pages weren't meant to change.
  *Earliest validation:* Step 4 smoke-checks each consumer page.
  *Mitigation:* this is intentional — the card is one shared partial, so
  all of its consumers are meant to pick up the shorter link
  consistently; called out here so it isn't mistaken for scope creep.
- **Risk:** a quote without an assigned display number (if any exist
  outside the numbering feature's coverage) would break if its link
  switched to `/N` with no real `N`.
  *Impact:* a broken or misleading link for that quote.
  *Earliest validation:* an explicit Step 3/4 test case for a thread with
  no `-qdb-` suffix.
  *Mitigation:* only switch a card's `href` to `/N` when a display number
  was actually parsed; otherwise keep today's full-ID `href` unchanged.

## Shared component inventory

- `templates/partials/quote_card.php` — reused; one conditional `href`
  change, not forked.
- `Application::handle()`'s existing last-resort bare-path block (the
  "Numeric-style permalinks" item already shipped in
  `qdb_classic_urls_checklist.md`) — extended with a numeric-quote-number
  fallback, not duplicated into a new route.
- `ThreadRepository` — extended with a new lookup-by-quote-number method
  alongside its existing `byId()`, reusing the same repository.
- No new template, route pattern, or schema change.

## Simple user flow

1. Visitor views quote cards on `/latest` (or `/top`, `/search`,
   `/random`, the board).
2. Visitor clicks a quote's `#3` link, now pointing at `/3`.
3. The server resolves `3` to the quote's full ID and 302-redirects to
   `/threads/<full-id>`.
4. Visitor sees the unchanged permalink page, reached via `/3`.
5. Typing `/3` directly produces the same result.

## Success criteria

- Clicking `#N` from any quote-card-rendering page on qdb reaches the
  quote's existing permalink page via `/N`.
- Typing `/N` directly for an existing quote reaches the same page.
- `/N` for a non-existent number 404s, same as before this feature.
- The permalink page itself is visually unchanged.
- Non-qdb site profiles are unaffected.
