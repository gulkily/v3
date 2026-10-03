# QDB Quote Numbering — Step 2: Feature Description

> **Feature plan:** [Step 1](./qdb_quote_numbering_step1_solution_assessment.md) · [Step 2](./qdb_quote_numbering_step2_feature_description.md) · [Step 3](./qdb_quote_numbering_step3_development_plan.md) · [Step 4](./qdb_quote_numbering_step4_implementation_summary.md)

## Problem

Quotes submitted through the normal compose flow on the `qdb` site show
their raw internal post ID instead of a sequential quote number, unlike
imported archive quotes and unlike original qdb.us, where every accepted
submission was immediately numbered.

## User stories

- As a visitor submitting a quote to the qdb site, I want my quote
  assigned a number right away so I can reference and share it the way
  qdb.us quotes always were.
- As a visitor browsing the qdb board, I want every quote — imported or
  newly submitted — to show its number consistently, so the list reads
  like a real quote database rather than a mix of numbers and raw IDs.

## Core requirements

- Every quote created through the normal compose flow on the `qdb` site
  profile is assigned a sequential number at submission time.
- The assigned number is strictly greater than any number currently in
  use (imported or live), continuing qdb.us's existing sequence.
- The number appears on that quote's board-list card immediately after
  submission, using the same display mechanism already used for imported
  quotes.
- Numbering applies only to the `qdb` site profile; thread creation on
  zenmemes and chouse is unaffected.
- No database schema change — assignment and display reuse the existing
  ID-suffix convention, write lock, and board-card template.

## Complete feature boundary

- **Normal entry point:** the existing "Submit a quote..." compose-thread
  form on the qdb site — no new UI.
- **Observable end-to-end outcome:** after submitting, the visitor is
  redirected to the board and sees their new quote's card showing
  `#<N>`, exactly like every imported quote.
- **Failure/recovery:** if submission fails validation before the thread
  is actually committed, no number is consumed — a corrected resubmission
  still receives the correct next number, never a skipped one.
- **Release condition:** shippable alone as a single Step 3 stage; it
  depends on no other in-flight feature and leaves no unusable
  intermediate state.

## Risks and mitigations

- **Risk:** the write-time suffix and `quote_card.php`'s display regex
  drift out of sync, silently reverting new quotes to raw-ID display.
  *Impact:* numbering appears broken for all new submissions.
  *Earliest detection:* manual submission test on qdb immediately after
  implementation, confirming the card reads `#<N>`.
  *Mitigation:* reuse the exact existing suffix convention and regex the
  importer already proved, rather than inventing a new format.
- **Risk:** the "highest number + 1" lookup isn't actually inside the
  same write lock as the thread commit, letting two near-simultaneous
  submissions race onto the same number.
  *Impact:* duplicate quote numbers.
  *Earliest detection:* a concurrent-submission test in Step 3/4
  asserting two quick submissions get two distinct, ordered numbers.
  *Mitigation:* perform the lookup and ID mint inside the existing
  `withTimedWriteLock()` closure, not before it.
- **Risk:** the site-profile check is scoped incorrectly, numbering
  zenmemes/chouse threads too, or failing to number real qdb submissions.
  *Impact:* a shared-code regression reaching the other two live
  instances.
  *Earliest detection:* a regression test asserting `createThread()`'s ID
  shape is unchanged for non-qdb profiles, run before/after this change.
  *Mitigation:* gate strictly on `SiteProfileRegistry::active()`, the
  same check already used for per-instance theme selection.

## Shared component inventory

- `templates/partials/quote_card.php` — already renders `#<N>` from the
  `-qdb-<N>` ID suffix; reused unchanged, no new UI surface.
- `LocalWriteService::createThread()` — the single write path for new
  threads across all three site profiles; extended with a conditional
  branch, not forked or duplicated.
- `SiteProfileRegistry::active()` — the existing per-instance identity
  check (already used for theme selection); reused to scope this
  behavior to `qdb` only.
- No new route, endpoint, or template is introduced.

## Simple user flow

1. Visitor on the qdb site opens the "Submit a quote..." compose form.
2. Visitor enters the quote body and submits.
3. The write path assigns the next sequential number and creates the
   thread.
4. Visitor is redirected to the board, where the new quote's card shows
   `#<N>` alongside the body, exactly like every imported quote.

## Success criteria

- A freshly submitted qdb quote displays a number, not its raw internal
  ID.
- That number is exactly one greater than the previous highest number
  (imported or live) at the moment of submission.
- Two submissions in quick succession receive two distinct, correctly
  ordered numbers.
- zenmemes and chouse thread creation remain unchanged (ID format,
  behavior, and existing tests all pass unaffected).
