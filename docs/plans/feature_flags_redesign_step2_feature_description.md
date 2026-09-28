# Step 2: Feature Description — Feature Flags Page Redesign

## Problem
The `/tools/feature-flags/` page's fixed-width table wraps flag keys mid-word and shows four always-visible columns per flag, which doesn't scale as the flag count grows and buries the handful of flags that actually need attention.

**Scope note:** search/filter (toolbar with search input and status-filter chips) is deferred to a follow-up feature — it splits cleanly since it only matters once the flag count grows, and doesn't affect this feature's layout, badges, grouping, or dependency work.

## User Stories
- As a site operator, I want flag keys and names to stay readable at any window width so I can scan the page without keys wrapping mid-word.
- As a site operator, I want to see only what deviates from normal (overridden, locked, unmet dependency) so I don't have to parse every column of every row to find what matters.
- As a site operator, I want to see when a flag is inactive because a flag it depends on is off, so I understand why toggling it had no visible effect.
- As a site operator, I want to toggle a flag or reset it to default with one action and get immediate feedback, so I don't need a dropdown-plus-Save round trip.

## Core Requirements
- Grouped-list layout with a switch per flag; no mid-word wrapping of names or keys at any width or theme.
- Remove the always-visible Effective/Default/Source/Mutable columns; show overridden/locked/dependency state only as badges when relevant.
- Dependency awareness for known parent/child flag pairs, with a visible "inactive — requires …" state when the parent is off.
- Preserve progressive enhancement (page works without JS) and existing `data-flag-key` / `data-role="feature-flag-*"` hooks so current tests keep working or are updated in place, not replaced.

## Shared Component Inventory
- `templates/pages/feature_flags.php` — the only template rendering this data. **Reused/extended in place**, not replaced.
- `public/assets/feature_flags.js` — the only JS driving the save flow. **Reused/extended in place** (rewritten internally for switch semantics, same file and hooks).
- `src/ForumRewrite/Support/FeatureFlags/{FeatureFlagRegistry,FeatureFlagDefinition,FeatureFlagEvaluator,FeatureFlagState}.php` — existing backend data/evaluation layer. **Extended**: add a display `group`/label, extend `requiresEnabledFlag` to two more pairs. No new data layer.
- `/api/set_feature_flag` (via `ToolsPageController`) — existing save endpoint and response format. **Reused as-is**, no new endpoint.
- Site-wide CSS tokens/components (`--status-*`, `--active-*`, `--ink-soft`, `.card`, `.meta`, `.nav-link`) in the shared stylesheet. New switch/badge/chip styles are **added to this same stylesheet**, built from existing tokens — not a separate page-specific stylesheet.
- No other page or API currently renders feature-flag state (only this template, this JS file, and the flag tests reference the `data-feature-flag-*` hooks), so there is no other canonical surface to consolidate with.

## Simple User Flow
1. Operator opens `/tools/feature-flags/`.
2. Page shows a summary line (counts) and a grouped list of flags, each with a switch.
3. Operator clicks a switch to toggle a mutable flag; the row updates in place with inline save feedback.
4. For an overridden flag, operator can click "reset to default" to revert it.
5. For a locked or dependency-blocked flag, operator sees why via a badge/tooltip without needing to change anything.

## Success Criteria
- Flag names and keys never wrap mid-word between 375px and 1150px, checked in at least light, dark, and one alternate theme.
- Page stays scannable with 50+ flags (verified with a temporary test fixture) via grouping alone (search/filter is deferred, see Scope note).
- All existing feature-flag tests pass (updated selectors where needed); new tests cover override/lock/dependency rendering and the no-JS POST path.
- No flag row shows always-visible metadata beyond name/description/key/switch; badges appear only when a flag deviates from default, is locked, or has an unmet dependency.
