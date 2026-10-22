> **Feature record:** [Description](./lcd_palette_rotation_step2_feature_description.md) · [Implementation](./lcd_color_choices/lcd_color_choices_step4_implementation_summary.md#additional-enhancement-request--palette-rotation)

Debug mode: enabled

# LCD palette rotation — Feature description

Follow-up to [LCD color choices](lcd_color_choices/lcd_color_choices_step4_implementation_summary.md), for the same intended release. Intended reused branch: `feat/lcd-color-choices`, currently unmerged into `main`. Step 1 skipped: the requested interaction is clear. No effort-study selection applies.

## Problem and user story

The rotate button currently leaves LCD after one palette. As a theme user, I want rotation to visit every LCD color while the dropdown stays compact, so that I can explore colors through either existing control.

## Core requirements

- Keep exactly one LCD entry in the theme dropdown. Selecting it restores the remembered LCD palette; the adjacent palette selector remains available.
- Treat LCD palettes as consecutive rotation stops: the preceding theme → LCD Green → LCD Amber → LCD Purple → LCD Gray → the following theme. Rotation enters at Green, even if another palette was previously remembered. Directly choosing a palette changes the current stop; the next rotation continues from there.
- Remember each rotated palette using the existing site preference. Reload/navigation preserve the visible palette; leaving LCD hides its palette control. Missing/invalid preferences and blocked storage retain the existing fallback/current-page behavior.
- Keep the rotate button's accessible name and tooltip accurate for both the current appearance and next stop. Preserve other themes' order, existing equivalent-system-theme skipping, stylesheet recovery, and theme-selector placement.

## Delivery scope and completion boundary

Work type: application change. Extend existing theme rotation and its behavioral tests; no new theme entries, palette colors, navigation, or deployment. Completion runs from the normal rotate button through all four LCD colors and onward, with keyboard activation, dropdown/direct-palette interaction, persistence, and recovery verified on phone/desktop.

## Risks

- **Trapped or skipped rotation:** validate the entire sequence and direct-selection transitions before planning; keep one ordered palette source and explicit exit after Gray.
- **Stale labels or preferences:** inspect current palette synchronization before planning; verify current/next labels after every selection route, reload, and storage failure.

## Shared component inventory

Extend `public/assets/theme_toggle.js` and the canonical `ThemeRegistry::LCD_PALETTES`/theme partial contract. Reuse existing layout preference restoration and LCD styling. Extend `tests/browser/theme_cycle.cjs` and `tests/browser/lcd_palettes.mjs`; preserve the recorded unrelated composer blocked-storage baseline.

## Flow and success criteria

1. Rotate into LCD Green; successive activations show Amber, Purple, Gray, then the next theme.
2. Choose LCD from its single dropdown entry or choose a palette directly; rotation continues from the visible palette.
3. Reload/navigate and confirm the last selected palette returns.

Pass when pointer/keyboard produce that sequence, labels name the actual next stop, exactly one LCD dropdown entry remains, and persistence/recovery/phone-desktop regression checks pass without moving the theme selector.

## Request and approval

**Request:** Keep one LCD dropdown entry but rotate through all colors, in the current release. **Disposition:** Small follow-up on the same unmerged branch; completed predecessor preserved. **Approval:** User explicitly requested “Please just add it as a one-shot,” overriding staged follow-up approval gates. **Outcome:** Implemented directly on the existing branch; see the linked implementation record for verification. No Step 3 or separate staged cycle is required.
