# QDB Numeric Permalinks — Step 3: Development Plan

> **Feature plan:** [Step 1](./qdb_numeric_permalinks_step1_solution_assessment.md) · [Step 2](./qdb_numeric_permalinks_step2_feature_description.md) · [Step 3](./qdb_numeric_permalinks_step3_development_plan.md) · [Step 4](./qdb_numeric_permalinks_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** clicking a quote's `#N` link on any page that renders
  `quote_card.php` (`/latest`, `/top`, `/search`, `/random`, the board),
  or typing `/N` directly.
- **End-to-end outcome:** the visitor reaches the quote's existing,
  unchanged permalink page via the short `/N` URL.
- **Required recovery:** `/N` for a number with no matching quote still
  404s, same as any other unmatched path today.
- **Deployment/external verification:** none — no new env var, schema,
  or external dependency.
- **Release condition:** complete after Stage 2; no follow-up cycle
  needed for the feature to be usable.

## Key Risks

- The numeric fallback fires somewhere other than strictly last-resort,
  shadowing a real route or the existing exact-ID bare path.
  - Impact: a quote number could resolve to the wrong page, or break an
    existing route.
  - Early warning / validation: Stage 1's redirect/404 verification.
  - Mitigation: add the fallback inside the already-last-resort block,
    after the existing exact-ID check, not as a new earlier route.
- Changing `quote_card.php`'s `href` affects every page that reuses that
  partial, not just `/latest`.
  - Impact: could look like an unintended side effect if some of those
    pages weren't meant to change.
  - Early warning / validation: Stage 2's consumer-page checklist.
  - Mitigation: this is intentional (one shared partial, all consumers
    pick up the change consistently) — verified explicitly, not assumed.
- A quote without an assigned display number would break if its link
  switched to `/N` with no real `N` behind it.
  - Impact: a broken or misleading link for that quote.
  - Early warning / validation: Stage 2's no-suffix test case.
  - Mitigation: only switch a card's `href` to `/N` when a display number
    was actually parsed from the post ID.

## Stage 1
- Goal: `/N` resolves and 302-redirects to the correct quote's full-ID
  permalink, strictly after all real routes and the existing exact-ID
  check have missed.
- Dependencies: none (builds on the already-shipped bare-path handler
  and the already-shipped quote-numbering feature).
- Expected changes:
  - New static method `ThreadRepository::byQdbQuoteNumber(PDO $pdo, int
    $number): ?string` — returns the matching `root_post_id` or `null`,
    via equality on the same numeric-suffix extraction already verified
    in the quote-numbering feature (not a new query shape).
  - In `Application::handle()`'s existing last-resort qdb bare-path
    block: when the direct `ThreadRepository::byId()` lookup misses and
    the path segment is all digits, try `byQdbQuoteNumber()`; redirect to
    `/threads/<resolved-id>` on a hit, exactly as the existing exact-ID
    case already does.
- Verification approach:
  - A seeded quote numbered 42: `GET /42` returns a 302 to
    `/threads/thread-...-qdb-42`.
  - A number with no matching quote: `GET /999999` still 404s.
  - Non-qdb profile: a bare numeric path is unaffected (block is
    qdb-only, unchanged behavior).
- Risks or open questions:
  - Impact / early warning / mitigation: see Key Risks, first bullet.
- Canonical components/API contracts touched: `ThreadRepository` (new
  method), `Application::handle()` (existing bare-path block extended,
  not forked).

## Stage 2
- Goal: every quote card's `#N` caption links to `/N` whenever a real
  display number exists; destination page stays visually unchanged.
- Dependencies: Stage 1 (the `/N` redirect must exist before the link can
  point at it).
- Expected changes: in `templates/partials/quote_card.php`, compute the
  anchor `href` conditionally — `/<displayNumber>` when the `-qdb-<N>`
  suffix was actually matched, otherwise the existing `/threads/<full-id>`
  form unchanged.
- Verification approach:
  - Rendered anchor `href` is `/42` (not `/threads/...`) for a numbered
    quote on `/latest`.
  - Same check repeated on `/top`, `/search`, `/random`, and the board —
    every consumer of `quote_card.php` picks up the change.
  - A quote with no `-qdb-` suffix keeps its old full-ID `href`
    unchanged.
  - Click-through smoke test: following the new `/N` `href` lands on the
    same, unchanged permalink page content as before this feature.
- Risks or open questions:
  - Impact / early warning / mitigation: see Key Risks, second and third
    bullets.
- Canonical components/API contracts touched:
  `templates/partials/quote_card.php` only.
