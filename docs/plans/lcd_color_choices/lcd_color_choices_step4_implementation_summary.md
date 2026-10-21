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

## Stage 3 - Recovery and release verification

- Changes: Added self-contained `tests/browser/lcd_palettes.mjs` against real PHP routes. Fixed palette stacking above thread content, matched DOM tab order to left-hand placement, and selected the main popover by ID to avoid menu collisions. Failed theme downloads now retain the working theme and can retry; initial failed sheets are checked for readable rules rather than only a non-null reference. No unrelated identity code was changed.
- Verification — passed: `node tests/browser/lcd_palettes.mjs` in Chromium, 390px/1280px: all four palettes, saved/invalid/missing preferences, namespace isolation, reload/navigation/round trips, menu/cycle entry, keyboard navigation/Escape/Tab/outside dismissal, unchanged theme selector anchors, expanded reply composer, no overflow, other permitted themes, standalone Forte light scheme, first paint, storage reads/writes throwing, failed and delayed stylesheets, rapid switching, initial failure and retry. Final screenshots: `/tmp/lcd-palettes-zxeblV/{green,amber,purple,gray}-{390,1280}.png`; inspected phone Purple and desktop Amber popovers, in addition to Stage 2 surface captures.
- Verification — passed: `node tests/browser/theme_cycle.cjs`; `./v3 test ThemeRegistryTest ProfileThemePresentationTest SiteProfileRegistryTest LocalAppSmokeTest::testLayoutUsesOnlyAValidatedThemeHintForTheInitialStylesheet LocalAppSmokeTest::testThemeToggleWarmsAlternateThemeStylesheetsAtLowPriority` (25/25); PHP/JavaScript syntax and `git diff --check`.
- Known baseline failure / follow-up: With all browser storage reads blocked, board composer initialization throws from `browser_signing.js:1594` (`currentAuthorIdentityId`). Reproduced independently on pre-feature `c10c305b` in a detached temporary checkout with the same stack and unchanged asset hash. The palette remains operable under this condition; the browser regression separately permits only this known composer stack (up to two events from read/both scenarios), and fails on other uncaught errors. General composer storage recovery is outside this palette cycle and remains pending.
- Notes: Stage 2 commit: `ed31277c`. No copy cleanup occurred; only essential palette names/accessibility labels were added. Deployment, schema migrations, and navigation/icon changes are inapplicable. Firefox/WebKit and the full PHP suite were not run; this is focused Chromium/theme regression evidence, not an all-browser/full-suite claim.

## Completion

- All three approved stages are complete with verification and stage-scoped commits. The four-palette flow and required palette recovery pass locally; the separately reproduced composer limitation above remains.
- Planning commit precedes the implementation summary and application edits; approved plans retain their historical content. Supporting mockups remain outside commits.
- Ready for user review on `feat/lcd-color-choices`; no merge or deployment performed.

## Stage 3 - Recovery and regression coverage

- Changes: Added the integrated `lcd_palettes.mjs` regression suite; fixed failed-stylesheet retry without applying an unavailable theme, disambiguated theme/palette popovers, aligned DOM and visual control order, restored predictable Tab focus, and kept the palette popover above page content. Stage 2 commit: `ed31277c`.
- Verification: passed `node tests/browser/lcd_palettes.mjs`, `node tests/browser/theme_cycle.cjs`, all 23 theme/profile PHP checks, the core-route smoke check, PHP/JS syntax, and diff checks. Chromium covered all four palettes at 390px/1280px, 34 contrast pairs per palette/viewport, fixed header anchors, real board/thread/Tools navigation, keyboard/Tab/Escape, selection/cycling, reload, first paint, missing/invalid/foreign preferences, throwing storage, other themes, standalone compatibility, failed/initially failed/delayed stylesheets, retry, and rapid theme changes. Inspected Purple phone and muted Amber desktop captures under `/tmp/lcd-palettes-uKgZYc`.
- Limits: focused checks, not a full-suite pass. Fully blocked storage reads reproduce two existing board-composer errors from unchanged browser-signing code; palette interaction recovers without additional errors. Physical phones and production were not checked. Standalone pages retain their existing light/dark scheme. Deployment/merge remain outside this cycle.

## Completion

All three approved stages are implemented and locally verified. UI adds only the requested palette control and short palette names; no explanatory copy or settings page. Source-feedback status and UI audit updated. Stage commits accompany their summaries; the final diagnostic report follows in a separate log-only handoff commit. Supporting mockups remain untracked as specified by the approved plan.
