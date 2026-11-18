Cycle: `v3/guest_name_visibility`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/guest_name_visibility`; [cycle ledger](../effort_ledger/guest_name_visibility.md); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.10**
Status: planned; next permitted action: implement on `feat/guest-name-visibility`

## Original request

2026-11-18: "On Zen Memes, the option to choose a username if it's enabled, and the fact that you're using a guest username should be more apparent to a new user."

## Authorization

The request names the outcome and nothing needs the maintainer's choice, so Claude chose a one-shot (AGENTS.md: one-shot by default). Carried to a pushed branch and an open PR. Merging and deploying are the maintainer's.

## Outcome and work type

Application change.

Today a new visitor only learns they are "guest" on Account, which they have no reason to open. And once a guest key is registered (it is after the first signed post), Account shows the member card with the name "guest" and a small "Change" that a guest may not even read as the way to pick a name.

1. **Every composer** (new post, reply, paned board, pledge form; all render `partials/composer.php`) gets one line under Post: "Posting as guest · Choose a name". The line links to `/account/key/#choose-name`. It shows when this browser has no key, or its key's name is `guest` with no name pending, and is hidden otherwise. The decision is made before first paint from browser storage (an attribute on `<html>` set in the layout head script), so cached and static pages stay static and the line never flickers. Without JavaScript it shows, which is true: such posts go out as guest.
2. **Account, member card for a key named `guest`**: the Name row's action reads "Choose a name" instead of "Change", and opens the editor empty instead of pre-filled with "guest".
3. **`#choose-name` on Account** opens the way to name yourself: on the guest card it focuses the Name field; on the member card it opens the name editor if naming is allowed.

"If it's enabled": a guest's first name is always allowed (`LocalWriteService` lets an unnamed guest choose an initial name even with `FORUM_USERNAME_CHANGES_ENABLED` off). The composer line names only guests, so it never offers a rename the server would refuse. Named members see nothing new.

Not doing: changing the "You" menu label, the flag, the server write path, or guest posts' display.

## Approach and risks

- Head script in `templates/layout.php`, beside the existing `data-member` / `data-visitor` checks: set `data-viewer-named` on `<html>` when a keypair is stored and either `forum_pki_username` is not `guest` or a `forum_pending_profile_name:openpgp:<fingerprint>` value exists (the same keys `forumAccountInitialNameHint` reads).
- CSS in `compose.css`: `html[data-viewer-named] .compose-guest-note { display: none; }`.
- Account: caption and empty prefill in the existing `syncSimpleUI` / `bindNameEditor`; hash handling in `initAccountKeySimpleUi`.

Risks:
- **Wrong state shown** (named member sees "guest"): the attribute uses the same storage keys as Account's guest check. Covered by a browser check of both states.
- **Crowding the phone composer**: one short meta line below the action row, not in it.
- **Stale after naming**: naming happens on Account, so the next page load re-runs the head check.

## Reuse decisions

- Pre-paint viewer state: the existing head script pattern (`data-member`, `data-visitor`) in `templates/layout.php`; no new script file.
- Composer: the one shared `templates/partials/composer.php`; no per-placement copies.
- Naming UI: the existing Account guest card field and member name editor (`templates/pages/account_key.php`, saved by `browser_signing.js`); no new naming form.
- Copy: `meta` class for the line, as other composer hints.

## Completion conditions

Normal entry: open `/compose/thread` or a thread's reply box in a fresh browser. The line shows; following it lands on Account with the name field focused; after saving a name, the next composer shows no line. A named member never sees it.

## Concepts touched

None in the inventory (`docs/concepts/` has no guest-identity or composer entry); used unchanged: browser identity storage keys.

## Verification and recovery plan

- `./v3 test --smoke` plus `LocalAppSmokeTest` (renders the Account guest card) and `AccountKeyPageHooksTest`; new assertions for the composer line and the head check.
- Real-browser check (Playwright if available): fresh browser shows the line; with `forum_pki_username` set to a name and a keypair stored, it is hidden; `#choose-name` focuses the field. Themes: Gloss (default) and one pixel theme for the line.
- UI copy checklist entry (`docs/plans/ui_copy_audit_checklist.md`).
- Recovery: revert the commit; no records or schema touched.

Starting revision and branch: `7d9b4b4d` on `main`; branch `feat/guest-name-visibility`.
Planning commit: pending.

## Dated execution history

- 2026-11-18: plan written.

## Study history

- 2026-11-18 intake: **0.10** (exposed, after reading the composer, Account page and the guest-name write gate). Single rater Claude (claude-opus-5-5).

*— Claude · 2026-11-18 UTC · Model: Claude Opus 5.5*
