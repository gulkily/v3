# Reliable Page Delivery: Step 2 Feature Description

## Problem

Cold mobile visits can show an unstyled page and delay basic controls behind OpenPGP loading; deployment can also expose mismatched cached page resources or stall live traffic during a static rebuild.

## User stories

- As a mobile visitor, I want the first visible page to have its correct theme and mobile layout immediately so that it does not flash unstyled.
- As a visitor, I want the theme menu to work as soon as it appears so that cryptographic loading does not block a basic preference.
- As a signer, I want OpenPGP already downloading during page load so that authentication and signing are ready promptly.
- As an operator, I want deployments and static publication to keep serving complete, compatible pages so that visitors are not trapped in reloads or blocked by maintenance.

## Core requirements

- The first paint supplies essential layout and the locally selected theme without a theme cookie or per-user public HTML.
- OpenPGP starts downloading eagerly but does not delay first-paint styling or basic control activation.
- Authentication, invitation signing, and other browser-signing flows retain their current security checks and work once OpenPGP is ready.
- HTML and fingerprinted assets remain cache-compatible across deployments; signed-in and anonymous variants are never confused.
- Rebuilding derived data and publishing static artifacts never holds the live read-model write lock for the duration of a full build.

## Shared component inventory

- `TemplateRenderer` and `templates/layout.php`: extend the canonical page shell for all rendered HTML.
- `theme_toggle.js` and the existing early theme selector: reuse for immediate theme selection and menu activation.
- `openpgp_loader.js`, `browser_signing.js`, and `private_site_auth.js`: extend the shared browser-crypto loading contract; do not create parallel signing paths.
- `FrontController`, `StaticArtifactBuilder`, and `ReadModelBuilder`: extend the shared public-page caching and derived-artifact publication path.

## User flow

1. A visitor opens a page and sees its chosen theme and usable theme control immediately.
2. OpenPGP downloads concurrently without blocking those interactions.
3. The browser finishes preparing OpenPGP in the background; a signing or authentication action uses the prepared library.
4. A deployment publishes a complete compatible page set, and visitors receive the correct current HTML/resources.

## Success criteria

- A cold, throttled mobile load has no visible unstyled first paint and the theme menu responds before OpenPGP evaluation completes.
- Browser authentication, invitation issuance, and signing tests pass with eager background crypto loading.
- Cached pages from before a deployment do not enter a reload loop.
- A full derived-data/static-artifact rebuild does not make normal page requests wait on its SQLite write transaction.
