> **Feature plan:** [Step 1](./ui_tools_launcher_step1_solution_assessment.md) · [Step 2](./ui_tools_launcher_step2_feature_description.md) · [Step 3](./ui_tools_launcher_step3_development_plan.md) · [Step 4](./ui_tools_launcher_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_tools_launcher` — [ledger](../effort_estimation_trial.md#cycle-v3ui_tools_launcher) (standing P2 enrollment)
Extended estimates: enabled

**Cycle estimate: 0.40**

## Original Query

Please write step 1 for ui_tools_launcher.

## Understood Intent

Cycle 9 of the [UI redesign checklist](../ui_redesign/implementation_checklist.md) (mockup `ui_redesign/mockups/tools.html`): tools grouped by who uses them (Your device, Reading, Community, Operator), each listed once; hooks `data-tone` per tool and `data-shape` on group headings; icon themes mapped for the launcher and subnavigation. Done when every tool is reachable once, in all themes. The checklist marked this cycle Direct; the user asked for a staged Step 1.

## Problem

/tools/ lists every tool twice (a button bar, then the same list with long descriptions), in no order a visitor would recognise, and includes Account, which is already in the main navigation.

## Concepts touched

- Tools launcher (proposed entry; changed): one registry (`ToolsPageSupport::registry()`) builds the launcher and every tool page's subnavigation.
- Theme navigation icons (proposed entry; used unchanged in A and C, extended in B): icons are keyed to each tool's link in `theme-nav-icons.css`; all 16 tools are already mapped for Word97, Chicago and Midnight.
- Member-only navigation (existing pattern from `viewer_nav_prepaint`, no entry yet; used in B only).

## Option A — One grouped launcher, same tools for everyone

**Estimate: 0.40**

The registry gains a group and a tone per tool. /tools/ shows one grouped list (the four groups, Operations under Operator) with one-line descriptions, replacing both the button bar and the long list. Account leaves Tools. Each tool page keeps its subnavigation, ordered by the same groups.

- Pros: one source for launcher and subnavigation; server HTML stays the same for everyone; existing icon mappings keep working because links don't change.
- Pros: smallest change that meets "each listed once" and adds the theme hooks.
- Cons: visitors still see Operator tools they can't open (the tool itself refuses, as today).
- Driver: launcher markup and CSS across all themes, phone and desktop. Uncertainty: low.

## Option B — A, plus hide tools the viewer can't use

**Estimate: 0.75**

As A, and groups or tools the viewer has no access to are hidden, decided in the browser before first paint (the member-only navigation pattern), so server HTML stays static.

- Pros: guests and members see only what they can use.
- Cons: needs a per-tool access rule and an operator signal the browser doesn't have today; access is spread across flags, approval and operator checks per tool.
- Cons: more roles and states to verify, and hidden tools must still refuse direct visits.
- Driver: access rules and pre-paint state for three roles. Uncertainty: medium.

## Option C — A, plus the subnavigation becomes a back link

**Estimate: 0.45**

As A, but inside a tool the button bar of every other tool is replaced by a back link to Tools, as the mockup proposes.

- Pros: removes the remaining duplicate list; tool pages get simpler, especially on phone.
- Cons: switching between operator tools takes two steps instead of one.
- Cons: the subnavigation icon mappings stop being used.
- Driver: every tool page's header. Uncertainty: low.

## Recommendation

Option A. It is a releasable slice on its own: the ordinary entry (Tools in the main navigation) leads to every tool exactly once, grouped, in every theme, with the hooks Phase 2 needs. C can be combined if switching between tools matters less than a cleaner tool page. B is better as a follow-up once operator access can be known before paint. Live counts on tiles (Outbox, User access waiting) are left out of every option; the Waiting count already has open exposure and staleness questions.

*— Claude · 2026-10-31 UTC · Model: claude-opus-5-5*

## Study snapshots

| Checkpoint | UTC | Scope | Estimate | Notes |
| --- | --- | --- | --- | --- |
| Intake | 2026-10-31T00:34:06Z | v1: checklist Cycle 9 as written | 0.40 | Recorded before source inspection; exposure: the checklist item, the redesign mockup notes summarised in earlier sessions, and the AGENTS.md icon rule. |
| Step 1 handoff | 2026-10-31 | v1 / Option A | 0.40 | Reaffirmed after inspection: links and icon mappings already exist, so the work is markup, CSS, hooks and verification. Options: A 0.40, B 0.75, C 0.45, not additive. |

Shared metadata: rater Claude (claude-opus-5-5), single exposed rater; reference R1, all three frozen hashes verified 2026-10-31; protocol P2; starting revision `41a6dbe9c`. No implementation, measurement or accepted outcome.

## Approval

Awaiting `Approved Step 1` (or a revised choice of options). No Step 2 until then.
