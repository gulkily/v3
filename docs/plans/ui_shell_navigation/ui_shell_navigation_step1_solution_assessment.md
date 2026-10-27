> **Feature plan:** [Step 1](./ui_shell_navigation_step1_solution_assessment.md) · [Step 2](./ui_shell_navigation_step2_feature_description.md) · [Step 3](./ui_shell_navigation_step3_development_plan.md) · [Step 4](./ui_shell_navigation_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_shell_navigation` ([ledger](../effort_estimation_trial.md#cycle-v3ui_shell_navigation)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.85**

## Original Query

Begin Cycle 1

## Understood Intent

Start cycle 1 of the [UI redesign checklist](../ui_redesign/implementation_checklist.md#cycle-1-ui_shell_navigation): the app shell and navigation from the [shell mockup](../ui_redesign/mockups/shell.html). Process: full Railspike, starting with this assessment. Decided earlier: work on the default site (ZenMemes) for now.

## Problem

Today's header puts every destination (Board, About, Users, Tools, Account, Messages, Invite) and the theme controls in one row, which costs two rows on phones before any content. The redesign needs three destinations, one New post, an account menu and a phone tab bar, without breaking the other sites, the 13 themes (plus Auto) ZenMemes permits, or navigation that changes with the viewer.

Constraints found: `templates/layout.php` and `partials/nav.php` are shared by all four sites; QDB has its own navigation and MIT Rap Club hides Tools (presentation slots `navigation` and `toolsNav`); members-only mode shows Lobby, Profile and Account; Messages and Invite depend on approval; `feature_flags.js` re-renders the nav through `navigationState()`; 15 theme stylesheets and `theme-nav-icons.css` key on `.site-header`, `.nav-link` and link addresses.

## Option A — Replace the shell for every site

**Estimate: 1.20**

Rewrite the shared layout, nav and theme menu once, and update every theme stylesheet and the QDB navigation to match.

- Pro: one shell everywhere; no second code path.
- Con: changes three sites that weren't asked for; widest theme and test blast radius; above 1.0, so it would need splitting.

## Option B — New shell as a ZenMemes navigation choice

**Estimate: 0.85**

Add an `app` choice to the existing `navigation` presentation slot and select it for ZenMemes. The new shell (top bar, account menu with the rare destinations and theme swatches, phone tab bar) renders from the same viewer-aware navigation items, reusing today's class names and link addresses where they still apply so existing themes and icon rules keep working. Other sites keep `forum` or `qdb` unchanged.

- Pro: delivers the full mockup for the default site; other sites untouched; turning it off is a one-line profile change.
- Pro: follows the site-profile slot pattern already used for QDB.
- Con: two shells to maintain until the others adopt it; the 13 permitted themes still need checking against the new elements.

## Option C — Rearrange the current shell in place

**Estimate: 0.55**

Keep the current header markup; move About, Tools, Invite and the theme menu into an account menu and add a phone tab bar as an extra element.

- Pro: smallest change; least theme risk.
- Con: no single New post button or slimmer top bar; still affects all sites; leaves the mockup half done, so a second shell cycle would follow anyway.

## Recommendation

**Option B.** It is a releasable vertical slice: a ZenMemes visitor gets the new shell on every page, can reach every destination they could before (including members-only, Messages and Invite states), and the site can switch back through its profile. If Step 3 exceeds eight stages, split the phone tab bar into a follow-up cycle rather than shrinking the desktop shell.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Effort study

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Intake | 2026-10-27T03:37:00Z | 0.90 | v1 / R1 / P2, baseline `83fba59802ce` | Claude, single exposed rater; authored the mockups and checklist; no shell code inspected in this cycle before rating | Cycle 1 as scoped in the checklist |
| Step 1 handoff | 2026-10-27 | 0.85 (Option B) | v1 / R1 / P2 | Same rater, after inspecting layout, nav, theme menu, nav items, presentation slots and theme selectors | New understanding: the existing presentation slot narrows the change to one site; no scope change |

Option estimates above are separate alternatives and are not summed. Exact R1 hashes were verified at intake.
