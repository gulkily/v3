# Forte Profiles — Step 2: Feature Description

## Problem
Forte has no user directory and no Forte-native profile destination; author names link out to classic today, inherited for free via a shared template helper.

## User Stories
- As a Forte board-view reader, I want to click an author's name and get a quick summary without leaving the board, so I can identify who I'm reading without losing my place.
- As a Forte board-view reader who wants more detail, I want a link from that summary to a full, Forte-styled profile page, so the quick glance isn't a dead end.
- As a Forte board-view reader, I want a way to browse all users, so I can find someone without already knowing their name.
- As anyone with a link to a Forte profile or the user directory, I want it to load correctly on its own, so it's shareable the same way every other piece of Forte state already is.

## Core Requirements
- Author names in Forte (board content pane, reply tree) open a lightweight summary dialog on click — username, approval status, thread/post counts — reusing the existing `/api/get_profile` endpoint verbatim (already used elsewhere, e.g. `browser_signing.js`'s own profile check) for the dialog's content. The link itself still has a real `href` to the full profile page underneath, so it degrades gracefully without JS.
- The summary dialog links to a full, standalone Forte-styled profile page (`/forte/profiles/{slug}`, plus `/forte/user/{username}` mirroring classic's dual scheme) with the same content as classic's profile page (approval status/by-whom, thread/post counts, public key details) — **except** the "Approve user" action, which stays out of scope here (that's `forte_user_approval`'s job).
- A Forte-styled user directory (`/forte/users/`) lists approved users with thread/post counts, linking straight to each one's full profile page (no summary-dialog detour needed there — you're already looking at a list of names).
- Both new page types get a way back to the board (they're separate routes, not embedded in `/forte` itself, per the approved Step 1 decision).
- No changes to classic's own `/profiles/{slug}`, `/user/{username}`, or `/users/` pages, and no navigation added from them into Forte (that's `forte_classic_nav_bridge`'s call to make, not this cycle's).

## Shared Component Inventory
- `/api/get_profile` — canonical profile-summary endpoint. **Reused unchanged** as the summary dialog's data source.
- `fetchProfileBySlug()` / `fetchProfilesByUsernameToken()` / `fetchApprovedUserDirectoryUsers()` — canonical data fetchers already backing classic's profile/directory pages. **Reused unchanged** for the new Forte pages.
- `renderAuthorHtml()` (shared by every page that shows an author) — **extended**, not forked: a new Forte-target variant point at `/forte/profiles/...`/`/forte/user/...` instead of classic's, exposed as a sibling template helper alongside the existing one so classic's own rendering is untouched.
- The `forte_compose_thread` New Thread `<dialog>` (titlebar + body chrome) — **pattern reused** for the summary dialog's visual shape, not literally the same element.
- Classic's `profile.php` / `users.php` templates — **not reused directly** (full page layout, not paned chrome); new Forte-styled templates render the same underlying data.

## Simple User Flow
1. Reader clicks an author's name on the board.
2. A summary dialog opens in place: username, approval status, counts, a link to the full profile.
3. Reader follows that link (or navigates to `/forte/users/` from the board) to a full, Forte-styled page with a way back to the board.

## Success Criteria
- Clicking any author name in Forte opens the summary dialog without navigating away or losing board state.
- The summary dialog's "full profile" link, `/forte/profiles/{slug}` and `/forte/user/{username}` directly, and `/forte/users/` all render correctly as fresh, standalone loads (shareable, not session-dependent).
- Approval status/counts shown in Forte match classic's own profile page exactly (same underlying data, no drift).
- No "Approve user" affordance appears anywhere in Forte's new pages.
- Classic's `/profiles/{slug}`, `/user/{username}`, and `/users/` are completely unaffected.
