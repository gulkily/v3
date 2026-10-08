> **Feature plan:** [Step 1](./mitrapclub_structural_identity_step1_solution_assessment.md) · [Step 2](./mitrapclub_structural_identity_step2_feature_description.md) · [Step 3](./mitrapclub_structural_identity_step3_development_plan.md) · [Step 4](./mitrapclub_structural_identity_step4_implementation_summary.md)

## Stage 1 - Hide `/tools/` from mitrapclub's nav
- Changes:
  - `src/ForumRewrite/PresentationSlotRegistry.php`: added a `toolsNav` slot (`fallback: 'visible'`, `choices: ['visible', 'hidden']`).
  - `src/ForumRewrite/SiteProfileRegistry.php`: added `'toolsNav' => 'hidden'` to `mitrapclub`'s `presentationSlots` only (the other three profiles need no change — `PresentationSlotRegistry::resolve()` already falls back per-slot when a profile's array omits a key).
  - `src/ForumRewrite/View/TemplateRenderer.php` (`navItems()`): resolves `toolsNav`; when hidden and not on the qdb nav, filters the `Tools` item out of the generic nav array. `/tools/` route itself untouched.
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed.
  - Rendered `/` for all four profiles via `FrontController` directly (fixture repo + a freshly built read-model sqlite): `mitrapclub`'s nav omits `Tools` (`Board`/`About`/`Users`/`Account` remain); `zenmemes`/`chouse` unchanged (`Tools` present); `qdb` unchanged (its own custom nav never had a `Tools` item).
  - Rendered `/tools/` directly on `mitrapclub`: returns the page normally, confirming the route stays reachable by direct URL.
- Notes:
  - Matches the existing precedent of dropping Account/Invite from qdb's nav while keeping the routes reachable directly.
