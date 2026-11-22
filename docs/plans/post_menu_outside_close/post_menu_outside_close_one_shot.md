# Close the post "…" menu on an outside click

Cycle: v3/post_menu_outside_close; Mode: one-shot; Index: [plans](../README.md)
Debug mode: enabled
Effort study: railspike-effort, R1/P2; [cycle ledger](../effort_ledger/post_menu_outside_close.md)
Extended estimates: enabled

**Cycle estimate: 0.10**

Status: locally implemented and verified; deployment and production acceptance pending. Next action: push and open a PR when the maintainer asks.

Original request: "please do f37, f32 as one-shots" (2026-11-22), after choosing from a ranked list of non-import backlog items.
Authorization: 2026-11-22 chat instruction authorizes a bounded one-shot for PF-20261111-F32. No push, PR, merge, deployment or production access is authorized. F37 is a separate cycle (`v3/quill_thread_link`).
Feedback: PF-20261111-F32 ("Should the ... button's menu go away when I click elsewhere on the page?", thread-20261109174818-d73f84e4, 2026-11-09).
Outcome and work type: application. An open post "More actions" menu (the "…" on a post, `details.post-more-actions`) closes when the person clicks or taps anywhere outside it, including on another post's "…", which then opens alone. Escape, the menu's buttons and the no-JavaScript `<details>` behavior are unchanged.
Which menu: the composer's "…" (`details.compose-action-menu`) has closed on outside clicks since 2026-10-14 (`compose_disclosure.js` `closeMenus`), and the account and sort menus do too (`account_menu.js`). The post "…" menu only closed on Escape when the report was made and still does, so it is the menu the report describes. The post card's "More" is an inline section, not a popup, and is out of scope.
Boundary: `public/assets/post_retraction.js`, `tests/browser/post_retraction_menu.mjs`, this record, the plans index, the cycle ledger and the feedback item. No markup, CSS, copy, server or dependency changes.

Approach: In `post_retraction.js`, beside its existing Escape handler for `details.post-more-actions`, add one document click listener that closes every open post menu not containing the click target, the same pattern as `account_menu.js` and `compose_disclosure.js`.
Reuse decisions: Reuse the existing native `<details>` menu and its script (`post_retraction.js`, loaded wherever the menu renders: `ThreadAndPostPageController`, `ComposeAndAccountKeyController`). Considered adding the selector to `account_menu.js`, which already closes the account and sort menus; kept the post menu's behavior in the script that already owns its Escape handling so the menu has one owner. No new component.
Risks: (1) A click on a button inside the menu must not be swallowed: the listener only closes menus that do not contain the target, so in-menu clicks are untouched and the existing handlers still close it after an action. (2) Touch: a tap fires click, covered by the existing mobile pass of the browser test. Low impact either way; no data or signing path changes.
Completion conditions: On a page with posts, opening a post's "…" and clicking elsewhere closes it; opening a second post's "…" closes the first; a click inside the open panel keeps it open; Escape still returns focus to "…". Checked at desktop and phone (touch) sizes in the existing browser test. Production acceptance is the maintainer's after deployment.
Concepts touched: none; the inventory has no entry for the post overflow menu, and this changes no concept-level behavior.
Verification and recovery plan: `node tests/browser/post_retraction_menu.mjs` (extended), `node tests/browser/post_retraction.mjs`, `./v3 test --smoke` (smoke set; no PHP changed), `git diff --check`. UI: one real thread page in a browser at phone and desktop widths. No themes change, since only behavior changes. Recovery: if a menu does not close, Escape or clicking "…" again still closes it. Full suite not run (maintainer-only).
Starting revision and branch: e2c112cabc09eb661ce2ff680ba9d880de6a7ba0 (freshly fetched origin/main); branch `fix/post_menu_outside_close`.
Planning commit: f7f4b4dbc (confirmed before implementation).

## Study history

2026-11-22 intake: pristine pre-inspection estimate missing; I read the menu scripts and templates while scoping. First exposed scoped estimate **0.10**, scope v1, R1/P2; R1 cached reference hashes verified against the manifest. Claude, single exposed rater; no independent rating or measured labor.
2026-11-22 execution entry: 0.10 reaffirmed for scope v1. No alternatives or numbered stages; none manufactured.
2026-11-22 closeout: retrospective 0.10; technical work complete locally; maintainer fit judgment and acceptance pending.

## Execution and verification

2026-11-22: Planning commit confirmed before the script edit. Added one document click listener to `post_retraction.js`, beside its Escape handler, closing every open `details.post-more-actions` that does not contain the click target.
2026-11-22: Extended `tests/browser/post_retraction_menu.mjs` with a second post menu and outside text: a click inside the open panel keeps it open, an outside click closes it, opening the second "…" closes the first, at desktop and phone (touch). Correction to the test fixture, not to the fix: the first run failed on the touch pass because the fixture had no viewport meta tag, so phone emulation laid the page out 980px wide and the in-panel tap landed on "…" itself. Added the same `<meta name="viewport">` the real layout has (`templates/layout.php`).
2026-11-22: Worked in a second worktree of the same origin/main revision while the F37 tests ran in the main checkout, with `node_modules` symlinked from the main checkout (untracked, not committed).

Verification: PASS — `node tests/browser/post_retraction_menu.mjs`: keyboard, Escape/focus, outside click, second menu, touch. Without the script change the same test fails at "an outside click closes it", so it covers the fix.
Verification: PASS — real thread page, server-backed: a temporary, uncommitted copy of `tests/browser/post_retraction.mjs` opened a post's "…" as the author (desktop click) and as the operator (390x844 touch), tapped the page heading, and saw the menu close. Screenshot of the open menu at phone width reviewed: menu shown above the bottom bar as before.
Verification: BASELINE FAILURE — `node tests/browser/post_retraction.mjs` stops in fixture setup, before any page loads: its `git commit` of a legacy-label fixture finds nothing to commit (exit 1). Same with the script change stashed, so it predates this cycle; the fixture has drifted from main. Left for separate work.
Verification: BASELINE FAILURES — `./v3 test --smoke`: 486 run, 482 passed, 4 failed: the text-rule failures `AgentPostingApiTest` (2), `SingleRequestHttpTest::testUnicodeRoundTripAndSymbolRejection` and `SignedPostBundleValidatorTest::testStrictSubsetAndExactBytes`, already replayed on unchanged main in [application_version_source](../application_version_source/application_version_source_one_shot.md). No PHP changed in this cycle.
Verification: PASS — `git diff --check`; `./v3 feedback check` 355 items, 0 problems.
Verification: INAPPLICABLE — theme checks and navigation icons (no markup, CSS or copy change); UI copy checklist (no copy). No-JavaScript: the menu stays a native `<details>` and behaves as before.
Verification: NOT RUN — full suite (maintainer-only); a physical phone; production.
Release/recovery: if a menu ever fails to close, Escape or "…" still close it. Production check: open a post's "…" on a phone and tap elsewhere. Feedback item stays `blocked` until then.

## Closeout

2026-11-22 retrospective scope assessment: **0.10**, same as the prospective 0.10. Scope v1, R1/P2, Claude single exposed rater. The touch-fixture correction and the broken retraction fixture added checking time but no scope.
Outcome: delivered as planned. No new requests; no enhancements added.
Final review: one listener in the script that already owns the menu's keyboard handling; same pattern as `account_menu.js` and `compose_disclosure.js`, no new component. Diff limited to the recorded boundary plus the derived feedback index.
Commit/merge/publication status: planning commit f7f4b4dbc; completion commit pending creation. Not pushed, no PR, not merged or deployed.

*— Claude (claude-opus-5-5) · 2026-11-22 UTC*
