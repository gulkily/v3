> **Feature plan:** [Step 1](./lcd_color_choices_step1_solution_assessment.md) · [Step 2](./lcd_color_choices_step2_feature_description.md) · [Step 3](./lcd_color_choices_step3_development_plan.md) · [Step 4](./lcd_color_choices_step4_implementation_summary.md)

Debug mode: enabled

# LCD color choices — Feature description

## Problem

LCD offers only a green palette. Users need a few curated color choices while keeping LCD's familiar appearance and layout.

## User stories

- As an LCD user, I want to choose a palette beside the theme selector so that I can personalize its colors immediately.
- As a returning user, I want my choice remembered so that navigation, reload, and switching themes preserve it.

## Core requirements

- Keep one LCD theme with Green (the existing default), muted Amber, grayish Purple, and Gray. Final colors remain subject to readability verification; the reviewed mockup is a design reference.
- Show a palette control to the left of the existing theme selector only when LCD is active, whether selected through the menu or cycling. Its appearance or disappearance must not change the theme selector’s distance from the right or top edge. Provide swatches, short accessible names, selected state, keyboard operation, dismissal, and predictable focus; add no explanatory copy or settings page.
- Apply selection immediately and remember it within the existing site's browser-preference boundary. Restore it through navigation, reload, and switching away and back, including initial rendering without a visible flash of the wrong LCD palette.
- Missing or invalid preferences use Green. With browser storage unavailable, selection still works for the current page without uncaught errors; future loads may return to Green. Preserve existing theme-load recovery and allow users to choose another palette or theme.
- Preserve LCD layout and other themes. All palettes must keep text, links, inputs, feedback, statuses, selected states, and focus readable and distinguishable on phone and desktop.

## Delivery scope and completion boundary

Work type: application change. Extend the shared theme selection and LCD presentation through the normal application flow, including pages that inherit a saved theme without exposing a selector. Custom colors, additional themes, and new navigation destinations are outside scope.

Entry is the existing theme selector. Completion is selecting LCD and a palette, seeing it applied across navigation and reload, and retaining it after a theme round trip. Release requires rendered phone/desktop checks, preference and stylesheet-failure recovery checks, relevant existing regressions, and recorded verification in the implementation document under `CONTRIBUTING.md`; any copy cleanup also belongs in the UI copy audit checklist.

## Risks

- **Header crowding:** the adjacent control could obscure navigation or overflow. Validate the integrated header at narrow and desktop widths before expanding coverage; retain left-hand adjacency and fixed theme-selector top/right offsets when showing or hiding the palette control.
- **Incomplete recoloring:** fixed LCD colors or shared status styles could reduce clarity. Inventory affected surfaces before Step 3, then verify every palette against representative content and feedback before release.
- **Preference/render mismatch:** initial rendering and interactive selection could disagree. Review both layout entry points before Step 3; verify reload, invalid values, blocked storage, and theme-load recovery together.

## Shared component inventory

- `templates/partials/theme_menu.php` and `public/assets/theme_toggle.js`: extend the canonical menu/cycle behavior with the adjacent LCD-only control; avoid a second theme picker.
- `templates/layout.php` and `templates/standalone_layout.php`: extend existing initial appearance restoration consistently, including surfaces without a menu.
- `public/assets/theme-lcd.css` and shared `public/assets/site.css`: reuse LCD presentation and shared header/control styling across cards, forms, navigation, and feedback. Add only the palette control needed for LCD-specific choice.

## Simple user flow

1. Select LCD through the existing menu or cycle button.
2. Open the adjacent palette control and choose Green, muted Amber, grayish Purple, or Gray.
3. See the colors change and the selected choice reflected in the control.
4. Navigate, reload, or switch away and back; the remembered LCD palette returns. If a preference is unusable, Green remains usable and another selection is available.

## Success criteria

All four palettes work with pointer and keyboard; the control appears only for LCD. Supported storage retains the choice across all flow steps. Missing/invalid preferences and unavailable storage follow the defined fallback. Phone and desktop checks show readable content, visible focus, no page overflow from the control, and accessible primary actions. Other themes and existing load recovery pass affected regression checks.

## Approval state

The user supplied `Approved Step 2` with a request for more muted Amber. This reconciled description carries forward the requested grayish Purple and left-hand control placement with stable top/right offsets. Step 3 is prepared for review; implementation and commits await `Approved Step 3`.

**Revision request:** More muted Amber. **Disposition:** Softened the mockup palette, retaining Green/Purple/Gray and selector placement. **Approval:** Included with `Approved Step 2`. **Outcome:** Updated preview and development-plan verification targets.
