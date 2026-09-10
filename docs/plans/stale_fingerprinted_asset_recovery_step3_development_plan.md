# Stale Fingerprinted Asset Recovery Step 3 Development Plan

## Stage 1
- Goal: Reproduce the stale HTML/asset mismatch and establish regression coverage before changing request handling.
- Dependencies: Approved Step 2; current static artifact and fingerprinting behavior.
- Expected changes: Add focused tests that distinguish a current fingerprint, an obsolete fingerprint for a known source asset, and an invalid/unknown asset path.
- Verification approach: Run the focused asset and front-controller tests; confirm the current mismatch is represented without changing runtime behavior.
- Risks or open questions:
  - Existing generated files may be stale or untracked in local environments.
- Canonical components/API contracts touched: `AssetFingerprint`, `FrontController`, `tests/LocalAppSmokeTest.php`.

## Stage 2
- Goal: Recover obsolete fingerprinted asset requests safely at runtime.
- Dependencies: Stage 1 coverage.
- Expected changes: Extend the canonical fingerprint resolver and front-controller asset path to identify a stale hash only when its known source asset exists, then direct the browser to the current fingerprinted URL with safe cache semantics. Preserve rejection of unknown paths and arbitrary files.
- Verification approach: Test CSS and JavaScript stale requests, current requests, unknown hashes, unsupported methods, and content-type/cache behavior.
- Risks or open questions:
  - Redirect handling must not create loops or make obsolete URLs permanently immutable.
  - Recovery must remain limited to public assets and recognized fingerprint syntax.
- Canonical components/API contracts touched: `src/ForumRewrite/Host/AssetFingerprint.php`, `src/ForumRewrite/Host/FrontController.php`.

## Stage 3
- Goal: Keep static HTML and fingerprinted assets consistent when artifacts are generated or published.
- Dependencies: Stage 2 committed.
- Expected changes: Adjust the static artifact workflow so generated pages and their referenced assets are published as one consistent artifact set, with replacement ordering that cannot expose a new HTML reference before its asset exists.
- Verification approach: Build a temporary artifact set after changing an asset; verify generated HTML references available files and that replacement leaves the previous set usable until the new set is ready.
- Risks or open questions:
  - The hosting environment may determine whether atomic directory/symlink publication is available; retain the runtime fallback if it is not.
- Canonical components/API contracts touched: `src/ForumRewrite/Host/StaticArtifactBuilder.php`, static artifact layout, deployment-facing build flow.

## Stage 4
- Goal: Add a release-time asset-reference health check and complete end-to-end verification.
- Dependencies: Stages 1–3 committed.
- Expected changes: Validate fingerprinted CSS/JavaScript references emitted by generated HTML, fail or report missing assets clearly, and document the recovery and publication guarantees in the Step 4 summary.
- Verification approach: Run focused tests, the full suite, a generated-artifact check, and a private-style request flow that loads HTML followed by every referenced asset.
- Risks or open questions:
  - Existing unrelated full-suite failures must remain separately identified rather than obscuring asset regressions.
- Canonical components/API contracts touched: `tests/LocalAppSmokeTest.php`, `StaticArtifactBuilder`, `AssetFingerprint`, `FrontController`, Step 4 implementation summary.

## Approval Gate

- Create the Step 4 feature branch only after explicit `Approved Step 3`.
- Commit the approved Step 1–3 planning documents first, then commit each implementation stage with its Step 4 summary update.
