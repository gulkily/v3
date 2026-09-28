# Step 2: Feature Description — Feature Flag Lock Explanation

## Problem
The feature flags page doesn't explain why a flag is locked (reason is hidden in a hover-only tooltip) and shows mutable flags as apparently editable to viewers who actually lack permission to change them, so they only discover the restriction after a failed submit.

## User Stories
- As a site operator viewing `/tools/feature-flags/`, I want to see *why* a locked flag is locked without hovering, so I understand what to do next without reading source code.
- As an approved-but-non-root viewer, I want the page to show me upfront that I can't change mutable flags, so I don't attempt a toggle that fails with a 403.
- As a root-approved operator, I want the page to look unchanged for flags I *can* actually edit, so the new messaging doesn't get in my way.

## Core Requirements
- Every locked flag (environment / private-config / not-site-mutable) shows its lock reason as visible text, not just a tooltip.
- Mutable flags (`siteMutable: true`) are rendered as disabled with visible explanatory text when the current viewer is not root-approved, instead of appearing live.
- The permission check reuses the existing `viewerCanManageFeatureFlags()` logic — no new permission model.
- No change to `FeatureFlagState`/`FeatureFlagEvaluator` or the write/API path; the fix is confined to what the page renders.
- Root-approved viewers see no behavior change for flags they can edit.

## Shared Component Inventory
- `templates/pages/feature_flags.php` — the only page rendering flag state; canonical surface, will be extended (not forked).
- `src/ForumRewrite/Http/ToolsPageController.php` — supplies `viewerCanManageFeatureFlags()` already for submit-time gating; will be extended to also pass this to the read-time render.
- `public/assets/feature_flags.js` — handles toggle submission/error display; no changes anticipated (still surfaces the 403 message as today, just less likely to be hit).
- No other page or API renders feature flag state, so there is no duplicate surface to reconcile.

## Simple User Flow
1. Viewer opens `/tools/feature-flags/`.
2. Controller resolves each flag's lock state (existing) and now also resolves the viewer's `canManageFeatureFlags` capability (existing check, newly passed to the template).
3. Template renders, per flag:
   - If locked (env/private-config/not-mutable): visible lock-reason text next to the badge.
   - Else if mutable but viewer lacks permission: toggle disabled, visible "read-only — requires root-approved identity" text.
   - Else: toggle enabled as today.
4. Root-approved viewer toggles a mutable flag as today; no behavior change.

## Success Criteria
- A non-technical operator can identify why any given flag is locked by reading the page, with no hover interaction required.
- A non-root approved viewer sees a disabled toggle with an explanation before attempting to submit, and no longer encounters the 403 as their first signal.
- No regression in the existing toggle flow for root-approved viewers (manual verification against current behavior).
