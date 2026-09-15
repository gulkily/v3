# Forte Identity Signing — Step 3: Development Plan

## Stage 1
- Goal: Make the board view's compose panel eligible for signing — lazy-loaded, matching classic's board page.
- Dependencies: none (Step 2 approved)
- Expected changes: `Application.php::renderForteBoard()` adds `/assets/lazy_compose_signing.js` to its page script list; `partials/paned_board_compose_panel.php`'s outer wrapper gets a `data-compose-root` attribute (confirmed not requiring any other new attributes — `browser_signing.js` doesn't reference `data-unicode-authored-text`/`data-emoji-authored-text`, those belong to an unrelated script)
- Verification approach: load `/forte`, confirm `lazy_compose_signing.js` is present in the page; focus the reply body field and confirm (via browser network/console inspection) that `openpgp_loader.js` + `browser_signing.js` lazy-load only at that point, not on initial page load
- Risks or open questions: none identified
- Canonical components/API contracts touched: `lazy_compose_signing.js` (reused unchanged), `renderForteBoard()` / `paned_board_compose_panel.php` (extended)

## Stage 2
- Goal: Give the reader visible feedback (progress/errors/status) during identity preparation and signed submission, instead of a silent experience.
- Dependencies: Stage 1
- Expected changes: `paned_board_compose_panel.php` gains a `data-role="compose-identity-status"` element (same hook classic's compose pages already use, `browser_signing.js` targets it automatically via existing null-guarded `querySelector`); `forte.css` gets a small rule so it reads consistently with the panel's existing secondary-text styling
- Verification approach: headless-browser test that focuses the compose form (triggering the lazy load) and drives the identity-preparation flow, confirming status text appears in the panel; screenshot check that it's styled consistently with the rest of the Forte panel, not classic's look
- Risks or open questions: none identified
- Canonical components/API contracts touched: `browser_signing.js` (reused, no changes needed for this stage), `paned_board_compose_panel.php` / `forte.css` (extended)

## Stage 3
- Goal: Make signed reply submissions respect `return_to` instead of hardcoding a redirect to classic's `/threads/{id}`.
- Dependencies: Stage 1 (signing must be active to reach this code path)
- Expected changes: the function building the post-signed-reply navigation target (`canonicalReplyUrl`/`navigateToCanonicalReply`, called from `submitSignedReplyAndNavigate`) reads the compose form's `return_to` field; when non-empty, builds the target from it — joining the existing `created_post_id`/`__v`/`#post-{id}` suffix with `&` instead of `?` when `return_to` already carries its own query string, mirroring the exact fix already applied server-side in `resolveComposeReplyReturnTo`'s caller — otherwise falls back to today's hardcoded classic path unchanged
- Verification approach: attempt a real end-to-end signed submission via headless browser (create/prepare an identity through the existing client-side flow, then submit a reply) and confirm the resulting URL reflects Forte's return path; separately confirm classic's own thread/board reply forms (which never render a `return_to` field) are byte-for-byte unaffected, since they fall through to the unchanged default
- Risks or open questions:
  - Driving a full real signed submission (key generation + signing) through a headless browser may prove slow or flaky; if so, fall back to verifying the URL-building logic directly (e.g., via browser console against the loaded script) rather than a full live signed-post flow
- Canonical components/API contracts touched: `browser_signing.js` (extended, not forked)

## Stage 4
- Goal: Full regression check across anonymous Forte replies, signed Forte replies (as far as testable), and classic's unaffected behavior.
- Dependencies: Stages 1-3
- Expected changes: none (verification only)
- Verification approach: re-run the existing anonymous board-reply round trip (filter + select + reply + redirect + restore + highlight, from `forte_board_reply_restore`/`forte_board_reply_tree`) to confirm zero regression; if Stage 3's live signed-submission test succeeded, confirm the same round trip holds for a signed reply; load classic's thread page and confirm its own signed-reply flow still lands on `/threads/{id}` exactly as before
- Risks or open questions: none identified beyond Stage 3's testability question
- Canonical components/API contracts touched: none new — integration check across Stages 1-3
