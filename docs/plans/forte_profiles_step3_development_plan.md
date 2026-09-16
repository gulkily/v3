# Forte Profiles — Step 3: Development Plan

## Stage 1
- Goal: Add a Forte-target author-link variant without touching classic's rendering.
- Dependencies: none (Step 2 approved)
- Expected changes: `TemplateRenderer::renderAuthorHtml()` gains an optional `bool $forteTarget = false` param (default preserves current classic behavior byte-for-byte); a new `$forteAuthor` closure exposed alongside the existing `$author` closure in `renderFile()`, calling it with `$forteTarget = true` to build `/forte/profiles/{slug}` / `/forte/user/{username}` instead of classic's paths.
- Verification approach: `php -l`; confirm classic pages using `$author` are byte-for-byte unchanged (no template calls `$forteAuthor` yet, so purely additive).
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `TemplateRenderer::renderAuthorHtml()` (extended, default-safe).

## Stage 2
- Goal: Stand up the full Forte-styled profile page.
- Dependencies: none (independent of Stage 1; nothing links to it yet)
- Expected changes: new routes `/forte/profiles/{slug}` and `/forte/user/{username}` (mirroring classic's dual scheme) reusing `fetchProfileBySlug()`/`fetchProfilesByUsernameToken()`; new `profile.php`-equivalent Forte template (paned-styled single window, not the full board chrome) showing username, approval status/by-whom, thread/post counts, public key details — no "Approve user" form; includes a link back to `/forte`.
- Verification approach: `curl` the new routes directly for an approved and an unapproved profile, confirm the right fields render and no approve-action markup appears; confirm classic's own `/profiles/{slug}`/`/user/{username}` are unaffected.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `fetchProfileBySlug()` / `fetchProfilesByUsernameToken()` (reused unchanged), new routes + template.

## Stage 3
- Goal: Point Forte's own author names at the new profile pages.
- Dependencies: Stages 1-2 (helper and destination must both exist first, so links are never briefly broken)
- Expected changes: `paned_board_content_pane.php` (root post) and `paned_thread_reply_tree.php` (replies) switch from `$author(...)` to `$forteAuthor(...)`.
- Verification approach: `curl` the board, confirm author links now point at `/forte/profiles/...`/`/forte/user/...`; click through in a headless browser, confirm it lands on a working Forte profile page; confirm classic's thread/board pages (still using `$author`) are unaffected.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `paned_board_content_pane.php`, `paned_thread_reply_tree.php` (extended).

## Stage 4
- Goal: Add the quick-glance summary dialog on author-name click.
- Dependencies: Stage 3 (needs real Forte-target links to intercept and to fall back to)
- Expected changes: new small dialog (titlebar + body, mirroring the `forte_compose_thread` New Thread `<dialog>` pattern) added to the board page; JS click-intercepts a Forte author link, fetches `/api/get_profile?profile_slug=...` (reused unchanged), renders username/approval/counts plus a "View full profile" link to the same `href` the anchor already had; without JS, the real `href` still navigates straight to the full page.
- Verification approach: headless-browser test — click an author name, confirm the dialog opens with correct fetched data instead of navigating away; confirm the "View full profile" link matches the anchor's original `href`; confirm keyboard/middle-click still reaches the full page directly (JS never prevents default for those).
- Risks or open questions:
  - `/api/get_profile` returns plain `Key: Value` text, not JSON — confirm the existing parsing approach already used elsewhere (`thread_reactions.js`'s `parseResponseValue`) is reusable here rather than writing a second parser.
- Canonical components/API contracts touched: `/api/get_profile` (reused unchanged), `paned_board_reader.js` (extended).

## Stage 5
- Goal: Add the Forte-styled user directory and a way to reach it from the board.
- Dependencies: Stage 2 (links to the same full profile pages)
- Expected changes: new route `/forte/users/` reusing `fetchApprovedUserDirectoryUsers()`; new paned-styled list template linking each entry straight to its full profile page (no dialog detour); a small nav entry point from the board (e.g. a link near the folder tree or statusbar) to reach it.
- Verification approach: `curl` `/forte/users/`, confirm approved users list with correct counts and working links; confirm the board's new nav entry point reaches it; confirm classic's `/users/` is unaffected.
- Risks or open questions: none identified.
- Canonical components/API contracts touched: `fetchApprovedUserDirectoryUsers()` (reused unchanged), new route + template, `forte_board.php` (small nav addition).

## Stage 6
- Goal: Full regression check.
- Dependencies: Stages 1-5
- Expected changes: none (verification only)
- Verification approach: confirm classic's `/profiles/{slug}`, `/user/{username}`, and `/users/` are byte-for-byte unaffected; confirm no "Approve user" affordance exists anywhere in the new Forte pages; re-verify the existing board regression suite (reactions, permalinks, New Thread, Reply, thread-selection URL sync) is unaffected; confirm summary-dialog and full-profile-page data match classic's own numbers for the same profile (no drift).
- Risks or open questions: none identified.
- Canonical components/API contracts touched: none new — integration check across Stages 1-5.
