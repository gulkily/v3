> **Feature plan:** [Step 1](./lcd_color_choices_step1_solution_assessment.md) · [Step 2](./lcd_color_choices_step2_feature_description.md) · [Step 3](./lcd_color_choices_step3_development_plan.md) · [Step 4](./lcd_color_choices_step4_implementation_summary.md)

Debug mode: enabled

# LCD color choices

## Original Query

Let's work on Feature 06, but Railspike it.

## Understood Intent

Explore a few color choices for the LCD theme, as requested in [Feature 06](../zenmemes_meta_feedback_2026-10-21_summary.md) and the [original comment](https://zenmemes.com/posts/reply-20261021181124-47b0956c) ([parent](https://zenmemes.com/posts/thread-20261021175710-9fe2b724), [thread](https://zenmemes.com/threads/thread-20261021175710-9fe2b724)).

## Problem

LCD currently has one green palette; users cannot choose another color while keeping its appearance and layout.

## Option A — Palette control beside the theme selector

Keep one LCD theme. When LCD is activated, show a palette control to the left of the theme selector: original Green plus muted Amber, grayish Purple, and Gray. Hide the control for other themes.

- Pro: Makes LCD colors directly discoverable while keeping the theme list compact.
- Con: Needs room beside the theme selector on phones, an accessible interaction, and a remembered palette preference.

## Option B — Separate LCD theme entries

Offer LCD Green, LCD Amber, LCD Purple, and LCD Gray directly in the existing theme list.

- Pro: Every palette uses the familiar theme-selection interaction.
- Con: Adds several near-identical entries to an already long theme list and its cycling order.

## Recommendation

**Option A**, with four curated palettes including the existing Green. Place the palette control to the left of the theme selector only while LCD is active; keep the theme selector’s offsets from both the right and top edges unchanged when the control appears or hides. Use color swatches with short accessible names; add no explanatory UI copy or separate settings page. Palette names and final colors remain reviewable before implementation.

The releasable slice runs from selecting LCD and a palette through navigation/reload with that choice retained. Switching away and back preserves the LCD preference; missing or invalid preferences fall back to Green. Keep other themes, LCD layout, and existing theme-load recovery working. Verify readable text, controls, statuses, and focus on phone and desktop, including first paint, header fit, and storage-unavailable behavior.

Custom color editing and additional themes are outside this slice. The user has approved Step 2; implementation awaits review and approval of Step 3.

## Revision request

**Request:** “The palette selector should be a control that appears next to the theme selector when this theme is activated.” **Disposition:** Incorporated into Option A and the recommendation; check phone header fit. **Approval:** Step 1 revision requested; approval pending. **Outcome:** Revised assessment only; no implementation or Step 2 started.

**Request:** “Please make a mockup of this.” **Disposition:** Standalone interactive [mockup](../lcd_color_choices_mockups/index.html), separate from application code. **Approval:** Mockup requested; Step 1 approval still pending. **Outcome:** Preview adjacent controls, palette choices, and hiding the palette control outside LCD at phone/desktop widths.

**Request:** Replace Blue with a grayish Purple, and keep the theme selector fixed relative to the right and top edges by placing the palette selector on its left. **Disposition:** Incorporated into Option A and the mockup. **Approval:** Requested preview revision; Step 1 approval pending. **Outcome:** Muted Purple replaces Blue; both theme-selector offsets are checked while switching themes.

**Request:** “Make the amber a bit more muted. Approved Step 2.” **Disposition:** Muted Amber in the mockup and reconciled the existing Step 2/3 drafts with Purple and fixed selector placement. **Approval:** Step 2 approved; Step 3 awaits review. **Outcome:** Revised preview and planning documents; no application edits.
