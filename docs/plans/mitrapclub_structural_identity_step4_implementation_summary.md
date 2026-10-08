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

## Stage 2 - Per-profile favicon/icon override mechanism (no-op by construction)
- Changes:
  - `src/ForumRewrite/PresentationSlotRegistry.php`: added a `favicon` slot (`fallback: 'default'`, `choices: ['default', 'mitrapclub']`).
  - New `src/ForumRewrite/View/FaviconRegistry.php`: `resolve(array $profile): string` maps the resolved slot value to a stable, unfingerprinted asset path (`'default'` → `/favicon.ico`, `'mitrapclub'` → `/assets/favicon-mitrapclub.ico`); single source of truth for both call sites below.
  - `src/ForumRewrite/View/TemplateRenderer.php`: both `renderLayout()` and `renderStandalonePage()` now pass `'faviconPath' => FaviconRegistry::resolve($profile)` to their templates; `renderStandalonePage()` gained a `SiteProfileRegistry::active()` lookup to support this.
  - `templates/layout.php` and `templates/standalone_layout.php`: `<link rel="icon">` now reads `$e($faviconPath)` instead of the hardcoded `/favicon.ico`.
  - `src/ForumRewrite/Host/BrowserRuntimeAssetRenderer.php::manifest()`: `icons[0].src` now reads `FaviconRegistry::resolve($profile)` instead of the hardcoded literal.
  - No profile opts into the `favicon` slot yet (`mitrapclub`'s `presentationSlots` is untouched in this stage), so every profile still resolves to the `default` choice.
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed.
  - Rendered `/` and `/manifest.webmanifest` for all four profiles via `FrontController` directly: every profile's `<link rel="icon">` and manifest `icons[0].src` still read `/favicon.ico` — confirmed no-op for all four, including `mitrapclub`, exactly as planned for this stage.
- Notes:
  - The mechanism is real and wired end-to-end but deliberately unexercised until Stage 3, so this stage's correctness is proven by the absence of change, not the presence of one.
  - Kept the favicon path unfingerprinted (not routed through `TemplateRenderer::assetPath()`) per Step 3's Key Risks, matching today's existing convention for this one asset.
