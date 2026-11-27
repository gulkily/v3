# Keep the Zalgo post menu above the next card

Cycle: v3/zalgo_post_actions_layer; Mode: one-shot; Index: [Feature plans](../README.md)
Debug mode: enabled
Effort study: P2, [cycle ledger](../effort_ledger/zalgo_post_actions_layer.md); extended estimates disabled.
**Cycle estimate: 0.10 on R1 (single estimate after inspection; no pristine intake).**
Status: focused browser verification passed; draft review and production acceptance pending.

Original report: [Zalgo display bug](https://zenmemes.com/threads/thread-20261127042736-3a0a3af3). Its image shows Activity and the reply composer covering the open post More actions menu. No matching feedback item or open PR was found.
Authorization: Ilya's standing ZenMemes bug sweep request authorizes verified, straightforward small fixes and a draft PR. No merge, deployment, forum reply, or production writes.
Approach and reuse: the existing menu already has panel stacking in content-interactions.css, but Zalgo transforms each card, so the panel cannot escape the card's stacking context. Raise just a Zalgo post card with that disclosure open, without altering its transform or the existing menu, markup, JavaScript or permissions. No new dependencies or migration.
Concepts touched: existing [thread activity](../../concepts/thread_activity.md) and [post retraction](../../concepts/post_retraction.md) are used unchanged; this is a theme paint fix, not a change to either concept's contract.
Completion: open the existing More actions disclosure on a post with Activity and a composer beneath it; both menu items must receive pointer hits, then normal stacking and focus must return when dismissed. Use desktop and phone-width Chromium with real styles, and verify the normal theme and existing keyboard/outside-click behavior. The reported device and exact source thread are not identified; production acceptance stays pending. Run smoke and inspect the final diff.
Baseline: origin/main `83d6798f3`; branch `fix/zalgo-post-actions-layer`. A 390 px browser fixture using the real CSS reproduced the first menu action being hit-tested as the Activity card. Planning commit: `5eeed7303`.

## Study history

2026-11-27 UTC: intake first recorded after source/screenshot inspection and baseline replay, 0.10; execution entry 0.10. Single exposed Dot rater. Relative R1 estimate, not measured effort.

*— Dot · 2026-11-27 UTC*

## Execution and verification

2026-11-27 UTC: added one theme-only rule. It raises the post card only while More actions is open; no JavaScript, templates, data, or authorization changed. The existing menu test now exercises the sibling Activity and composer stacking with the project's styles. Pointer hit-testing is used because browser visibility alone passed even when a sibling intercepted the pointer.

- Failed as expected before the CSS change: the 390 px Zalgo case hit a following card instead of a menu button.
- Passed after the change: `node tests/browser/post_retraction_menu.mjs` with headless Chromium. Both items receive pointer hits in Gloss and Zalgo at 390 and 1280 px; the original keyboard, focus, outside click and touch checks pass. Closing removes the open-card layer. Visually inspected the local 390 px baseline and corrected examples.
- `./v3 test --smoke`: 427 passed and 67 failed on both untouched main and the patch; the failure sets match. Failures include local GPG agent startup and PHP app/runner checks. The full suite was not run.
- The separate `node tests/browser/post_retraction.mjs` check failed at its pre-browser legacy-label Git fixture commit on both main and the patch. It did not exercise signing/retraction; those paths were not changed.
- The originating device/browser and production acceptance are not verified. On a deployment with the patch, select Zalgo, open a post you own with Activity below it, and open More actions; verify that both options now read and receive a tap above Activity and that Escape or an outside click closes the menu.
- Final diff reviewed: the CSS rule, existing browser test, and this cycle's records only. No deployment, production writes, new dependency, or changes to the retraction contract. Draft PR review pending.

Study closeout: retrospective scope estimate 0.10 on R1; no scope revisions or measured effort. Extra diagnostics are in [the debug log](zalgo_post_actions_layer_debug_log.md).

*— Dot · 2026-11-27 UTC*
