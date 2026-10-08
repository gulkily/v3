# Step 3: Development Plan — Omit Duplicate Preview Line from Thread List Card

## Stage 1
- Goal: Hide the thread list card's preview line only when it exactly duplicates the title
- Dependencies: none
- Expected changes: in `templates/partials/thread_card.php`, compute a boolean (e.g. comparing `trim($thread['body_preview'])` to `trim($subject)`, the already-computed display title) and wrap the existing `<p class="thread-card__preview">` line in that condition; no change to how `$subject` or `$thread['body_preview']` are computed
- Verification approach: manually render the board/tag page for a thread whose subject matches its body's first line (e.g. the "On Accessibility" or "The Rules of ZenMemes.com" threads) and confirm no preview line renders; render an unrelated existing thread (e.g. `root-001`, "Hello world" vs. "First line preview.") and confirm output is byte-identical to before this change
- Risks or open questions: none
- Canonical components/API contracts touched: `templates/partials/thread_card.php` (markup only, no PHP class/service changes)

## Stage 2
- Goal: Add automated test coverage for the new conditional
- Dependencies: Stage 1
- Expected changes: extend `LocalAppSmokeTest` with coverage for: title matches preview → line omitted; title differs from preview → line renders unchanged; no-subject thread (excerpt-derived title) → preview still renders, since the fallback title can't exactly match
- Verification approach: run the new/updated test(s) and the full suite (`php tests/run.php`), confirm no new failures beyond the existing pre-existing/unrelated ones
- Risks or open questions: none
- Canonical components/API contracts touched: test suite only

Reply **Approved Step 3** to create the feature branch and begin Step 4.
