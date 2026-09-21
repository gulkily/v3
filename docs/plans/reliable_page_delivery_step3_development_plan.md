# Reliable Page Delivery: Step 3 Development Plan

## Stage 1 - Critical first paint
- Goal: Render the selected theme and mobile page shell without waiting for external CSS.
- Dependencies: Approved shared layout direction.
- Expected changes: Add a compact shared critical-style source to the canonical layout; retain the fingerprinted full stylesheet as the refinement layer.
- Verification approach: Render-page tests confirm critical CSS and the fingerprinted stylesheet; manual cold-cache mobile-throttle check has no unstyled first paint.
- Risks or open questions:
  - Critical rules must cover visible variants without duplicating the full stylesheet.
- Canonical components/API contracts touched: `TemplateRenderer`, `templates/layout.php`, theme CSS.

## Stage 2 - Interactive-first OpenPGP loading
- Goal: Make the theme menu usable before crypto evaluation while OpenPGP starts downloading eagerly.
- Dependencies: Stage 1 layout ordering.
- Expected changes: Extend the shared loader contract to separate download readiness from evaluation readiness; activate theme controls before crypto work and preserve all signing/authentication callers.
- Verification approach: Loader and browser-signing tests cover eager transfer, readiness, and action-time waits; throttled manual check confirms theme interaction before OpenPGP evaluation.
- Risks or open questions:
  - A user can begin a signing action before evaluation completes and needs clear existing-progress feedback.
- Canonical components/API contracts touched: `openpgp_loader.js`, `browser_signing.js`, `private_site_auth.js`, `theme_toggle.js`, layout script ordering.

## Stage 3 - Cache-compatible HTML delivery
- Goal: Revalidate HTML safely across deployments without sacrificing fingerprinted-asset caching.
- Dependencies: Stages 1-2 rendered output.
- Expected changes: Add HTML validators and cookie-aware cache variation for static and dynamic page responses; retain immutable caching for fingerprinted assets.
- Verification approach: Front-controller tests exercise validators, not-modified responses, and anonymous-versus-session page variants; manual pre/post-deploy cache check has no reload loop.
- Risks or open questions:
  - Validators must not expose a signed-in HTML response to another browser.
- Canonical components/API contracts touched: `FrontController`, `Application` HTML responses, `AssetFingerprint` cache contract.

## Stage 4 - Candidate read-model build
- Goal: Build a complete candidate read model without touching the live SQLite database.
- Dependencies: Existing rebuild metadata and execution-lock behavior.
- Expected changes: Add a candidate-build contract that produces and validates a separate database.
- Verification approach: Integration test serves reads during a deliberately slow candidate rebuild and confirms the old live model remains available.
- Risks or open questions:
  - Candidate metadata must prove compatibility before it is eligible for promotion.
- Canonical components/API contracts touched: `ReadModelBuilder`, `ReadModelConnection`, read-model metadata, execution locking.

## Stage 5 - Atomic read-model promotion
- Goal: Switch a validated candidate model live without exposing a partial model or losing concurrent writes.
- Dependencies: Stage 4 candidate build.
- Expected changes: Add an atomic promotion/recovery contract coordinated with existing write and execution locks.
- Verification approach: Integration test interrupts promotion and exercises a concurrent write; the previous or the complete new model remains readable.
- Risks or open questions:
  - SQLite file replacement and sidecar-file behavior must be safe on the production filesystem.
- Canonical components/API contracts touched: `ReadModelConnection`, write services, execution locking, read-model metadata.

## Stage 6 - Atomic static release publication
- Goal: Keep serving the prior complete artifact release until a new one is validated and switched live.
- Dependencies: Stages 4-5 candidate model and promotion.
- Expected changes: Build artifacts into a release directory, validate referenced assets, atomically change the active release, and retire old releases safely.
- Verification approach: Integration test interrupts a candidate build and confirms live pages remain available; successful promotion serves only the complete new release.
- Risks or open questions:
  - Existing sibling `public/*.html` compatibility must not override the active release.
- Canonical components/API contracts touched: `StaticArtifactBuilder`, `FrontController` static resolution, `AssetFingerprint` validation.

## Stage 7 - Deployment guardrails
- Goal: Make the safe path the documented production path.
- Dependencies: Stages 3-6.
- Expected changes: Update the deploy runbook with cache verification, release promotion, rollback, and cold-cache/authenticated smoke checks.
- Verification approach: Run the documented procedure against a disposable local deployment layout.
- Risks or open questions:
  - Shared-host permissions and available compression modules vary by host.
- Canonical components/API contracts touched: `docs/runbooks/production_deploy.md`, CLI build commands.
