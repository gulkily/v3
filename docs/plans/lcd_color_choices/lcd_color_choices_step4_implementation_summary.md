> **Feature plan:** [Step 1](./lcd_color_choices_step1_solution_assessment.md) · [Step 2](./lcd_color_choices_step2_feature_description.md) · [Step 3](./lcd_color_choices_step3_development_plan.md) · [Step 4](./lcd_color_choices_step4_implementation_summary.md)

Debug mode: enabled

# LCD color choices — Implementation summary

- Branch: `feat/lcd-color-choices`; planning commit: `c10c305b`.
- Request: user-edited Step 3 specifies muted Amber, grayish Purple instead of Blue, and left-hand placement with unchanged theme top/right offsets. Disposition: approved palette/placement refinements in the latest plan, retained over earlier proposed colors. Approval: `Approved Step 3`. Outcome: tracked in stages below; no pending scope requests.
- Effort study: not selected. Deployment, database migrations, new navigation/icon mappings: inapplicable to approved scope.
- Mockup remains untracked supporting material, outside the planning/implementation commits.

## Stage 1 - Green and muted Amber

- Changes: Green/muted Amber now selectable through a left-hand LCD-only swatch menu, with namespaced persistence and restoration before paint. Menu labels, selected state, arrow/Home/End keys, Escape, outside dismissal, and focus return use the canonical theme controls.
- Verification: passed `node tests/browser/theme_cycle.cjs` and `./v3 test ThemeRegistryTest ProfileThemePresentationTest SiteProfileRegistryTest` (23/23). Chromium at 390px/1280px passed keyboard selection, Escape/refocus, navigation to Tools, reload, theme round trip, and page overflow. Main theme menu y/right offsets exactly match the pre-change baseline (390px: y=82.765625, right=28.1875; 1280px: y=66.578125, right=266.1875). Screenshots saved under `/tmp/lcd-stage1-{390,1280}.png`; phone screenshot inspected.
- Notes: standalone layout intentionally keeps its existing scheme-only behavior. Verification caught and corrected a theme-isolation rule violation and an overly broad popover lookup collision before commit. Full palette recoloring/contrast and failure injection remain in Stages 2–3.

## Stage 2 - Purple, Gray, and readable shared surfaces

- Changes: Added grayish Purple/Gray; recolored LCD gradients, chrome, swatches, forms, feedback, and status variables. Retained Green backgrounds; darkened secondary text/borders and its warning color where measured contrast missed approved targets. Added explicit focus outlines and readable placeholders for all LCD palettes.
- Verification: passed the 23 theme/profile PHP checks, existing theme-cycle checks, syntax and whitespace checks. Chromium at 390px/1280px traversed real board/thread routes for each palette; sampled 31 text/status/control-border foreground/background pairs per palette/viewport with no contrast failures after correction (4.5:1 text, 3:1 borders). Focus and expanded feedback probes were rendered alongside actual thread content; no page overflow. Screenshots: `/tmp/lcd-stage2-{green,amber,purple,gray}-{390,1280}.png`; inspected all four phone palettes and desktop Purple.
- Notes: Probe feedback uses existing component classes with representative success/error/warning/expanded content; it is not evidence of completing a server write. Stage 1 commit: `201b472e`. Failure injection, namespace isolation, first-paint assertions, and durable browser regression coverage remain Stage 3.
