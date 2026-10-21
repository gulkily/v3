> **Feature plan:** [Step 2](./same_author_collapse_toggle_step2_feature_description.md) · [Step 3](./same_author_collapse_toggle_step3_development_plan.md) · Step 4 pending

Debug mode: enabled

# Same-author collapse toggle

## Problem
Thread views automatically merge consecutive quick replies from the same author. Operators cannot disable this presentation when individual post boundaries are preferable.

## User stories
- As an operator, I want to toggle same-author collapsing so that I can choose how posts appear across the site.
- As a reader, I want disabled collapsing to show each post's identity and actions so that I can follow individual contributions.

## Core requirements
- Add “Collapse posts from same author” to the existing site-wide Feature Flags controls, using their existing permissions, persistence, attribution, and reset behavior.
- Default to enabled, preserving the current continuation window and author eligibility rules.
- When disabled, render each post separately with its normal metadata and actions, including the root post; ensure reply counts match the uncollapsed presentation.
- Re-enabling restores current grouping without changing post content, identity, order, or stored records.

## Delivery scope and completion boundary
Application change. Ordinary entry is Tools → Feature Flags, followed by opening or reloading a thread. Complete when an authorized operator can persist either state, readers see the corresponding presentation, and reset restores the enabled default. Existing save-failure feedback must let the operator retry and verify the saved state after reload. Browser verification covers phone and desktop, affected themes, root/reply continuation runs, and shared post actions. Record verification and limitations in the implementation summary and UI audit checklist.

## Risks
- Grouping also controls metadata placement and reply counts: inspect these dependencies before planning and verify both states with a root continuation and a later continuation run.
- Site-wide versus personal scope could differ from user intent: this proposal explicitly chooses the existing operator Feature Flags surface; confirm this boundary during review.
- Theme-specific grouping styles may leave visual gaps: verify rendered collapsed and separate posts in affected themes before release.

## Shared component inventory
Reuse Tools → Feature Flags and its existing save/reset interfaces. Extend the canonical thread view and shared root/post cards; inspect alternate thread views and post fragments during planning so the toggle applies wherever continuation grouping is actually used. Existing theme styles remain shared. No new navigation destination is required.

## Simple user flow
1. An authorized operator opens Tools → Feature Flags and disables the toggle.
2. Readers open or reload a thread and see individual posts.
3. The operator enables or resets the toggle, then reloads to verify grouping returns.

## Success criteria
Both states survive reload; unauthorized users cannot change the setting; default/reset retain current grouping; separate posts expose their metadata and actions; rendered phone/desktop flows have no new overflow or inaccessible controls.
