> **Feature plan:** [Step 1](./event_feature_gating_step1_solution_assessment.md) · [Step 2](./event_feature_gating_step2_feature_description.md) · [Step 3](./event_feature_gating_step3_development_plan.md) · [Step 4](./event_feature_gating_step4_implementation_summary.md)

## Problem

The shared thread composer and shared thread rendering currently expose event support on every site. Sites need to opt in explicitly, while existing event metadata remains available if a site later enables the feature.

## User Stories

- As a site operator, I want a default-disabled site feature flag for event support so I can enable it only where events belong.
- As a member of an enabled site, I want the existing thread composer to offer optional event details so I can publish an event without learning a new flow.
- As a visitor or member of a disabled site, I want no event controls or event presentation so the ordinary forum experience is unchanged.

## Core Requirements

- Register a site-mutable `FORUM_EVENT_SUPPORT_ENABLED` feature flag in the existing feature-flags tool, defaulting to `false`.
- When disabled, hide event inputs from every non-compact use of the shared thread composer and suppress event blocks on board cards and thread pages.
- Enforce the flag on thread creation as well, so submitted event values cannot create event metadata when the site is disabled.
- When enabled, retain the current optional date, location, and link authoring and display behavior.
- Preserve existing canonical event metadata and read-model values; disabling only hides and prevents new event support, and re-enabling restores display of existing event threads.

## Delivery Scope

- **Work type:** Application change.
- The event form remains in the existing full thread composer. A separate event-creation page, event listing, calendar, RSVP flow, and changes to the canonical event format are out of scope.

## Completion Boundary

- **Normal entry:** a root-approved operator enables event support for one site, then a member creates a normal or event-bearing thread and a visitor views it.
- **End-to-end outcome:** only enabled sites expose event authoring and event blocks; default-disabled sites render and create ordinary threads without event support.
- **Needed recovery:** disabling the flag immediately returns a site to the ordinary experience without data loss; attempted event submission while disabled fails safely without a partial write.
- **Release condition:** the full test suite passes, including default-off, enabled, disabled-submission, and retained-data-after-re-enable coverage.

## Risks

- A presentation-only gate would still permit hand-crafted event submissions. Earliest validation: submit event values while disabled. Mitigation: enforce the same capability rule on the creation path.
- One shared partial or composer inclusion could be missed, leaving a site partially enabled. Earliest validation: render every full-composer and event-display surface with the flag on and off. Mitigation: enumerate those shared surfaces in Step 3 and test both states.
- Hiding stored event data could be mistaken for deleting it. Earliest validation: disable then re-enable a site with an existing event thread. Mitigation: do not mutate canonical records or read-model event values as part of flag changes.

## Shared Component Inventory

- Feature-flags registry and tool: extend the canonical site-mutable flag mechanism; no new settings surface.
- Shared full thread composer: extend its existing conditional data so `compose_thread`, board inline compose, and the paned new-thread dialog agree; the compact QDB composer remains event-free.
- Thread-creation controller and write service: extend the existing submission path to reject event fields while disabled; no new API or route.
- Shared event-block partial: extend its two existing consumers, the board thread card and root thread card, with the flag condition; no new renderer.

## Simple User Flow

1. An operator opens Feature Flags and enables event support for a chosen site.
2. A member uses the normal full thread composer and optionally provides event details.
3. Visitors see event details on that site's board card and thread page.
4. On another site, or after disabling the flag, event fields and blocks are absent; an existing event thread displays as an ordinary thread until re-enabled.

## Success Criteria

- The event-support flag is visible, site-mutable, and off by default.
- With the flag off, no supported compose or display surface exposes event UI, and event-bearing submissions are rejected.
- With the flag on, the existing event workflow works on all of its current surfaces.
- Toggling the flag never deletes or changes stored event metadata.

Waiting for "Approved Step 2" before drafting Step 3.
