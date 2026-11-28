# Ochre file window surface

Cycle: `v3/ochre_file_window_surface`; Mode: one-shot; [Plans index](../README.md); [Predecessor](../post_file_windows/post_file_windows_one_shot.md)
Debug mode: enabled (project marker)
Effort study: standing R1/P2 enrollment; [ledger](../effort_ledger/ochre_file_window_surface.md) to land at closeout.

**Cycle estimate: 0.10**

Status: bounded follow-up planned from `c62b0c269` on `fix/ochre-file-window-surface`; local Chromium checks pass; draft review, original Firefox retest and deployment pending.

## Request and authorization

The [report](https://zenmemes.com/threads/thread-20261123173506-b7aec260) shows the Source dialog letting the page's text and image show through in Firefox in the HN (Ochre) theme. The standing instruction to fix straightforward ZenMemes bugs authorizes this isolated visual correction, local verification, branch and draft PR, not a forum reply, merge, deployment or production writes. Follow-up to [PF-20261118-F05](../../feedback/items/PF-20261118-F05.txt); no separately matched item in main's feedback. No duplicate in inspected current/recent PRs; #374 covers Pending.

## Approach and completion

Extend the existing opaque-panel rule in `public/assets/theme-ochre.css` (currently for Pending) to the existing `dialog.card.file-window` built by `file_windows.js` from links in `templates/pages/post.php`. Reuse `--panel`; ordinary cards stay transparent. No duplicate component, JavaScript, routes, dependency, accessibility semantics, format or data changes. CSS specificity must beat the transparent base card rule and leave other themes alone. Concepts: existing [window controls](../../concepts/window_controls.md) unchanged.

In the ordinary post details flow, Source opens readable above page content without navigation in Ochre; Commit, Signature and Public key share the fix; Pending remains opaque and ordinary cards unchanged. Close still returns focus and Raw still points to the file; other themes unchanged, failed inline loads still fall back to the file. Exercise ordinary link click on a synthetic post-details fixture with real assets, raw responses and narrow/desktop widths, before and after; check unaffected theme and scoped selectors. Run smoke before/after and confirm failures, if any, are identical. No full suite. Original Firefox/device and deployment acceptance require a post-merge check on the report's sample.

Intake 0.10 (after inspection, not pristine), single exposed Dot rater; execution entry 0.10, scope v1 / R1 / P2. Planning commit `b6a36eca6` (local; remote mirror uses the same tree).

*Dot, 2026-11-28 UTC*

## 2026-11-28: verification and closeout

- Extended the existing opaque Ochre rule to the post file dialog, scoped to the same theme. No change to card markup, behavior or data.
- `node tests/browser/ochre_file_window.mjs` with the available isolated Chromium: before the patch failed on transparent Source at 390 px. After correction, two runs passed at 390 px (touch) and 1280 px (keyboard). The four file links opened text at the original page; the Raw destinations and close/focus behavior were preserved. Forced inline fetch failure followed the raw URL. Pending stayed opaque, ordinary Ochre cards stayed transparent, and Gloss retained its own opaque surface.
- `./v3 test --smoke` (PHP 8.4): 430 passed and the same 67 failed on baseline and patched code. Baseline failures include unavailable GPG agent and local runner/HTTP checks. Full suite not run. No new smoke failures; the smoke suite is not green.
- The report is desktop Firefox; local verification used Chromium, not the reporter's browser. After deployment, pick Ochre, open [the reported post details page](https://zenmemes.com/posts/thread-20261123173358-4a407ac8), click Source, and check that the underlying text/image do not show through. No production changes or posting.
- Final file boundary: Ochre stylesheet, regression fixture, existing feedback record and generated index if changed, this record and its index, ledger, and debug log. No migration, permission, flag, or release changes. Completion commit pending until created; not merged or deployed.

Retrospective scope estimate: 0.10, scope v1. No measured effort or maintainer acceptance claimed.

*Dot, 2026-11-28 UTC*
