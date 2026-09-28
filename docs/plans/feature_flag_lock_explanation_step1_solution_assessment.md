# Step 1: Solution Assessment — Feature Flag Lock Explanation

## Problem Statement
The `/tools/feature-flags/` page hides *why* a flag is locked in a hover-only tooltip, and conflates that data-source lock with a separate, unshown root-approval permission gate — so a non-root approved user sees an apparently-editable toggle and only learns they lack permission after submitting and getting a 403.

## Option A — Visible tooltip text only
- Convert the hover-only `title` attribute on the `badge-locked` span into visible caption text using the existing `lockReason()` values.
- Pros: trivial, template-only change; no risk to `FeatureFlagState`/evaluator.
- Cons: does not address the root-approval gap — a non-root approved viewer still sees a live-looking toggle and hits a surprise 403.

## Option B — Unify permission into the lock model
- Extend `FeatureFlagState`/`FeatureFlagEvaluator` with a new "permission-locked" reason, computed from `viewerCanManageFeatureFlags()`, so `isLocked()`/`lockReason()` become the single source of truth for every disabled state.
- Pros: cleanest long-term model; one code path for all "why can't I change this" logic.
- Cons: touches core evaluator/state classes shared by the write path and API, larger surface area and risk for a page-explanation fix.

## Option C — Controller/template-level permission check (no core model change)
- Pass `viewerCanManageFeatureFlags()` result from `ToolsPageController` into the template; when false, render mutable-but-permission-blocked flags as disabled with visible text ("Read-only — requires root-approved identity"), independent of `lockReason()`.
- Also make the existing lock-reason tooltip visible as inline text (same as Option A).
- Pros: fixes both real gaps (hidden lock reason + misleading enabled toggle) without modifying `FeatureFlagState`/evaluator; changes stay isolated to the controller and template that already own page rendering.
- Cons: two separate "why is this disabled" checks remain in the template rather than one unified model (acceptable given they're conceptually different: data-source lock vs. viewer permission).

## Recommendation
**Option C.** It closes both gaps identified in the investigation (invisible lock reason, misleading enabled toggle before a permission 403) with a small, low-risk, template/controller-only change, consistent with the process's preference for minimal-footprint fixes and avoiding changes to shared core logic (`FeatureFlagState`/evaluator) that other write paths depend on.
