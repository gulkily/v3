# Step 3: Development Plan — Omit Duplicate Title Line from Thread Root Card

## Stage 1
- Goal: In the thread root card, skip rendering the body's first line when it exactly matches the title
- Dependencies: none
- Expected changes: in `templates/partials/thread_root_card.php`, before rendering `<div class="body">`, split `$post['body']` on its first line break into a first-line candidate and the remainder; if the first-line candidate trimmed equals `$title` trimmed (case-sensitive), render only the remainder through the existing `$br` helper; otherwise render the full body through `$br` exactly as today; if the remainder is empty, render an empty body element rather than omitting it
- Verification approach: manually render the linked poem-style thread where subject repeats the first body line and confirm the line appears once, immediately followed by the rest of the poem; render an unrelated existing thread (subject differs from first body line) and confirm output is byte-identical to before this change; render a thread with no subject (title derived from the truncated body-excerpt fallback) and confirm its first body line is still shown (title ends in `...` so it can't exactly match)
- Risks or open questions:
  - Line-break detection must handle a body with no line breaks at all (single-line post) — in that case, if the whole body equals the title, the remainder is empty and should render an empty body element, matching the Step 2 success criterion
  - Comparison happens on raw (un-escaped) text before the existing `$br`/`$e` escaping is applied, consistent with how `$title` itself is already un-escaped until render time
- Canonical components/API contracts touched: `templates/partials/thread_root_card.php` (markup only, no PHP class/service changes)

## Stage 2
- Goal: Add automated test coverage for the new de-duplication behavior
- Dependencies: Stage 1
- Expected changes: new test method(s) (likely in `tests/LocalAppSmokeTest.php`, alongside the existing thread-page assertions, or a small dedicated test file if that's a cleaner fit) covering: title matches first body line → line omitted, rest of body intact; title differs from first body line → body unchanged; no-subject thread (excerpt-derived title) → first body line still shown; body is only the duplicated title → empty body element, no error
- Verification approach: run the new/updated test(s) and the full suite (`php tests/run.php`) and confirm no new failures beyond the existing pre-existing/unrelated ones
- Risks or open questions: none
- Canonical components/API contracts touched: test suite only

Reply **Approved Step 3** to create the feature branch and begin Step 4.
