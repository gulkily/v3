# Theme CSS Loading — Step 3: Development Plan

## Stage 1 - Define theme asset and hint contracts
- Goal: Establish one validated source of explicit theme asset names and the resolved-theme cookie boundary.
- Dependencies: Approved Steps 1–2; existing `ThemeRegistry` names and modes.
- Expected changes: Extend the theme registry/view contract with fingerprintable stylesheet metadata; define `theme-hint` as a concrete explicit theme only, with client storage retaining the `auto` or explicit preference.
- Verification approach: Unit-test valid asset metadata and reject missing, unknown, `auto`, or malformed cookie hints.
- Risks or open questions:
  - Cookie attributes and HTML cache variation must match the deployment cache model.
- Canonical components/API contracts touched: `ThemeRegistry`; `TemplateRenderer` layout context.

## Stage 2 - Extract shared and per-theme stylesheets
- Goal: Separate shared critical/base styles from independently cacheable, scoped explicit-theme files without visual regressions.
- Dependencies: Stage 1 asset naming contract; current selector and media-query inventory.
- Expected changes: Create one fingerprintable stylesheet per explicit theme; retain shared rules in `site.css`; move each theme's variables, overrides, responsive rules, and menu-swatch styling as an intact ownership group.
- Verification approach: Check every registry theme has a source asset; compare selector ownership; manually inspect representative light, dark, and highly customized themes at desktop and mobile widths.
- Risks or open questions:
  - The unscoped light fallback and critical CSS marker must remain usable without JavaScript.
  - Cross-theme selectors or mixed media queries may need a deliberately shared home.
- Canonical components/API contracts touched: `public/assets/site.css`; new theme stylesheet assets; `ThemeRegistry` metadata.

## Stage 3 - Render the prioritized stylesheet link
- Goal: Let the shared layout provide a normal high-priority stylesheet link from the validated cookie hint while allowing early client correction.
- Dependencies: Stages 1–2; existing inline head resolver and asset fingerprinting.
- Expected changes: Add the hinted/theme-manifest layout data and stylesheet-link surface; preserve the base stylesheet and no-JavaScript fallback; ensure the inline resolver can replace an incorrect or absent hint before the theme link is fetched.
- Verification approach: Render pages with valid, invalid, absent, and stale hints; assert fingerprinted base and active-theme paths, safe fallback behavior, and link ordering.
- Risks or open questions:
  - A first visit has no hint and must still prioritize the client-resolved theme.
- Canonical components/API contracts touched: `TemplateRenderer::renderLayout()`; `templates/layout.php`; `AssetFingerprint`.

## Stage 4 - Reconcile preference, Auto/System, and hint updates
- Goal: Preserve the existing selection UX while keeping the cookie hint aligned with the concrete resolved theme.
- Dependencies: Stage 3; current `theme_toggle.js` selection and media-query handling.
- Expected changes: Extend client theme resolution to set the active asset, update `theme-hint` only when its resolved value changes, and react to system-scheme changes while Auto/System is selected.
- Verification approach: Browser-test explicit selection, Auto/System at both schemes, storage failures, stale hints, and an operating-system scheme change; confirm preference and hint remain distinct.
- Risks or open questions:
  - Cookie writes should not occur repeatedly when the resolved value has not changed.
- Canonical components/API contracts touched: `public/assets/theme_toggle.js`; inline resolver contract; `ThemeRegistry` modes.

## Stage 5 - Warm alternate themes and preserve responsive switching
- Goal: Load all non-active theme assets after first-render work without allowing a later selection to flash unstyled or fallback styling.
- Dependencies: Stages 2–4; scoped theme stylesheets.
- Expected changes: Add low-priority background loading with readiness tracking; promote a requested but not-yet-ready theme and retain a safe visual state until it is available.
- Verification approach: Throttle network and confirm initial priority order; switch through all themes before and after warmup; assert no duplicate asset requests after cached navigation.
- Risks or open questions:
  - Lazy loading creates a short pre-warm window; test its selection behavior explicitly rather than assuming an instant cache hit.
- Canonical components/API contracts touched: `theme_toggle.js`; layout theme asset manifest; theme menu state.

## Stage 6 - Cover static artifacts and regressions
- Goal: Ensure dynamic and generated pages deliver the same fingerprinted theme assets and retain all current UI behavior.
- Dependencies: Stages 1–5; static artifact asset-copy behavior.
- Expected changes: Extend regression coverage for rendered dynamic pages and generated artifacts; update asset-copy or release handling only if new files expose a gap.
- Verification approach: Run the PHP suite, build a static artifact, verify every referenced fingerprinted theme asset exists, and perform final visual/accessibility checks for the theme menu and no-JavaScript page.
- Risks or open questions:
  - Generated HTML may encode a default/no-cookie hint, so client reconciliation must be independently verified.
- Canonical components/API contracts touched: `StaticArtifactBuilder`; `StaticArtifactReleasePublisher`; `AssetFingerprint`; `LocalAppSmokeTest`.
