# Forte Profiles — Step 1: Solution Assessment

## Problem
Forte has no user directory and no Forte-native profile page; author names already link out to classic's `/profiles/{slug}`/`/user/{username}` (inherited for free via the shared `renderAuthorHtml()` helper), and per the approved direction, that link target should become Forte-native instead.

## Option A: Standalone Forte-styled pages (new routes, full navigation away from the board)
`/forte/profiles/{slug}` and `/forte/users/` as their own paned-chrome pages, same shape as classic's own separate profile/directory pages.
- Pros: real, shareable, bookmarkable URLs for free — no new client JS or state-sync machinery needed, just GET routes + templates; consistent with every other piece of Forte state (threads, tags, replies, permalinks) already being URL-addressable, a direction this project just invested a full cycle establishing.
- Cons: clicking an author name navigates away from the board entirely (loses the current pane state, same as it already does today going to classic — not a new cost, just not eliminated either).

## Option B: In-board `<dialog>` overlay for both profile and directory
Mirrors the `forte_compose_thread` New Thread dialog precedent — no navigation away.
- Pros: keeps the reader's board context intact.
- Cons: not URL-addressable unless built with the same URL-sync machinery `forte_thread_selection_url_sync` just added for thread selection — real extra complexity, disproportionate for a "look someone up" feature; would be the first piece of Forte state to step backward on shareability right after a cycle spent establishing it everywhere else.

## Option C: Hybrid — profile as a dialog, directory as a standalone page
Directory doesn't fit a small dialog well (unbounded list); a single profile does (bounded, like New Thread's form).
- Pros: each piece gets the shape that fits it best.
- Cons: two different navigation patterns for two closely-related pages; still loses URL-shareability for the profile half, for the same reason as Option B.

## Recommendation
**Option A.** It's the only option that doesn't trade away URL-shareability, costs the least new code (no dialog/state-sync layer at all), and matches classic's own precedent of treating profiles/directory as their own pages rather than inline overlays.

## Decision
**Option A, confirmed for the full profile page and the user directory** — both remain standalone Forte pages with real URLs, exactly as recommended.

**Refined for author-name clicks specifically**: clicking an author name opens a lightweight summary dialog (mirroring the `forte_compose_thread` New Thread `<dialog>` precedent) rather than navigating straight to the full profile page. The dialog links to the full standalone page for anyone who wants it. This keeps the board's context intact for the common "who is this" glance while the full page — the one with real content (approval status, thread/post counts, public key details) — stays the URL-shareable source of truth Option A was chosen to preserve; the dialog is a convenience layer on top, not a replacement for it, so nothing about the shareability tradeoff from Option B/C applies here (the dialog itself needs no URL, since it always has a real page one click away).
