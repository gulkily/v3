# Keep the Zalgo post menu above the next card

Cycle: v3/zalgo_post_actions_layer; Mode: one-shot; Index: [Feature plans](../README.md)
Debug mode: enabled
Effort study: P2, [cycle ledger](../effort_ledger/zalgo_post_actions_layer.md); extended estimates disabled.
**Cycle estimate: 0.10 on R1 (single estimate after inspection; no pristine intake).**
Status: planned; next action is the isolated theme CSS correction and checks.

Original report: [Zalgo display bug](https://zenmemes.com/threads/thread-20261127042736-3a0a3af3). Its image shows Activity and the reply composer covering the open post More actions menu. No matching feedback item or open PR was found.
Authorization: Ilya's standing ZenMemes bug sweep request authorizes verified, straightforward small fixes and a draft PR. No merge, deployment, forum reply, or production writes.
Approach and reuse: the existing menu already has panel stacking in content-interactions.css, but Zalgo transforms each card, so the panel cannot escape the card's stacking context. Raise just a Zalgo post card with that disclosure open, without altering its transform or the existing menu, markup, JavaScript or permissions. No new dependencies or migration.
Concepts touched: existing [thread activity](../../concepts/thread_activity.md) and [post retraction](../../concepts/post_retraction.md) are used unchanged; this is a theme paint fix, not a change to either concept's contract.
Completion: open the existing More actions disclosure on a post with Activity and a composer beneath it; both menu items must receive pointer hits, then normal stacking and focus must return when dismissed. Use desktop and phone-width Chromium with real styles, and verify the normal theme and existing keyboard/outside-click behavior. The reported device and exact source thread are not identified; production acceptance stays pending. Run smoke and inspect the final diff.
Baseline: origin/main `83d6798f3`; branch `fix/zalgo-post-actions-layer`. A 390 px browser fixture using the real CSS reproduced the first menu action being hit-tested as the Activity card. Planning commit pending.

## Study history

2026-11-27 UTC: intake first recorded after source/screenshot inspection and baseline replay, 0.10; execution entry 0.10. Single exposed Dot rater. Relative R1 estimate, not measured effort.

*— Dot · 2026-11-27 UTC*
