# Forte Identity Signing — Step 2: Feature Description

## Problem
Forte's board-view reply composer never loads the browser-key signing scripts, so every reply posts as anonymous "guest" even for readers with an existing signed identity, and the composer never shows the identity/status feedback classic already provides.

## User Stories
- As a Forte board-view reader with a signed identity, I want my reply to carry that signed authorship, so it's attributed the same way it would be from classic.
- As a Forte board-view reader without a signed identity yet, I want the same identity-preparation flow classic's board page already offers, so I'm not blocked from signing up just because I'm using Forte.
- As a Forte board-view reader who successfully posts a signed reply, I want to stay on the board — same tag filter, same thread selected, new reply highlighted — exactly like an anonymous reply does today, so signing in doesn't change where I land.

## Core Requirements
- The board view's compose panel gets `data-compose-root` and loads `lazy_compose_signing.js`, matching classic's own board-page loading strategy (per approved Step 1, Option A).
- Signed reply submissions land back in Forte's board view — same tag/selection/highlight restoration anonymous replies already get — instead of `browser_signing.js`'s current hardcoded classic-thread redirect, via the approved `return_to`-aware patch to that shared script.
- Anonymous submission behavior is completely unchanged (still the existing `/compose/reply` form POST and its existing restore mechanism).
- No changes to the single-thread Forte reader (being deprecated in a separate cycle).
- No database/schema changes — the signing capability already exists; this only wires it into an existing surface.

## Shared Component Inventory
- `lazy_compose_signing.js` / `browser_signing.js` / `openpgp_loader.js` — canonical signing implementation, already used by classic's board and thread pages. **Reused as-is** for loading; `browser_signing.js`'s post-signed-reply navigation gets a small, surgical `return_to`-aware extension (not a fork).
- `partials/reply_form.php` — already fully compatible (`data-compose-form`, `author_identity_id` hidden field present). **Reused unchanged.**
- `partials/paned_board_compose_panel.php` — **extended** with a `data-compose-root` wrapper attribute.
- `POST /api/create_reply` — canonical signed-submission endpoint, already used by classic. **Reused unchanged**; only its caller's post-success navigation changes.
- `POST /compose/reply` + `resolveComposeReplyReturnTo()` — canonical anonymous-submission path. **Untouched** — still exactly what anonymous Forte replies already use.

## Simple User Flow
1. Reader opens the board view's reply composer.
2. If they have (or create) a signed identity, the reply is signed the same way it would be from classic.
3. On success, the reader stays on `/forte` with their tag filter, selected thread, and the new reply highlighted — the same landing experience an anonymous reply already gets today.

## Success Criteria
- A reader with an existing signed identity who replies from Forte's board view sees their reply attributed to that identity, not anonymous "guest."
- A reader without a signed identity can complete the existing sign-up/key-preparation flow from within Forte's board view, unchanged from classic's behavior.
- After a successful signed reply, the reader lands back on the board view with tag filter and thread selection preserved and the new reply highlighted — never redirected to classic.
- Anonymous replies from Forte's board view are completely unaffected.
