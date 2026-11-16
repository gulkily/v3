# DM conversation tab bar Step 1 solution assessment

> **Feature plan:** [Step 1](./dm_conversation_tabbar_step1_solution_assessment.md) · [Step 2](./dm_conversation_tabbar_step2_feature_description.md) · [Step 3](./dm_conversation_tabbar_step3_development_plan.md) · [Step 4](./dm_conversation_tabbar_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/dm_conversation_tabbar`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

Cycle estimate: 0.30

## Original Query

Please one shot changes you can and write step 1 for next Railspike size task

(Context: the remaining item from the approved DM page checklist of 2026-11-10 is "Hide or slim down the bottom nav on DM pages, or merge it with the composer. Right now the composer, nav, and Safari's bars together leave a thin strip for the conversation.")

## Understood Intent

On a phone, a conversation should give most of the screen to the messages, the way chat apps do, without making it hard to leave the conversation or reach the rest of the site.

## Problem

In a conversation the phone tab bar is effectively always shown. `tab_bar_scroll.js` hides it while scrolling down but always shows it within 48px of the page's bottom, and a conversation opens and stays at the bottom. With the composer above it and Safari's bars below, the bar takes about 56px of a 667px screen on every conversation.

Concepts touched: [Navigation slots](../../concepts/navigation_slots.md) (the bar's contents, used unchanged in every option). No new concept.

## Option A — No tab bar inside a conversation
**Estimate: 0.30** — driver: a page-level opt-out in the shell plus checks across themes; uncertainty: how many layout rules assume the bar's offset.

Conversation pages render without the phone tab bar. The header's back arrow returns to Messages, as in iMessage or WhatsApp. The composer sits at the bottom edge. Desktop is unchanged.

- Gives the conversation the most room, with no motion.
- Matches chat-app habits; the back arrow already exists.
- Leaving for another part of the site takes two taps (back, then the tab) instead of one.

## Option B — Bar hides at the newest message, returns on scroll up
**Estimate: 0.35** — driver: a per-page mode in `tab_bar_scroll.js` and the composer's offset following it; uncertainty: iOS bounce and keyboard interplay at the bottom edge.

Reuse the existing scroll-hide. On conversation pages the rule near the bottom is inverted: the bar stays away while the newest message is in view and slides back when you scroll up to read older messages.

- One-tap navigation is still a short scroll away.
- Reuses the existing script, CSS offset and transition.
- The bar moving in and out at the bottom edge can feel jumpy, and on iPhone the bottom bounce is exactly where that rule is decided.

## Option C — Slimmer bar on conversation pages
**Estimate: 0.20** — driver: CSS for an icon-only, shorter bar on conversation pages; uncertainty: tap-target size and the icons in every theme.

Keep the bar always shown but drop labels and shrink it on conversation pages, saving roughly half its height.

- Navigation stays one tap away and nothing moves.
- Smallest change, CSS only.
- Saves the least space, and makes the bar look different on one kind of page.

## Recommendation

Option A. A conversation is a focused place you enter and leave, and the back arrow already provides the way out; it gives back the full bar height with no motion to tune. If one-tap access to other sections from inside a conversation matters more than space, Option B is the fallback.

Enhancement requests: none.

## Study snapshots

- Intake (after code inspection; no pristine intake): 0.30 projected total for the recommended scope, including verification across themes and iPhone acceptance. Baseline `1936c60f3b7623fea533bff1e0286869bbe61715`, scope v1, R1, P2; Claude single exposed rater, 2026-11-14 UTC.
- Step 1 handoff: 0.30 reaffirmed for Option A. R1 hashes not re-verified in this session.

*— Claude · 2026-11-14 UTC · Model: Claude Opus 5.5*
