> **Feature plan:** [Step 1](./nav_links_load_stability_step1_solution_assessment.md) · [Step 2](./nav_links_load_stability_step2_feature_description.md) · [Step 3](./nav_links_load_stability_step3_development_plan.md) · [Step 4](./nav_links_load_stability_step4_implementation_summary.md)

Debug mode: enabled

# Step 2: Feature Description — Stable Messages/Invite Nav Buttons (Revised)

## Revised direction
Stage 1 found no script that inserts the nav buttons after load. The observed jank is the Messages button starting with a `…` placeholder (wider) and shrinking to `Messages`, which shifts Invite. Revised scope: drop the visible count/placeholder text, signal new messages with an outline highlight plus ARIA, and never change the button's size or caption. This supersedes the earlier pre-render/cross-tab fallback scope; a richer unread presentation is deferred to a later brainstorm.

## Problem
The Messages nav button changes width after load because its unread-count text arrives late, moving the Invite button.

## User Stories
- As a signed-in member, I want the Messages button to keep its size from first paint so that the nav doesn't jump.
- As a member with new messages, I want the Messages button outlined so that I notice them without any layout change.
- As a screen-reader user, I want the unread state announced through ARIA so that I get the same information.

## Core Requirements
- Messages caption is always exactly `Messages`; no visible `…`, count, or `?`.
- Outline highlight (no change to size, padding, or border box) when unread > 0; removed when 0 or unavailable.
- ARIA conveys loading/unread/unavailable state (accessible name or description updated; no visible text).
- Unread polling, data attributes, messaging-disabled hiding, and Invite behavior are unchanged.

## Delivery Scope
- Work type: application change (no schema changes).
- Completion boundary: eligible viewer loads any page → Messages and Invite do not move at any point; unread state shows as outline + ARIA; releasable once verified in a browser and browser tests updated.

## Risks
- Outline clashes with theme button styling (Word 97/Chicago/Win-dark bevels) — impact: invisible or ugly highlight. Validate per theme early. Mitigation: use an outline-style highlight that doesn't affect layout, tuned per theme only where needed.
- Scripts assume the count/status elements exist — impact: runtime errors in unread polling and the flag-toggle error path. Validate with existing browser tests. Mitigation: update scripts to set state via attributes/ARIA only.
- Existing tests assert the visible `…`/`?` text — impact: failing tests. Mitigation: update to assert attributes/ARIA.

## Shared Component Inventory (extend, don't fork)
- `templates/partials/nav.php` — canonical Messages markup; extend.
- `private_message_unread.js` — owns unread state; extend to drive outline/ARIA state.
- `feature_flags.js` — error path touching the count element; adapt.
- `site.css`, `theme-nav-icons.css` — nav link styling; add highlight state.
- Browser tests: `private_message_unread_ui`, `private_message_live_navigation`, `private_message_seen_*`.

## Simple User Flow
1. Page loads; Messages and Invite render at final size.
2. Unread check completes.
3. If unread > 0, Messages gets an outline and updated ARIA; nothing moves.

## Success Criteria
- Zero layout shift of nav items before/after unread check (measured bounding boxes).
- Outline appears only when unread > 0, in every theme.
- ARIA state reflects loading/unread/unavailable.
- Existing messaging tests pass after updates.

**Approval:** Reply `Approved Step 2` to confirm the revision.
