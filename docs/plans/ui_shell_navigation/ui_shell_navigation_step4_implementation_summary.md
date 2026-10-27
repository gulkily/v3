> **Feature plan:** [Step 1](./ui_shell_navigation_step1_solution_assessment.md) · [Step 2](./ui_shell_navigation_step2_feature_description.md) · [Step 3](./ui_shell_navigation_step3_development_plan.md) · [Step 4](./ui_shell_navigation_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_shell_navigation` ([ledger](../effort_estimation_trial.md#cycle-v3ui_shell_navigation)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.90**

Branch `feat/ui-shell-navigation`, cut from `ui-redesign-mockups` at `83fba5980`. Planning commit `062c19f4b`.

## Step 4 entry

- Step 3 approved 2026-10-27 ("Approved Step 3").
- Entry projection: **0.90 reaffirmed** from the Step 3 handoff; scope v1 / R1 / P2, baseline `83fba59802ce`; Claude single exposed rater; no new inspection since the Step 3 snapshot.
- Original stage-work estimates copied from the [approved plan](./ui_shell_navigation_step3_development_plan.md) (copying is not a new rating): Stage 1 0.15, Stage 2 0.12, Stage 3 0.10, Stage 4 0.20, Stage 5 0.15, Stage 6 0.15, Stage 7 0.08, Stage 8 0.10.
- Enhancement requests: none recorded.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Stage 1 - Shell switch and item placement

**Original estimate: 0.15**
**Retrospective: 0.12**

- Changes:
  - `PresentationSlotRegistry`: `navigation` slot accepts `app`.
  - `SiteProfileRegistry`: ZenMemes selects `navigation => app`; Chouse, QDB and MIT Rap Club unchanged.
  - `TemplateRenderer::navigationItems()` (new, public): wraps `navItems()` and, for `app` only, gives each item a placement: `main` (Board, Users, Messages, Lobby) or `menu` (About, Tools, Account, Invite, a members-only Profile link). Other slots return items unchanged.
  - `tests/AppNavigationPlacementTest.php`: slot selection per site; placements for guest, unapproved and approved viewers with Messages on and off; members-only placements; no placements on other sites; ZenMemes header byte-identical between `app` and `forum`.
- Verification:
  - `php tests/run.php AppNavigationPlacementTest PresentationSlotRegistryTest PrivateMessageFeatureFlagTest FeatureFlagChangePageTest InvitationIssuanceTest QuoteCardDisplayNumberTest ProfilePresentationContentTest`: 46 run, 46 passed.
  - UI checks not applicable: rendered output is unchanged by design in this stage.
- Notes:
  - Sequencing adjustment within the approved plan: the layout does not yet pick a shell partial by slot. Switching the rendering before the account menu exists (Stage 2) would hide About, Tools and Account, so the partial switch moves into Stage 2 with the menu. No scope change.
  - The Tools-hidden case cannot occur with `app` today (only MIT Rap Club hides Tools, and it uses `forum`); covered by the existing `forum` behavior.
  - Existing constraint kept for later stages: `PrivateMessageFeatureFlagTest` requires the page's `<nav class="nav">` to equal the live-refresh HTML.
- Estimate details: original scope as planned; uncertainty was low; no revisions. Retrospective 0.12 because the rendering switch moved to Stage 2.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*
