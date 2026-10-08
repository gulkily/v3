> **Feature plan:** [Step 1](./mitrapclub_structural_identity_step1_solution_assessment.md) · [Step 2](./mitrapclub_structural_identity_step2_feature_description.md) · [Step 3](./mitrapclub_structural_identity_step3_development_plan.md) · [Step 4](./mitrapclub_structural_identity_step4_implementation_summary.md)

## Completion Contract

- **Normal entry:** a visitor loads any page on `mitrapclub` (nav + favicon render on every page; theme switcher on pages that show it).
- **End-to-end outcome:** `mitrapclub`'s nav no longer lists `/tools/` (still reachable by direct URL); `mitrapclub` keeps the full `permittedThemes` breadth unchanged (decision below — no code change); a real per-profile favicon/icon override mechanism exists and is exercised by `mitrapclub`, currently pointing at a byte-identical copy of today's shared icon (no new art ships).
- **Required recovery:** none — config/presentation only, no new runtime failure mode.
- **Deployment/external verification:** not applicable.
- **Release condition:** `./v3 test` passes in full; a manual byte-diff of rendered nav, `<link rel="icon">` output, and `manifest.webmanifest` confirms no change for `zenmemes`/`chouse`/`qdb`.

## Key Risks

- **Theme-freedom decision, resolved:** keep `mitrapclub`'s `permittedThemes` at full breadth (no change to `SiteProfileRegistry`). Matches `qdb`/`chouse`'s existing precedent (both keep full breadth despite strong branding); no one has asked to restrict it; restricting is a trivial follow-up later if wanted. No stage needed — this is a config no-op.
- `PresentationSlotRegistry::resolve()` falls back per-slot when a profile's `presentationSlots` array omits a key (confirmed by reading its implementation) — so `zenmemes`/`chouse`/`qdb` need no new keys added at all for either new slot below; only `mitrapclub` needs to opt in. Mitigation: do not touch the other three profiles' `presentationSlots` arrays.
- The favicon is currently requested at a literal, unfingerprinted path (`/favicon.ico`) outside the asset-fingerprint pipeline (`TemplateRenderer::assetPath()`), and `BrowserRuntimeAssetRenderer::manifest()`'s `icons[0].src` is a separate hardcoded literal. Resolution: keep the per-profile favicon path **unfingerprinted** (same convention as today) in both places, driven by one shared resolver, rather than routing it through `assetPath()` — avoids a mismatch between what the static exporter's asset-fingerprint sweep discovers and what the manifest/layout actually reference. Early validation: Stage 2's byte-diff (must be a true no-op for all profiles before Stage 3 changes anything visible).
- **High risk:** `StaticArtifactBuilder`'s static-export favicon copy only knows about the two hardcoded root files (`/favicon.ico`, `/favicon.gif`) today. If Stage 3 adds a per-profile override file without also updating that copy loop, a static export of `mitrapclub` would reference a file that was never copied into the artifact (broken icon in the exported build only, not in the live app). Mitigation: Stage 3 explicitly extends that copy loop to include the active profile's resolved favicon path alongside the two existing defaults.

## Stage 1
- Goal: `mitrapclub`'s visible nav no longer lists `/tools/`; it stays reachable by direct URL, matching the existing Account/Invite-on-qdb precedent.
- Dependencies: none.
- Expected changes:
  - `src/ForumRewrite/PresentationSlotRegistry.php`: add a `toolsNav` slot (`fallback: 'visible'`, `choices: ['visible', 'hidden']`).
  - `src/ForumRewrite/SiteProfileRegistry.php`: add `'toolsNav' => 'hidden'` to `mitrapclub`'s `presentationSlots` only.
  - `src/ForumRewrite/View/TemplateRenderer.php` (nav-building method, ~line 258): compute `$hideToolsNav = PresentationSlotRegistry::resolve($profile, 'toolsNav') === 'hidden'`; skip pushing the `Tools` item in the generic (non-qdb) nav array when true. qdb's nav is already a fully separate array and is unaffected.
- Verification approach:
  - `./v3 test` full suite.
  - Render nav items for all four profiles: `mitrapclub`'s list omits `Tools`; `zenmemes`/`chouse`/`qdb` unchanged (qdb never had a `Tools` item to begin with).
  - Confirm `/tools/` still returns 200 on `mitrapclub` via direct request (route untouched).
- Risks or open questions:
  - Impact: a mistargeted conditional could hide `Tools` for every profile instead of just `mitrapclub`.
  - Early warning / validation: the four-profile nav render check above, before moving on.
  - Mitigation: gate strictly on the new slot's resolved value, not on profile name directly.
- Canonical components/API contracts touched: `PresentationSlotRegistry::SLOTS`, `SiteProfileRegistry::all()`, `TemplateRenderer`'s nav-building method.

## Stage 2
- Goal: introduce a real, working per-profile favicon/icon override mechanism that is a provable no-op for every existing profile (including `mitrapclub`, not yet opted in).
- Dependencies: none (independent of Stage 1).
- Expected changes:
  - `src/ForumRewrite/PresentationSlotRegistry.php`: add a `favicon` slot (`fallback: 'default'`, `choices: ['default', 'mitrapclub']`).
  - New small resolver (e.g. `FaviconRegistry::resolve(array $profile): string`, colocated under `src/ForumRewrite/View/` alongside `ThemeRegistry`): maps slot value `'default'` → `/favicon.ico`, `'mitrapclub'` → `/assets/favicon-mitrapclub.ico`; single source of truth used by both call sites below.
  - `src/ForumRewrite/View/TemplateRenderer.php`: both page-rendering methods (the main `layout.php` renderer and `renderStandalonePage()`) add a `'faviconPath' => FaviconRegistry::resolve($profile)` template var (not run through `assetPath()` — see Key Risks); `renderStandalonePage()` gains a `$profile = SiteProfileRegistry::active();` lookup to support this.
  - `templates/layout.php` and `templates/standalone_layout.php`: `<link rel="icon" href="...">` reads `$e($faviconPath)` instead of the hardcoded `/favicon.ico`.
  - `src/ForumRewrite/Host/BrowserRuntimeAssetRenderer.php::manifest()`: `icons[0].src` reads `FaviconRegistry::resolve($profile)` instead of the hardcoded literal.
- Verification approach:
  - `./v3 test` full suite.
  - Byte-diff rendered `<link rel="icon">` output and `manifest.webmanifest` for all four profiles against a pre-stage snapshot: must be identical for all four (no profile has opted into the `favicon` slot yet, so every profile still resolves to `/favicon.ico`).
- Risks or open questions:
  - Impact: same as the Key Risks fingerprinting note above — resolved by keeping this path unfingerprinted.
  - Early warning / validation: the all-four-profiles byte-diff above.
  - Mitigation: none further needed; this stage ships no profile-visible change by construction.
- Canonical components/API contracts touched: `PresentationSlotRegistry::SLOTS`, new `FaviconRegistry`, `TemplateRenderer`, `templates/layout.php`, `templates/standalone_layout.php`, `BrowserRuntimeAssetRenderer::manifest()`.

## Stage 3
- Goal: exercise the Stage 2 mechanism for `mitrapclub` — prove it's live wiring, not dead code — without shipping new artwork.
- Dependencies: Stage 2 (mechanism must exist first).
- Expected changes:
  - New binary asset `public/assets/favicon-mitrapclub.ico`: an exact byte-copy of today's `public/favicon.ico`.
  - `src/ForumRewrite/SiteProfileRegistry.php`: add `'favicon' => 'mitrapclub'` to `mitrapclub`'s `presentationSlots` only.
  - `src/ForumRewrite/Host/StaticArtifactBuilder.php` (`copyOfflineRuntimeFiles()`, ~line 217): extend the existing root-favicon copy loop to additionally copy the active profile's `FaviconRegistry::resolve()` path into the exported artifact (alongside the two existing hardcoded defaults), so a static export of `mitrapclub` doesn't reference a file that was never copied.
- Verification approach:
  - `./v3 test` full suite.
  - Byte-diff rendered `<link rel="icon">` output and `manifest.webmanifest` for `zenmemes`/`chouse`/`qdb` against the Stage 2 snapshot: must remain identical.
  - Render `mitrapclub`'s `<link rel="icon">` and manifest: both now reference `/assets/favicon-mitrapclub.ico`; confirm that file's bytes are identical to `public/favicon.ico` (`cmp` or hash comparison) — proving zero visual regression.
  - Confirm the new asset file is present in a static export of `mitrapclub` at the referenced path.
- Risks or open questions:
  - Impact: forgetting the `StaticArtifactBuilder` copy-loop update breaks the static-export icon for `mitrapclub` only (the Key Risks high-risk item).
  - Early warning / validation: the static-export file-presence check above.
  - Mitigation: already folded into Expected changes; verified explicitly in this stage rather than assumed.
- Canonical components/API contracts touched: `SiteProfileRegistry::all()`, `StaticArtifactBuilder::copyOfflineRuntimeFiles()`.

Waiting for "Approved Step 3" before branching and starting Step 4.
