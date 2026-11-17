# Bubblegum You menu and Tools captions

Cycle: `v3/bubblegum_menu_labels`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/bubblegum_menu_labels`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)
Debug report: [log](bubblegum_menu_labels_debug_log.md)

**Cycle estimate: 0.08**

Status: technically complete and committed locally; push and PR blocked (session has no push access to gulkily/v3); thread reply waits for the PR. Next permitted action: push, open the PR, reply in the thread.

## Original request

Thread: [Image](https://zenmemes.com/threads/thread-20261114183048-312aba71), maintainer (ilyag), 2026-11-14, request post `reply-20261114183546-3cd5e1da`: "a couple visual bugs with the bubblegum theme: the account an invite buttons in the second screenshot; the wrapping caption for bookmarklets icon in first screenshot". Tagged #bug #meta. Feedback item: PF-20261114-B01.

## Authorization

Maintainer pasted the request post's link into a Claude session on 2026-11-14 with no other text. Under `docs/agents/thread_to_pr.md` this authorizes one-shot execution of the bounded outcome, pushing the branch, opening the PR and replying in the thread. It does not authorize merging, deploying or production access.

## Outcome and work type

Application change, Bubblegum stylesheet only.

1. Desktop You menu: Account and Invite pills show the 16px nav icon with the usual space before the label, like the other Bubblegum pills. The 32px icon and the green status dot stay on the phone More tiles, which were designed for them.
2. Tools launcher: tool captions never break inside a word at any width. Words still wrap between words ("Offline / Reading").

Excluded: other themes; the phone More panel's look; the Tools launcher layout at desktop widths.

## Approach and risks

- You menu: the rule that sets 32px icons (and `margin: 0`) is shared between the Tools launcher tiles and `.account-menu__links .nav-link::before`, outside any media query, so it reaches the desktop dropdown, where links are `display: block` text with an inline-block icon and no gap. Move the account-menu half of that selector, and the `::after` status dot, into the existing `max-width: 639px` block that builds the phone tiles. Desktop then falls back to the shared 16px sizing in `theme-nav-icons.css`, which every other icon theme uses.
- Tools captions: `.card` sets `overflow-wrap: anywhere`, which the launcher buttons inherit, so a narrow tile splits "Bookmarklets". Set `overflow-wrap: normal` on the Bubblegum launcher buttons, drop their inline padding (0.2rem) so the caption gets the tile's full width, and raise the phone column minimum from 5.5rem to 5.75rem so the longest caption word (Bookmarklets, about 90px in the theme's rounded font at 0.85rem) fits. 5.75rem keeps three columns on a 375px phone (list width 308px needs 3 x 92 + 2 x 6.4 = 289px). If a font is wider than expected, the word overflows a few pixels into the column gap rather than splitting.
- Risk: low; one theme file, no markup, no behavior.

## Reuse decisions

- Icons: the shared 16px nav icon sizing in `public/assets/theme-nav-icons.css` (unchanged), as Gloss, Word 97 and the rest already use in the desktop menu.
- Phone tile styling: the existing `@media (max-width: 639px)` More-panel block in `public/assets/theme-bubblegum.css`.
- Launcher grid: the existing Bubblegum launcher rules; only values change. No new classes, markup or files.

## Completion conditions

- Normal entry: Bubblegum theme; open You on desktop; open /tools/ on a phone or narrow window.
- End-to-end: menu pills match the other Bubblegum pills; no caption splits mid-word.
- Release: PR merged and deployed by the maintainer.

## Concepts touched

None.

## Verification and recovery plan

Render before/after in headless Chrome against the current stylesheets: /tools/ at 320 to 660px in 20px steps (check every caption's lines start at a word), at 375 and 499px, and at desktop; the You menu open at 900px and 375px (phone More panel unchanged). Smoke set plus `ThemeRegistryTest` and `NavIconSetsTest`. Full suite not run, per `AGENTS.md`. Real iOS SF Pro Rounded metrics cannot be rendered here.

Starting revisions and branch: `fix/bubblegum-menu-and-tool-labels` from `9122adf0fa97f2e9e1bdee3d1b1bdd5e5fa684d5`.
Planning commit: `6a2f183f` (plan, index link, feedback item only; confirmed with `git log`).

## Dated execution history

- 2026-11-14: reproduced both bugs in headless Chrome from live page snapshots with the repo stylesheets (desktop You menu at 900px; Bookmarklets caption height doubles at 360-500px and 540-620px widths).
- 2026-11-14: planning commit `6a2f183f`, then implemented as planned in `public/assets/theme-bubblegum.css`: account-menu 32px icon, `position: relative` and status dot moved into the phone More block (now scoped with `[data-shell="app"]` like the other tile rules); launcher buttons get `overflow-wrap: normal` and no inline padding; phone column minimum 5.5rem to 5.75rem. No deviations from the plan.
- 2026-11-14: push failed: `access denied by the git proxy: gulkily/v3 is not in this session's authorized repository set` (403). Attaching the repository needs the maintainer's approval; branch kept locally and a patch handed to the maintainer. Thread reply held until the PR exists, per `thread_to_pr.md` recovery.

## Study history

- Intake: **0.08**, recorded after the reproduction (no pristine intake). Execution entry reaffirmed 0.08; closeout retrospective **0.08**. Single exposed rater Claude (claude-opus-5-5). Not hours or measured labor; maintainer acceptance pending.

## Outcome

Delivered as bounded. Desktop You menu: Account and Invite are ordinary Bubblegum pills with the 16px icon and a gap before the label, no status dot. Phone More panel: unchanged (32px icons, status dots, coloured tiles). Tools launcher: captions wrap between words only; three columns on a 375px phone as before, four or five at 460-660px. Limitation: real iOS font metrics (SF Pro Rounded) were not rendered; if that font's "Bookmarklets" is wider than the 5.75rem column it overflows a few pixels into the column gap instead of splitting.

## Verification

- Headless Chrome (Google Chrome, Playwright) against live page snapshots of `/` and `/tools/` with this branch's stylesheets, Bubblegum selected through the theme storage key.
- Caption check at 320-660px in 20px steps, plus 375 and 1100px: a script finds any line break inside a word and any caption overflowing its tile. Before: 5 mid-word breaks. After: 0 breaks, 0 overflows.
- Screenshots: You menu open at 900px (Account pill fixed) and 375px (More panel unchanged); /tools/ at 375, 499 and 1100px. Invite was not on screen (logged-out snapshot) but uses the same rule as Account.
- `./v3 test --smoke ThemeRegistryTest NavIconSetsTest`: 455 run, 454 passed. The one failure, `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`, also fails on `main` at `9122adf0` (checked with this change stashed), so it is pre-existing and unrelated to a CSS-only change.
- `./v3 feedback check`: the new item passes; the one reported problem is pre-existing in `PF-20261111-F03` (a non-URL source line), which also stops `./v3 feedback index` from writing, so the index was not regenerated.
- Full suite not run, per `AGENTS.md`. Not checked: other themes (untouched files), iOS Safari rendering, live site after deploy.

## Final review

Diff is one stylesheet plus this record, its index line, the feedback item and the ledger/debug entries. No duplicated rules: the moved account-menu rules exist only in the phone block now; the shared 16px sizing in `theme-nav-icons.css` is reused unchanged. Comment added on the launcher rule explains why `overflow-wrap` is reset.

## Commit/merge/publication status

Planning commit `6a2f183f`; completion commit is the one containing this text (hash pending, reported to the maintainer). Not pushed, no PR, not merged, not deployed.

## Attribution

Claude (claude-opus-5-5), 2026-11-14.
