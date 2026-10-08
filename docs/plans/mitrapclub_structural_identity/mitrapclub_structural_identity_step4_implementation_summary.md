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

## Stage 3 - Exercise the favicon override for mitrapclub
- Changes:
  - New binary asset `public/assets/favicon-mitrapclub.ico`: an exact byte-copy of `public/favicon.ico` (`cmp` confirms identical bytes) — no new artwork ships, per Step 2's scope.
  - `src/ForumRewrite/SiteProfileRegistry.php`: added `'favicon' => 'mitrapclub'` to `mitrapclub`'s `presentationSlots` only.
  - `src/ForumRewrite/Host/StaticArtifactBuilder.php` (`copyOfflineRuntimeFiles()`): the favicon copy loop now also copies the active profile's `FaviconRegistry::resolve()` path (deduplicated against the two existing hardcoded defaults), so a static export references a file that was actually copied into the artifact.
- Verification:
  - `./v3 test` — full suite: 817 run, 817 passed, 0 failed.
  - Rendered `/` and `/manifest.webmanifest` for all four profiles via `FrontController` directly: `zenmemes`/`chouse`/`qdb` unchanged (`/favicon.ico`); `mitrapclub` now resolves both to `/assets/favicon-mitrapclub.ico`.
  - `cmp public/favicon.ico public/assets/favicon-mitrapclub.ico` — identical, confirming zero visual regression.
  - Built a static export of `mitrapclub` via `StaticArtifactBuilder` directly (fixture repo + the same read-model sqlite): confirmed `assets/favicon-mitrapclub.ico` is present in the exported artifact root. Built a static export of `zenmemes` the same way: confirmed no `favicon-mitrapclub.ico` appears anywhere under its artifact root (unaffected).
- Notes:
  - Closes the Step 3 high-risk item (static-export copy loop previously only knew about the two default files) with an explicit before/after check rather than an assumption.
  - Feature complete per the Step 3 Completion Contract: `mitrapclub`'s nav omits `Tools` (reachable by direct URL); theme breadth is unchanged by deliberate decision (no code needed); the favicon/icon override mechanism is real, live-wired, and exercised for `mitrapclub` with zero new art; `zenmemes`/`chouse`/`qdb` are provably unchanged throughout.
