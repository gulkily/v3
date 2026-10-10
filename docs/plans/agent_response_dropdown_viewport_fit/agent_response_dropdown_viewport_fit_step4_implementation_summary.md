> **Feature plan:** [Step 1](./agent_response_dropdown_viewport_fit_step1_solution_assessment.md) · [Step 2](./agent_response_dropdown_viewport_fit_step2_feature_description.md) · [Step 3](./agent_response_dropdown_viewport_fit_step3_development_plan.md) · [Step 4](./agent_response_dropdown_viewport_fit_step4_implementation_summary.md)

# Agent Response Dropdown Viewport Fit – Implementation Summary

## Stage 1 - Side selection and available-space sizing
- Changes:
  - `positionAgentResponseModeMenu` in `public/assets/post_analysis.js` now compares space above and below the button, preferring below, and sets the menu max-height to the space on the chosen side (minimum 120px), keeping internal scrolling.
  - Top is clamped inside the viewport; the inline max-height overrides the static CSS cap.
- Verification:
  - `node --check public/assets/post_analysis.js` passes.
  - Manual browser check not yet run in this environment; to be covered in Stage 3.
- Notes:
  - No CSS change was needed; the inline style takes precedence over the static rule.

## Stage 2 - Scroll/resize anchoring and auto-close
- Changes:
  - Added `syncAgentResponseModeMenu` in `public/assets/post_analysis.js`: repositions the open menu and closes it when the trigger is fully outside the viewport.
  - Scroll (capture, so nested scroll containers count) and resize listeners are added on open and removed in `closeAgentResponseModeMenu`; `addEventListener` with the same function reference prevents stacking.
- Verification:
  - `node --check public/assets/post_analysis.js` passes.
  - Manual browser scroll/resize check not yet run in this environment; to be covered in Stage 3.
- Notes:
  - Existing close paths (Escape, outside click, close button, re-click) all go through the same close function, so listeners are always removed.

## Stage 3 - Cross-viewport verification and regression guard
- Changes:
  - None; no theme-specific margin or z-index tweak was identified, so the shared placement rule is unchanged.
- Verification:
  - Placement math simulated in Node for four cases: room below, near the bottom (flips above), short viewport with a tall menu, and a very short viewport. All stay within an 8px gutter; the menu scrolls internally and never drops below the 120px minimum.
  - `php tests/LocalAppSmokeTest.php` and `php tests/WriteApiSmokeTest.php` (both reference `post_analysis.js`) exit 0.
  - Not run: manual in-browser check of scroll-follow, auto-close and each shipped theme (no browser available in this environment).
- Notes:
  - Fixed headers in a theme could still overlap the menu; this needs a manual theme pass by a reviewer before release.
