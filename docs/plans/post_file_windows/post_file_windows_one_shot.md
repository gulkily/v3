# Post file windows — one-shot

Cycle: `v3/post_file_windows`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](post_file_windows_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/post_file_windows`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/post_file_windows.md))
Extended estimates: enabled (no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.1**

Status: technically complete on `feat/post_file_windows`; pushed for review, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Original request, 2026-11-16 (maintainer, [thread](https://zenmemes.com/threads/thread-20261116080536-1ed26bc9), with a screenshot of a post's details page in Bubblegum): "for this dialog, it would be amazing to have adorable little popup windows for the file contents (which have a link to raw file)", then "adjust level of adorable for theme".

Thread so far (Claude, 2026-11-17): all four file links on that page (Source, Commit, Signature, Public key) answer with `text/plain`, so a click handler can fetch the link and show the text in a small window that keeps the link as "raw file"; Bubblegum's window chrome could be reused. The maintainer: "yes, please make a pr!"

Authorization: the maintainer, 2026-11-18 (Claude session): "Can you please scan Zen memes for anything that's asking for a PR? And if the task seems appropriate for a one-shot, make the PR and reply in the thread." With "yes, please make a pr!" in the thread, this authorizes a one-shot through an open PR and a thread reply. Exclusions: merge, deployment.

Feedback item: none matched in `docs/feedback/items/` (searched the thread ID, "popup", "raw file"). Created [PF-20261118-F05](../../feedback/items/PF-20261118-F05.txt) in the completion commit.

## Outcome and work type

Application change. On a post's details page (`/posts/<id>`), clicking Source, Commit, Signature or Public key opens a small window over the page showing that file's text, titled with the file name, with a Raw link (the original URL) and a close button. Several can be open at once, cascading. Each theme dresses the window as it dresses its cards, so Bubblegum gets its pink window and, with draggable windows on, a title bar and Float-mode dragging.

## Approach and risks

- A new page script `file_windows.js` handles clicks on `/source/` links inside the post facts table (marked `data-file-windows` in `post.php`). Modified clicks (Ctrl/Cmd/Shift/Alt, middle button) and failed fetches fall through to the normal link, so without JavaScript, or on error, the link works as today.
- Windows are non-modal `<dialog class="card file-window">` opened with `show()`, so the page stays usable and several can stay open. Escape closes the focused window; focus returns to the link.
- Risk: themes set `.card { position: relative }` with high specificity; the pending-user dialog already hit this and solved it with `:root dialog.<name>.card { position: fixed }`. Same fix here.
- Risk: long lines (armored keys are 64 columns; commit file lists may be longer). `pre` wraps with `overflow-wrap: anywhere` and scrolls inside a capped height.

## Reuse decisions

- Dialog pattern from `pending_user_summary.js` / `.pending-user-dialog` in `site.css`: a `dialog.card` built in script, fixed-positioned beside its anchor and kept in the viewport, backdrop-free here because it is non-modal. A separate small script rather than generalizing that one, which is specific to profiles and approval.
- `.card` theming everywhere, and Bubblegum's `theme-bubblegum.js` MutationObserver, which decorates any new `.card` with a title bar and Float dragging. No Bubblegum-specific code.
- Existing `/source/...` routes; no server change.

## Concepts touched

None changed. Uses window controls (`docs/concepts/window_controls.md`) unchanged.

## Completion conditions and verification plan

- Normal entry: on `/posts/<id>`, click Source; a window shows the record text with a Raw link; click Signature too; both open; Escape or × closes; Ctrl-click still opens the raw file in a new tab.
- Smoke test that `post.php` marks the table and loads `file_windows.js`; `node --check` on the script.
- Browser check on a fixture post in Light, Gloss, Word97 and Bubblegum (with draggable windows on), desktop and phone width: window inside the viewport, text shown, Raw link correct, two windows cascade, Escape closes, modified click not intercepted.
- `./v3 test --smoke` plus the changed tests. Full suite not run.

Starting revision and branch: `31e16b066774dbdf8f4d3709ae4ad021bfff9516` on `main`; branch `feat/post_file_windows`.

Planning commit: `32483359` (2026-11-18T07:39Z), containing only this record and its index link.

## Dated execution history

- 2026-11-18T07:38Z: plan written after reading `post.php`, `source_metadata.php`, `ThreadAndPostPageController::renderPost`, `pending_user_summary.js`, the pending-user dialog CSS and `theme-bubblegum.js`; checked prod's four link types (all `text/plain`).
- 2026-11-18T07:40Z: planning commit `32483359` confirmed. Added `file_windows.js`, the `.file-window` CSS beside the pending dialog's, `data-file-windows` on the post facts table, the script in the post page's list, and smoke assertions.
- 2026-11-18T07:45Z: discovery from the browser check. Placed under the clicked link, the first window covered the links below it, so a second file couldn't be opened without closing the first. Windows now open below the facts table, under the link's column, cascading. Correction to placement, within scope.

## Study history

- Intake, 2026-11-18T07:38Z: **0.1** whole-cycle projection, scope v1, R1/P2. Recorded after inspecting the files above (not a blind intake). Single exposed rater (Claude).
- Execution entry, 2026-11-18T07:40Z: 0.1 reaffirmed.
- Closeout, 2026-11-18T07:48Z: delivered scope v1. Retrospective **0.08**. Maintainer fit and acceptance pending.

## Outcome

Delivered: the file links on `/posts/<id>` open small non-modal windows (file name, Raw link, ×, the text), several at once, cascading; themes style them as cards; in Bubblegum with draggable windows on they get the title bar and Float dragging. Modified clicks, failed fetches and no-JavaScript browsers follow the link as before.

## Verification

- `./v3 test --smoke LocalAppSmokeTest::testPostPagesExposeAvailableAuthorPublicKeyLink`: 478 of 478 (marker and script asserted); `node --check public/assets/file_windows.js`.
- Scratch headless-Chrome check on a git-backed fixture post in Light, Gloss, Word97 and Bubblegum (draggable windows on) at 1280x900 and 390x800: Source opens with the record text and the right Raw link without navigating; a second window (Commit) opens and cascades; every window inside the viewport; Bubblegum windows get title bars; Escape closes one; Ctrl-click opens no window; no page errors. Screenshots reviewed.
- `./v3 feedback check`: 0 problems; `./v3 feedback index` regenerated.
- Not run: the full suite; Signature window (the fixture post is unsigned), Safari and real devices.

## Final review

Diff limited to `file_windows.js` (new), `site.css`, `post.php` (one attribute), `ThreadAndPostPageController.php` (one script path), `LocalAppSmokeTest.php`, this record and its index line, the feedback item and index, ledger and debug log. No server route, dependency or theme-specific code.
