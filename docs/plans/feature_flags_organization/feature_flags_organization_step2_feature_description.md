> **Feature plan:** [Step 1](./feature_flags_organization_step1_solution_assessment.md) · [Step 2](./feature_flags_organization_step2_feature_description.md) · [Step 3](./feature_flags_organization_step3_development_plan.md) · [Step 4](./feature_flags_organization_step4_implementation_summary.md)

# Step 2: Feature Description — Feature Flags Page Organization

## Problem

The feature-flags page groups seven unrelated settings under “Forum” because it follows technical key prefixes. Operators cannot scan settings by the capability they intend to manage.

## User Stories

- As a site operator, I want access and identity controls together so that I can manage site admission without scanning unrelated settings.
- As a site operator, I want authored-content, forum-experience, and rendering controls in distinct groups so that I can find the setting matching the outcome I want.
- As a site operator, I want existing specialist groups to remain recognizable so that established workflows do not need to be relearned.

## Core Requirements

- Replace the overloaded Forum grouping with Access and identity, Authored content, Forum experience, and Site rendering groups.
- Keep Agent replies, LLM exchanges, and Fastmod as separate existing groups.
- Grouping is display-only: flag evaluation, defaults, dependencies, mutability, save behavior, and endpoints remain unchanged.
- Preserve every current flag, its existing row content, and its relative order within its new group.

## Delivery Scope

- Work type: application change.
- Scope is limited to the operator-facing grouping metadata and its presentation on `/tools/feature-flags/`.

## Completion Boundary

- Normal entry: an operator opens `/tools/feature-flags/`.
- End-to-end outcome: each flag appears once in its logical group, with all existing state, controls, and save behavior intact.
- Recovery: an operator can continue managing every flag through its existing control if a group label is unfamiliar; no flag state or configuration data changes.
- Release condition: automated feature-flag coverage passes and a rendered page confirms the intended groups and unchanged controls.

## Risks

- **Unclear taxonomy:** a setting may plausibly fit two groups. Validate the proposed memberships against each flag's operator-facing description before Step 3; use its primary operator outcome as the boundary.
- **Behavioral coupling:** display grouping could inadvertently affect evaluation behavior. Validate existing evaluator coverage early; keep grouping distinct from evaluation categories and dependencies.
- **Presentation regression:** a regrouped flag could be omitted or duplicated. Validate the rendered page and assert that every registered flag appears exactly once.

## Shared Component Inventory

- `templates/pages/feature_flags.php` is the sole operator UI rendering the grouped flag list; extend this canonical surface rather than adding a second listing.
- `FeatureFlagRegistry`/`FeatureFlagDefinition` supply the page's existing display grouping; extend that display metadata without changing their evaluation-facing category or dependency information.
- `public/assets/feature_flags.js` and `/api/set_feature_flag` serve the existing controls; reuse them unchanged because grouping does not alter saving.
- No other UI renders the grouped feature-flag list.

## Simple User Flow

1. The operator opens `/tools/feature-flags/`.
2. They identify the capability they want to manage from a logical group heading.
3. They locate the flag in that group and use its existing control.
4. The existing save feedback confirms the change.

## Success Criteria

- The page displays the seven agreed groups: Access and identity, Authored content, Forum experience, Site rendering, Agent replies, LLM exchanges, and Fastmod.
- All 14 registered flags render exactly once in the intended group.
- Existing dependency, lock, override, and toggle behavior remains unchanged.
- Existing feature-flag tests pass, with coverage for the new group memberships and rendered headings.
