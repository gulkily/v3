> **Feature plan:** [Step 2](./feature_flags_toolbar_step2_feature_description.md) · [Step 3](./feature_flags_toolbar_step3_development_plan.md) · [Step 4](./feature_flags_toolbar_step4_implementation_summary.md)

Debug mode: enabled

# Feature flags search and status toolbar

## Problem
The grouped `/tools/feature-flags/` page still requires scanning every flag to find a setting or inspect enabled, overridden, or locked flags. This delivers the toolbar deferred by the feature flags redesign Steps 2–3 (the existing folder is `feature_flags_redesign/`).

## User stories
- As a site operator, I want to search flag names, keys, and descriptions so that I can quickly find a setting.
- As a site operator, I want to filter by status so that I can inspect enabled flags, deviations from defaults, or locked settings.

## Core requirements
- Provide a sticky toolbar with a labeled search input and single-select All, Enabled, Overridden, and Locked chips with counts. Default to All and an empty search; match trimmed text case-insensitively across name, key, and description, combined with the selected status. Counts reflect search matches for each status before applying the selected chip.
- Reuse current semantics: Enabled means effective value is true; Overridden means effective value differs from its declared default; Locked means the existing locked state, rather than viewer permission. Statuses may overlap.
- Hide groups with no matching rows and show “No flags match.” when there are no results. Clearing search and selecting All restores every row. Successful toggles and resets immediately refresh results and counts; failed saves preserve the existing state and feedback.
- Make controls keyboard accessible with visible focus and an announced selected status and result count. `/` focuses search except while editing text or using modifier shortcuts. Keep focused save controls recoverable if a saved row leaves the results.
- Support narrow screens and existing themes using shared styles. Without JavaScript, retain the complete grouped list and existing save behavior; do not present inactive filtering controls.

## Delivery scope and completion boundary
Work type: application change. Extend the existing feature flags page and assets, add targeted verification, and remove the completed todo entry after implementation. From the normal tools link, operators can find and filter flags, change permitted settings, see current results, and recover from an empty result or failed save. Release requires relevant existing checks and new toolbar behavior checks to pass.

## Risks
- Save updates can leave stale matches or strand focus. Validate successful/failed saves under active filters early; refresh from confirmed row state and provide a visible focus destination when a row disappears.
- Locked and overridden can be confused with permission and source. Validate against existing state methods and badges before planning; retain their exact definitions.
- Sticky controls can obscure content or overflow. Check existing sticky navigation and narrow layouts before planning; use shared offsets/tokens and allow controls to wrap.

## Shared component inventory
- `templates/pages/feature_flags.php`: extend the canonical grouped list, row state, and error presentation.
- `public/assets/feature_flags.js`: extend existing page initialization and signed save completion behavior.
- `public/assets/tool-details.css` and existing shared theme/form/button styles: reuse tokens and extend toolbar styling.
- Feature flag registry/evaluator/state and existing prepare/finalize APIs: reuse existing state and save contracts.

## Simple user flow
1. Open Feature Flags from Tools; see all groups and toolbar counts.
2. Enter search text and/or select one status; inspect matching rows.
3. Toggle or reset an editable flag; see confirmed results and counts update, or existing error feedback.
4. Clear search and select All to return to the complete list.

## Success criteria
Search works for names, keys, and descriptions; all four chips and combined queries produce correct rows/counts; empty groups and empty results behave correctly. Save/reset success and failure retain accurate results and usable focus. Keyboard behavior, 375px and desktop layouts, light/dark/one alternate theme, and no-JavaScript fallback are verified alongside existing feature flag checks.
