> **Feature plan:** [Step 1](./ui_tab_bar_scroll_away_step1_solution_assessment.md) · [Step 2](./ui_tab_bar_scroll_away_step2_feature_description.md) · [Step 3](./ui_tab_bar_scroll_away_step3_development_plan.md) · [Step 4](./ui_tab_bar_scroll_away_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_tab_bar_scroll_away` ([ledger](../effort_estimation_trial.md#cycle-v3ui_tab_bar_scroll_away)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.45**

## Original Query

I kind of like how the bar looks on iPhone. Can we make it scroll away? I think the reply bar should definitely go.

Great, please write step 1.

## Understood Intent

Keep the ZenMemes phone tab bar from [cycle 1](../ui_shell_navigation/) but stop it from always covering the page. Problems the user reported with floating elements on 2026-10-27: covers content, Page Up/Down skips the covered part, sits oddly above iPhone Safari's translucent toolbar area, can't be dismissed, and feels annoying. The reply bar removal belongs to the unmerged `ui_thread_reply_dock` cycle, not this one (see Requests).

## Problem

The phone tab bar is always on screen, so it covers content while reading and the user cannot get it out of the way.

## Option A: Hide while scrolling down, return on scroll up

**Estimate: 0.45**

A small script hides the bar (slides it down) when the reader scrolls down and brings it back when they scroll up, reach the top or bottom of the page, or move keyboard focus into it. Parts that sit above the bar (account menu, post-send reply box, private message composer) follow its visible height.

- Pros: matches Safari's own toolbar; out of the way while reading and paging down; always one flick away; keeps the look the user likes.
- Pros: without JavaScript the bar stays as it is today.
- Cons: still covers content and shows the iPhone gap while visible; needs scroll-direction thresholds that feel right on real phones.
- Driver: scroll thresholds and the three dependent bottom elements. Uncertainty: iOS rubber-band scrolling and toolbar resizing.

## Option B: CSS-only scroll-driven hide

**Estimate: 0.30**

The bar fades or slides away as the page scrolls past the top, using CSS scroll-driven animation, with no script.

- Pros: no script; no timing code.
- Cons: not direction-aware, so the bar only returns at the top of the page; scroll-driven animation is new in Safari, so older iPhones keep the always-on bar.
- Driver: browser support. Uncertainty: Safari behavior on older iOS.

## Option C: Bar at the end of the page, not floating

**Estimate: 0.55**

The bar stops floating and sits at the bottom of each page; the top keeps a compact row (wordmark, New post, You) so navigation is reachable without scrolling to the end.

- Pros: removes floating entirely, so none of the reported problems remain.
- Cons: loses the floating look the user likes; navigation at the end of long pages is rarely seen; two navigation places on phones.
- Driver: phone header layout across 13 themes. Uncertainty: low.

## Recommendation

Option A. It keeps the bar the user likes and makes it go away while reading, which answers covering, paging and dismissal. It is a releasable slice on its own: normal entry is any ZenMemes page on a phone, the outcome is a bar that hides and returns, and recovery is the unchanged bar without JavaScript or with reduced motion (no animation, same hide/show). Separate branch from `main`; independent of the reply dock cycle.

## Requests

| Request | Disposition and reason | Approval status | Outcome |
| --- | --- | --- | --- |
| Remove the floating reply bar ("should definitely go") | Separate work: changes the unmerged `ui_thread_reply_dock` cycle's approved Step 2; handled there as a scope revision | Pending: revise that cycle's Step 2/3; whether the pre-existing post-send floating reply box also goes is not yet confirmed (recommended: yes) | Pending |
| Private message composer is also sticky above the tab bar | Noted while inspecting; out of scope here beyond following the bar's height | Not requested | Candidate follow-up |

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Effort study

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Intake | 2026-10-27T23:06:56Z | 0.45 | v1 / R1 / P2, baseline `main` `7ef735dd2` | Claude, single exposed rater; built the tab bar in cycle 1 and discussed this change before rating; no code inspected for this cycle before rating | Scroll-away phone tab bar as requested |
| Step 1 handoff | 2026-10-27 | 0.45 (Option A) | v1 / R1 / P2 | Same, after inspecting the tab bar styles and the three elements that depend on its height | Dependents were expected; no change |

Option estimates are separate alternatives and are not summed. R1 hashes verified earlier today (2026-10-27).
