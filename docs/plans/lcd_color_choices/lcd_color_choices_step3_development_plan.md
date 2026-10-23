> **Feature plan:** [Step 1](./lcd_color_choices_step1_solution_assessment.md) · [Step 2](./lcd_color_choices_step2_feature_description.md) · [Step 3](./lcd_color_choices_step3_development_plan.md) · [Step 4](./lcd_color_choices_step4_implementation_summary.md)

Debug mode: enabled

# LCD color choices — Development plan

## Completion Contract

- Application change following `Approved Step 2`: select LCD, choose Green/muted Amber/grayish Purple/Gray to the left of the theme selector, and retain the palette across navigation, reload, and theme round trips within the site's preference boundary.
- Show the palette control only for LCD without changing the theme selector's top/right offsets. Invalid preferences fall back to Green; blocked storage permits current-page selection. Preserve theme-load recovery, other themes, LCD layout, and standalone light/dark behavior.
- Release requires integrated phone/desktop checks, accessible controls, readable palettes, stable first paint, and relevant regressions recorded in Step 4. No database/API change, new navigation, or deployment. Record copy cleanup in the UI copy checklist if applicable.

## Key Risks

- **High risk:** Header crowding or focus errors obstruct navigation. Validate the integrated header first; resolve placement, keyboard operation, and overflow before adding palettes.
- **High risk:** Fixed LCD colors obscure text/status distinctions. Inventory overrides early; verify contrast before accepting each palette.
- Preference restoration may disagree with interactive state. Review both layouts and namespace rules first; preserve standalone's scheme-only behavior and exercise load/storage failures.

## Stage 1

- Goal: Complete Green/muted Amber selection, persistence, and reload.
- Dependencies: Approved Step 3, feature branch, organized plans, and planning commit.
- Expected changes: Extend canonical theme controls with the left-hand LCD-only selector, selected state, keyboard/dismissal/focus behavior, and namespaced restoration before paint.
- Verification approach: Pointer/keyboard, navigation/reload, theme cycling/round trip, first paint, and fixed top/right offsets at 390px/1280px; existing theme-cycle and PHP theme/profile checks.
- Risks or open questions: Broad button rules or popover handlers may conflict. Exercise both controls immediately and resolve header/restoration failures before Stage 2.
- Canonical components/API contracts touched: Theme partial/controller, LCD/shared styles, main layout, renderer/browser preference boundary as needed; review standalone compatibility.

## Stage 2

- Goal: Complete Purple/Gray across content and feedback.
- Dependencies: Stage 1 verified, summary updated, and committed.
- Expected changes: Extend the same selector and LCD presentation; preserve Green and standalone behavior.
- Verification approach: Four palettes on phone/desktop with board/thread content, forms, long values, expanded controls, and success/error/warning states. Target 4.5:1 normal-text contrast and 3:1 relevant controls/focus; rerun affected theme checks.
- Risks or open questions: Green literals can defeat recoloring. Audit computed styles early, adjust curated colors, and recheck affected surfaces before Stage 3.
- Canonical components/API contracts touched: LCD stylesheet and shared palette control; existing feedback consumers.

## Stage 3

- Goal: Verify recovery and complete release evidence.
- Dependencies: Stage 2 verified, summary updated, and committed.
- Expected changes: Add focused behavioral regressions, correct in-scope failures, and record results/limitations.
- Verification approach: Missing/invalid preferences, throwing storage, namespace isolation, failed/delayed stylesheet loading/retry, rapid switching, reload first paint, and selector-free pages. Run theme-cycle, ThemeRegistryTest, ProfileThemePresentationTest, SiteProfileRegistryTest, and affected smoke checks; inspect other themes.
- Risks or open questions: Source assertions miss rendering/recovery failures. Inject failures through real routes early; require rendered evidence and report omissions explicitly.
- Canonical components/API contracts touched: Existing browser/PHP theme tests, layouts/controller as needed, and Step 4 summary.

## Approval state

Awaiting `Approved Step 3`; no application edits or commits. Debug mode remains enabled; not an effort-study cycle. Target at most one hour per stage; split oversized work before starting it. After approval, organize approved plans/log under `docs/plans/lcd_color_choices/`, repair links/index, and commit on the feature branch before creating Step 4. Mockups remain supporting material outside that commit unless separately authorized.
