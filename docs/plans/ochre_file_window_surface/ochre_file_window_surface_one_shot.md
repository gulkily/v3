# Ochre file window surface

Cycle: `v3/ochre_file_window_surface`; Mode: one-shot; [Plans index](../README.md); [Predecessor](../post_file_windows/post_file_windows_one_shot.md)
Debug mode: enabled (project marker)
Effort study: standing R1/P2 enrollment; [ledger](../effort_ledger/ochre_file_window_surface.md) to land at closeout.

**Cycle estimate: 0.10**

Status: bounded follow-up planned from `c62b0c269` on `fix/ochre-file-window-surface`; outcome awaits local validation then draft review.

## Request and authorization

The [report](https://zenmemes.com/threads/thread-20261123173506-b7aec260) shows the Source dialog letting the page's text and image show through in Firefox in the HN (Ochre) theme. The standing instruction to fix straightforward ZenMemes bugs authorizes this isolated visual correction, local verification, branch and draft PR, not a forum reply, merge, deployment or production writes. Follow-up to [PF-20261118-F05](../../feedback/items/PF-20261118-F05.txt); no separately matched item in main's feedback. No duplicate in inspected current/recent PRs; #374 covers Pending.

## Approach and completion

Extend the existing opaque-panel rule in `public/assets/theme-ochre.css` (currently for Pending) to the existing `dialog.card.file-window` built by `file_windows.js` from links in `templates/pages/post.php`. Reuse `--panel`; ordinary cards stay transparent. No duplicate component, JavaScript, routes, dependency, accessibility semantics, format or data changes. CSS specificity must beat the transparent base card rule and leave other themes alone. Concepts: existing [window controls](../../concepts/window_controls.md) unchanged.

In the ordinary post details flow, Source opens readable above page content without navigation in Ochre; Commit, Signature and Public key share the fix; Pending remains opaque and ordinary cards unchanged. Close still returns focus and Raw still points to the file; other themes unchanged, failed inline loads still fall back to the file. Exercise ordinary link click on a synthetic post-details fixture with real assets, raw responses and narrow/desktop widths, before and after; check unaffected theme and scoped selectors. Run smoke before/after and confirm failures, if any, are identical. No full suite. Original Firefox/device and deployment acceptance require a post-merge check on the report's sample.

Intake 0.10 (after inspection, not pristine), single exposed Dot rater; execution entry 0.10, scope v1 / R1 / P2. Planning commit pending.

*Dot, 2026-11-28 UTC*
