# Forte Deprecate Single-Thread View — Step 3: Development Plan

## Stage 1
- Goal: Make `/threads/{id}/forte` stop rendering the old reader — the primary user-observable change, done first and in isolation.
- Dependencies: none (Step 2 approved)
- Expected changes: remove the `preg_match('#^/threads/([^/]+)/forte/?$#'...)` route branch and the `renderForte()` method from `Application.php`.
- Verification approach: `php -l` on `Application.php`; `curl` `/threads/{id}/forte` for a real thread ID and confirm it now returns 404, same shape as a request to a nonexistent route; confirm `/forte` (board view) and `/threads/{id}` (classic) both still render correctly, unaffected.
- Risks or open questions: none identified — Step 1 confirmed no other route or code path calls `renderForte()`.
- Canonical components/API contracts touched: `Application.php` (reduced) — no shared component affected.

## Stage 2
- Goal: Delete the now-orphaned single-thread templates, partials, and JS.
- Dependencies: Stage 1 (nothing renders them anymore, safe to delete without an intermediate broken state).
- Expected changes: delete `templates/pages/forte.php`, `templates/partials/paned_list_pane.php`, `templates/partials/paned_content_pane.php`, `templates/partials/paned_compose_panel.php`, and `public/assets/paned_reader.js`.
- Verification approach: grep the full codebase (`src/`, `templates/`, `public/`) for each deleted filename/basename and confirm zero remaining references; `php -l` across `templates/`; confirm `/forte` (board view) still renders correctly with no missing-asset console errors (it never loaded any of these files).
- Risks or open questions: none identified — Step 1's usage grep confirmed these four files have no board-view or classic callers.
- Canonical components/API contracts touched: none — these files were single-thread-exclusive per Step 1's finding.

## Stage 3
- Goal: Remove the now-dead `resolveComposeReplyReturnTo()` branch that whitelisted `/threads/{id}/forte` as a valid `return_to`.
- Dependencies: Stage 2 (the only template that ever generated that `return_to` value, `paned_compose_panel.php`, is gone).
- Expected changes: remove the `preg_match('#^/threads/' . preg_quote($threadId, '#') . '/forte$#'...)` branch (`Application.php:5083-5085`) from `resolveComposeReplyReturnTo()`, leaving the `/forte` (board) branch and the default `/threads/{id}` fallback unchanged.
- Verification approach: `php -l`; unit-level check (direct call or via a reply submission) that a `return_to` of `/forte(...)` still resolves correctly and that a `return_to` of the old single-thread shape now falls through to the default `/threads/{id}` fallback instead of being honored; confirm classic's and the board's own reply flows are unaffected.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `resolveComposeReplyReturnTo()` (reduced).

## Stage 4
- Goal: Full regression check across the whole removal.
- Dependencies: Stages 1-3
- Expected changes: none (verification only)
- Verification approach: full-codebase grep for `renderForte(`, `paned_reader.js`, `paned_list_pane`, `paned_content_pane`, `paned_compose_panel`, and the `/threads/.../forte` route pattern — confirm zero hits outside this feature's planning docs and git history; `curl` `/threads/{id}/forte` again to reconfirm 404; full regression pass on the board view (`/forte`) — folder tree, thread list, sort, tag filtering, anonymous reply, and signed reply (`forte_identity_signing`) all still work exactly as before; confirm classic's thread page (`/threads/{id}`) and its own compose/reply flow are unaffected.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: none new — integration check across Stages 1-3.
