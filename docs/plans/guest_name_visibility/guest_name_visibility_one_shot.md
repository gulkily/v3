Cycle: `v3/guest_name_visibility`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/guest_name_visibility`; [cycle ledger](../effort_ledger/guest_name_visibility.md); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.10**
Status: implemented and verified locally; next permitted action: push and PR (merging and deploying are the maintainer's)

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
- CSS: `:root[data-viewer-named] .compose-guest-note { display: none; }` (placed in `site.css`, see history).
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
Planning commit: `2b08b2a7`.

## Dated execution history

- 2026-11-18: plan written and committed (`2b08b2a7`).
- 2026-11-18, discovery: a composer "Post as {name} · Change" row existed and was removed on 2026-10-28 at the maintainer's request ("takes up way too much space"; `9b6e5065`, `ui_copy_audit_checklist.md`). Weighed a zero-space alternative (append " as guest" to the Post button, "Choose a name" in the ··· menu) and dropped it: the usage log records the submit button's text, browser tests click `Post` by exact name, and the ··· menu hides the link. Kept the plan: one meta line, guests only, so named members (the maintainer included) never see it. Flagged in the PR.
- 2026-11-18, correction: the hide rule went into `site.css` rather than `compose.css`, since not every page with a composer loads `compose.css` (QDB add, calendar day) and a missed page would show the line to a named member.
- 2026-11-18: on phones the collapsed inline reply box shows only its body until focused (existing `compose.css` rule); the guest line follows that, like Post.
- 2026-11-18, pre-existing failure fixed in scope: `tests/browser/invite_zero_click.mjs` failed on `7d9b4b4d` looking for "Expires 30 days after creation.", which `8e6f232c` (2026-11-17) moved into the QR caption "Scan to join · valid 30 days". The test now looks for the caption.
- 2026-11-18: new browser test `tests/browser/guest_name_visibility.mjs`.

## Outcome

Delivered as planned:
- `templates/partials/composer.php`: "Posting as guest · Choose a name" under the action row in every composer, linking to `/account/key/#choose-name`.
- `templates/layout.php` head script: `data-viewer-named` on `<html>` when a keypair is stored and its name is not `guest` or a chosen name is pending; `site.css` hides the line then.
- `templates/pages/account_key.php` + `account.css`: the Name action reads "Choose a name" while the pre-paint guest-naming hint is set, otherwise "Change"; the editor opens empty for a guest; `#choose-name` focuses the guest card's Name field or opens the member card's editor.

Limitations: a browser with a stored name but no keypair counts as guest (it does post as guest). The line shows without JavaScript too; Account itself needs JavaScript to name a key, as before.

## Verification

- `./v3 test --smoke AccountKeyPageHooksTest LocalAppSmokeTest`: 485 run, 485 passed (new assertions for the composer line, the head check, the Account label and hash handler).
- Browser (Chrome headless, 390px): `guest_name_visibility.mjs` PASS (fresh browser sees the line on New post and on a focused reply; the link lands on Account with the Name field focused; a registered key named guest sees "Choose a name" and an empty editor; after saving a name and on a named browser from the start, the line is hidden). `account_initial_name.mjs` PASS (public, private). `invite_zero_click.mjs` PASS after the fix (includes the username-changes-disabled hidden-button check). `composers.mjs` PASS on rerun; one earlier run failed and a rerun on base also failed once, so treated as an existing flake, not investigated further.
- Looked at screenshots: Gloss (default) composer and Account at 390px; Chicago composer.
- Full suite not run (maintainer runs it).

## Final review

Diff checked: no duplicated components (reuses the shared composer, Account editor and head-script pattern), files within scope plus the one pre-existing test fix, UI copy checklist line added. No concepts touched.

Commit/merge/publication status: completion commit pending at time of writing; PR to follow. Not merged or deployed.

## Study history

- 2026-11-18 intake: **0.10** (exposed, after reading the composer, Account page and the guest-name write gate). Single rater Claude (claude-opus-5-5).
- 2026-11-18 closeout retrospective: 0.12 (pre-existing test fix and the removed-row history added work).

Debug report: [debug log](guest_name_visibility_debug_log.md)

*— Claude · 2026-11-18 UTC · Model: Claude Opus 5.5*
