# Bubblegum You menu and Tools captions

Cycle: `v3/bubblegum_menu_labels`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/bubblegum_menu_labels`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)
Debug report: [log](bubblegum_menu_labels_debug_log.md)

**Cycle estimate: 0.08**

Status: planning; next permitted action: implement after the planning commit.

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
Planning commit: pending.

## Dated execution history

- 2026-11-14: reproduced both bugs in headless Chrome from live page snapshots with the repo stylesheets (desktop You menu at 900px; Bookmarklets caption height doubles at 360-500px and 540-620px widths).

## Study history

- Intake: **0.08**, recorded after the reproduction (no pristine intake). Single exposed rater Claude (claude-opus-5-5).

## Outcome

Pending.

## Verification

Pending.

## Final review

Pending.

## Commit/merge/publication status

Planning commit pending.

## Attribution

Claude (claude-opus-5-5), 2026-11-14.
