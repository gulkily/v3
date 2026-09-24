# Forte Board Reply Restore — Step 3: Development Plan

## Stage 1
- Goal: Make the compose panel's `return_to` field track the current tag filter and selected thread instead of the static `/forte`.
- Dependencies: none (builds on already-shipped `forte_reply`/`forte_board_reply`)
- Expected changes: `public/assets/paned_board_reader.js` — `return_to`'s value is recomputed (via the same query-string construction already used by `urlForState()`, not a hand-rolled duplicate) whenever selection changes (`selectThread`), selection clears (`resetContentPane`), or the tag filter changes (`selectFolder`), so it always reflects the live `tag`/`selected` state at submit time
- Verification approach: headless-browser test — apply a tag filter, select a thread, read the compose panel's hidden `return_to` input value and confirm it contains both the current tag and the selected thread's id
- Risks or open questions:
  - Reusing `urlForState`'s query-building logic (rather than a second implementation) so the two never drift apart
- Canonical components/API contracts touched: `public/assets/paned_board_reader.js` (extended)

## Stage 2
- Goal: Let the server accept and forward this richer board return path safely.
- Dependencies: Stage 1
- Expected changes: `Application.php::resolveComposeReplyReturnTo(string $requestedReturnTo, string $threadId): string` extended so a `/forte` return target may carry a query string restricted to just `tag` and `selected` (each validated against a narrow, character-restricted pattern, same spirit as the existing ASCII-token checks used elsewhere); anything outside that shape still falls back to the current safe defaults
- Verification approach: `curl -i -X POST /compose/reply` with `return_to=/forte?tag=general&selected=root-001` → redirect Location matches exactly; malformed/foreign query content (e.g. an extra unexpected param, or non-token characters) falls back to plain `/forte` or classic `/threads/{id}`, confirming the guard still holds
- Risks or open questions:
  - Keep the allowlist conservative (`tag`, `selected` only) so this doesn't become a general-purpose open redirect surface
- Canonical components/API contracts touched: `Application.php::resolveComposeReplyReturnTo` (extended)

## Stage 3
- Goal: Restore tag filter and thread selection on page load when the URL carries them.
- Dependencies: Stage 2 (so the query params actually arrive after a reply redirect)
- Expected changes: `paned_board_reader.js`'s startup logic reads `selected` from the URL (tag restoration already works today via `resolveForteBoardTag()`'s existing server-side `tag` handling) and calls the existing `selectThread()` if that id matches a rendered thread; otherwise leaves the default "nothing selected" placeholder state untouched
- Verification approach: headless-browser test — load `/forte?tag=general&selected=root-001` directly and confirm that thread is selected with Reply enabled on load; load with a nonexistent `selected` value and confirm no error, default state holds
- Risks or open questions: none identified
- Canonical components/API contracts touched: `public/assets/paned_board_reader.js` (extended)

## Stage 4
- Goal: Verify the full round trip and confirm no regressions on adjacent flows.
- Dependencies: Stages 1-3
- Expected changes: none expected (integration/regression verification only)
- Verification approach: headless-browser test covering: filter by tag → select thread → open composer (confirm `return_to` reflects both) → submit → confirm redirected page loads with that same tag filter and thread selected, Reply enabled; separately confirm submitting with no filter/no selection still lands cleanly on plain `/forte` (Step 2's stated edge case); separately confirm the single-thread reader's `/threads/{id}/forte` return path is unaffected
- Risks or open questions: none identified
- Canonical components/API contracts touched: none new — integration check across Stages 1-3, plus regression check on the untouched `/threads/{id}/forte` path
