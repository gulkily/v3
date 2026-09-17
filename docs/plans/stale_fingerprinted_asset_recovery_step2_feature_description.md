# Stale Fingerprinted Asset Recovery Step 2 Feature Description

## Problem

Anonymous or private-browsing visitors can receive static HTML whose fingerprinted asset references belong to an older release. When those files are gone, the page loads without styling or behavior.

## User stories

- As a private-browsing visitor, I want pages to load their CSS and JavaScript reliably so that the site never appears broken because of an expired asset URL.
- As a deployer, I want generated HTML and assets to remain internally consistent so that releases do not create stale references.
- As a returning visitor, I want an already-cached page to recover gracefully after a deployment so that I do not need to clear browser data or reload repeatedly.
- As an operator, I want deployment checks to detect missing referenced assets so that failures are found before users report them.

## Core requirements

- A missing obsolete fingerprinted asset must recover to the current valid asset when its unversioned source still exists.
- Recovery must preserve correct content types and must not expose arbitrary filesystem paths.
- Static artifact generation and publication must keep generated HTML and fingerprinted assets consistent as one release unit.
- Generated pages must be checked for missing fingerprinted asset references before a release is considered healthy.
- Existing fingerprinted assets, cache behavior, authenticated routes, and normal PHP fallback rendering must continue working.

## Shared component inventory

- **`AssetFingerprint`:** canonical asset naming and hash validation surface; extend it rather than creating a second fingerprint parser.
- **`FrontController` asset handling:** existing public request path for fingerprinted assets; reuse it for stale-reference recovery and preserve its response/content-type boundary.
- **`StaticArtifactBuilder`:** existing static HTML and asset publication flow; extend its release consistency behavior rather than adding a parallel builder.
- **`TemplateRenderer` asset URL generation:** canonical source of fingerprinted CSS/JavaScript URLs; keep it unchanged unless verification identifies a generation defect.
- **Asset and application smoke tests:** existing verification surfaces; add coverage for stale references, generated asset availability, and release consistency.

## Simple user flow

1. A visitor opens a page from a static or cached release.
2. The page requests its fingerprinted CSS or JavaScript asset.
3. If that fingerprint is obsolete, the server resolves the current valid asset and the browser receives usable content.
4. On future releases, the artifact build and health check confirm that every generated reference has a corresponding asset.

## Success criteria

- A request for an obsolete but recognized fingerprinted asset no longer produces a page-breaking 404 when the current source asset exists.
- Generated anonymous pages reference only assets available in the same published release.
- A release check fails clearly when any fingerprinted CSS or JavaScript reference is missing.
- Private-browsing and cookie-bearing requests both load styled pages after an asset-changing deployment.
- Existing asset fingerprint, cache, route, and security tests continue to pass.
