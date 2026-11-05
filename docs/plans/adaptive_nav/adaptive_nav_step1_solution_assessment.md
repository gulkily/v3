> **Feature plan:** [Step 1](./adaptive_nav_step1_solution_assessment.md) · [Step 2](./adaptive_nav_step2_feature_description.md) · [Step 3](./adaptive_nav_step3_development_plan.md) · [Step 4](./adaptive_nav_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/adaptive_nav`; [host ledger](../effort_estimation_trial.md#cycle-v3adaptive_nav). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.80

## Original Query

I've been thinking lately that we ought to do something about the navbar. For one thing, not all of the things that we now have under the You menu fit under the You menu. Also, the things that are not under the expandable You menu are feeling kind of crowded.

## Understood Intent

The maintainer refined the request in a voice conversation on 2026-11-05 UTC:

- Desktop looks fine. The problem there is that Search, Calendar, About and Tools hide in the You menu, which is not an obvious place to look.
- Phones are the bigger problem. The visible tabs are crammed together.
- On phones, Board is a fixed tab, and so is Calendar while event support is on.
- The other phone tabs should be one or two personal slots. A slot fills itself from what the viewer picks most often in the menu, like Office 97's adaptive menus.
- Slots must stay put. A destination earns a slot after a viewer picks it a couple of times and then keeps it. A manual pin always beats an automatic choice.
- Automatic filling matters because most people will never pin anything.

Problem: The You menu holds destinations that are not about the viewer, and the phone tab bar is too crowded to read.

Concepts touched: [theme navigation icons](../../concepts/theme_navigation_icons.md) (changed in every option: icons for new bar and menu placements); [calendar](../../concepts/calendar.md) (used unchanged: its flag decides the Calendar tab); *navigation slots* (proposed, Options B and C).

## Option A - Wider desktop bar and a fixed phone bar

**Estimate: 0.45**
Main driver: two header layouts from one list of destinations. Uncertainty: low.

Desktop shows Board, Recent, Calendar, Users, Search, About and Tools in the top bar. The You menu keeps only Account, Invite, Profile and the theme controls. Phones get a fixed bar of Board, Calendar, New post and Messages, plus a More tab. More opens a full page listing every other destination, instead of a dropdown.

- Pro: fixes desktop discoverability and phone crowding with no viewer state.
- Pro: every viewer sees the same bar, so first paint needs no new work.
- Con: the phone bar suits the maintainer's habits, not each viewer's.
- Con: Recent and Users move behind More on phones for everyone.

## Option B - Option A plus self-filling sticky slots on phones

**Estimate: 0.80**
Main driver: the slot rules and the pre-paint script. Uncertainty: medium, mostly in tuning how fast a slot fills.

Start from Option A. On phones, keep Board, Calendar (when on), New post and More fixed. Add one or two personal slots. A slot fills after a viewer picks the same destination from More a few times. A filled slot then stays unless the viewer pins or unpins it. The browser keeps this choice in local storage. The existing head script places the slots before first paint, so server HTML stays the same for everyone.

- Pro: matches the maintainer's design, including viewers who never pin.
- Pro: sticky slots avoid the muscle-memory problem that sank Office's adaptive menus.
- Con: a new viewer sees empty or default slots until the browser learns.
- Con: slots do not follow a viewer between browsers or devices.
- Con: more to test across themes, phone widths and the pre-paint path.

## Option C - Option A plus pin-only slots

**Estimate: 0.60**
Main driver: a pin control in More and the pre-paint script. Uncertainty: low.

Start from Option A. Each destination in More gets a pin control. Pinned destinations fill the personal slots; unpinned slots show a default such as Recent.

- Pro: fully predictable, and simpler than learning from use.
- Con: the maintainer expects most viewers never to pin, so most phones keep the defaults.

## Recommendation

Choose Option B. It is the only option that serves viewers who never pin.

Option A is a working vertical slice on its own. Build it first: desktop placement, the fixed phone bar and the More page. Then add the slots, with pinning before automatic filling. If the cycle looks too large at Step 3, split the slots into a follow-up cycle on the same branch.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-05T04:40:00Z, `railspike-effort` / `v3/adaptive_nav`, Step 1 handoff. Scope v1: Option B as recommended above. Starting revision `a24e16df`. Reference R1, protocol P2; frozen R1 hashes not re-verified for this checkpoint. Single exposed Claude rater, exposed to the current nav renderer, the app header, the nav icon stylesheet and the earlier shell and pre-paint plans before rating. Intake and Step 1 projected total **0.80**. Option estimates 0.45, 0.80 and 0.60 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

## Enhancement Requests

None yet.
