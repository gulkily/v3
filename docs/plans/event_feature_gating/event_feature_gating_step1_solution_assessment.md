> **Feature plan:** [Step 1](./event_feature_gating_step1_solution_assessment.md) · [Step 2](./event_feature_gating_step2_feature_description.md) · [Step 3](./event_feature_gating_step3_development_plan.md) · [Step 4](./event_feature_gating_step4_implementation_summary.md)

## Original Query

We recently added an event feature, and it seems to now be available on all sites, which was not the original intent. Let's write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md with these goals:

- Place the event support feature behind a feature flag. Feature flag should be off by default.
- Consider putting the event creation form on a separate page (let's discuss).

## Understood Intent

Limit event authoring and presentation to sites that deliberately enable it, without changing or discarding existing event metadata. Decide whether the feature should remain part of normal thread composition or have a focused entry point before planning implementation.

## Problem Statement

Event fields and event-block rendering were added to shared thread paths, making the capability visible across every site instead of opt-in per site.

## Solution Options

**Option A: Gate the existing composer fields and event rendering with one site-mutable, default-off event-support flag.** Enabled sites continue to create and display event threads from the normal full thread composer; disabled sites expose neither the fields nor the event block.

- Pros: smallest vertical slice; matches the established site feature-flag system; preserves one familiar compose flow; existing event records remain intact if a site is disabled.
- Cons: event details still share the general thread form, which may be visually noisy for sites that use events heavily.

**Option B: Use the same default-off flag, plus a dedicated event-creation page.** Enabled sites get a distinct route and form for event threads, while ordinary thread composition remains event-free.

- Pros: clear author intent; keeps the ordinary composer focused; creates a natural future home for event-specific guidance or validation.
- Cons: adds routing, navigation, form reuse/state-recovery, and a choice between two authoring paths; premature if date, location, and link are the only differences.

**Option C: Build a dedicated events experience or listing now.** Put creation and discovery behind event-specific routes and pages rather than the generic forum flow.

- Pros: strongest events-focused experience and a base for future calendar or RSVP work.
- Cons: materially expands the request beyond containment; duplicates or restructures the current thread experience before there is evidence it is needed.

## Recommendation

**Option A.** Add one site-mutable `FORUM_EVENT_SUPPORT_ENABLED` flag, defaulting to off, and gate both event authoring and display so disabled sites behave as though the feature is unavailable. Preserve stored event data so enabling the flag later restores it without migration.

Keep the creation form in the existing full thread composer for the first slice. A separate page becomes justified if enabled sites need event-first navigation, materially different fields/workflow, or a way to prevent ordinary threads from being mistaken for events; none is established yet. If you prefer that separation now, Option B is compatible with the same flag but should be an explicit scope choice for Step 2.

**Vertical-slice viability:** Yes. A root-approved operator enables the flag for one site; members there can author and visitors can see event details, while every default-disabled site has no event controls or event presentation. Disabling the flag restores the ordinary-thread experience without modifying canonical records.

Waiting for "Approved Step 1" before drafting Step 2.
