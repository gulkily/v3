# OpenPGP Production Incident Recovery Checklist

## Scope and confirmed behavior

This checklist addresses a production failure in which the browser OpenPGP
loader selects a bundle but cannot fetch it:

- secure origins select `/assets/openpgp.min.js` (v6);
- public HTTP origins select `/assets/openpgp.v5.11.3.min.js` (v5);
- the loader is itself emitted with a fingerprinted URL;
- the bundle URLs are currently raw, runtime JavaScript dependencies.

The HTTP v5 selection is correct. A failure mentioning
`/assets/openpgp.v5.11.3.min.js` is an asset-delivery failure, not a fallback
selection failure.

## Target environment

- Public HTTP origin: `http://zenmemes.com/`
- Public HTTPS origin: `https://zenmemes.com/`
- Required HTTP bundle: `http://zenmemes.com/assets/openpgp.v5.11.3.min.js`
- Required HTTPS bundle: `https://zenmemes.com/assets/openpgp.min.js`
- Hosting constraint: only the application files and `public/.htaccess` are
  controllable; do not require Apache virtual-host access for recovery.

### Observed production state

- [x] HTTPS v6 bundle returns JavaScript:
  `https://zenmemes.com/assets/openpgp.min.js`.
- [ ] HTTP v5 bundle currently returns `404`:
  `http://zenmemes.com/assets/openpgp.v5.11.3.min.js`.
- [x] HTTP raw v6 also returns `404`:
  `http://zenmemes.com/assets/openpgp.min.js`.

The public HTTP origin does not serve raw assets, not merely the v5 bundle.
Fingerprinted requests can be served by the PHP `FrontController`, but raw
requests fall through to a 404. The immediate recovery is the safe raw public
asset fallback in the code-fix inventory; restoring a file alone cannot fix
this host behavior.

`./v3 build-static` alone is not a recovery action for this incident: its
current output does not contain the raw v5 filename requested by the loader.

Do not clear browser storage, regenerate user keys, force HTTPS, or enable
HSTS as incident workarounds. Those actions can strand existing browser-held
identities and do not restore the missing bundle.

## 1. Contain and identify the live asset origin

- [ ] Pause any deployment that publishes a static artifact directory as the
  public asset origin until it has passed the checks below.
- [ ] Record the effective production `DocumentRoot`, CDN/origin mapping, and
  the location from which `/assets/*` is served.
- [ ] Confirm that the deployed application checkout includes both tracked
  source files:

  ```text
  public/assets/openpgp.min.js
  public/assets/openpgp.v5.11.3.min.js
  ```

- [x] The deployed checkout contains `openpgp.v5.11.3.min.js` with the
  expected SHA-256:
  `89ae4b15e830e08096a125003218bdc7b5c39a4075437dc8d6d4a4e3bdc9a550`.

- [ ] Confirm the web server can read both files.
- [ ] Confirm client-visible behavior only: the checked-in `public/.htaccess`
  permits existing `/assets/*` files to bypass PHP. If a requested asset still
  reaches PHP, the application must supply a safe public-asset fallback rather
  than relying on unavailable host configuration access.
- [ ] From an external machine, capture status, headers, and final URL for
  each request. Use `GET`, rather than relying only on `HEAD`:

  ```bash
  curl -sS -D - -o /dev/null http://zenmemes.com/assets/openpgp.v5.11.3.min.js
  curl -sS -D - -o /dev/null https://zenmemes.com/assets/openpgp.min.js
  ```

- [ ] Treat any redirect, HTML/PHP error page, 404, 403, 405, or non-JavaScript
  content type as a failed asset check.
- [x] The required HTTP v5 asset check failed with `404`; the required HTTPS
  v6 asset check returned JavaScript.
- [ ] Compare the response body checksum with the deployed source file. A
  `200` response containing an error document is not a successful recovery.
- [ ] Inspect CDN/reverse-proxy rules for an asset allowlist, deployment-copy
  exclusion, or a static-release origin that omits the raw bundle files.

## 2. Restore service safely

- [x] Add a safe PHP fallback for raw public assets that reach `index.php`
  (working tree; deploy pending).
- [ ] Restore both raw bundle files to the actual production asset origin.
- [ ] Verify the outcome without host configuration access: both required
  bundle URLs must return their exact JavaScript bodies from the public site.
  If the host routes a missing asset through PHP, use the application-level
  public-asset fallback described in the code-fix inventory.
- [ ] If an artifact-only/CDN asset origin must remain temporarily, publish
  both raw bundle filenames there as a compatibility hotfix.
- [ ] Purge only the relevant CDN cache entries after the origin is correct:
  the two bundle URLs and the fingerprinted loader/browser-signing URLs.
- [ ] On secure origins, unregister/update stale service workers only after
  confirming the network asset responses are healthy. A service worker is not
  available on ordinary public HTTP and is not the primary cause of the HTTP
  failure.
- [ ] Keep the explicit anonymous-post action available throughout recovery.
- [ ] Do not ask affected users to clear storage. Their saved private key may
  still be available on the same origin after the asset path is restored.

## 3. Verify user-visible recovery

- [ ] In a fresh HTTP browser profile, confirm
  `window.__forumOpenPgpLoader.selectedVersion === "v5"` and that its selected
  path returns JavaScript with status `200`.
- [ ] In a fresh HTTPS browser profile, confirm the selected version is `v6`
  and its selected path returns JavaScript with status `200`.
- [ ] On HTTP, create an identity, reload, and perform a second signed action.
  The second action must not ask for a username.
- [ ] On HTTPS, repeat the same create/reload/second-action check.
- [ ] With an existing keypair on the same scheme, host, and port, confirm a
  signed action does not show a username prompt.
- [ ] Confirm an intentionally unavailable OpenPGP bundle offers explicit
  anonymous posting and retains the compose draft.
- [ ] Confirm that HTTP and HTTPS are described as separate browser-storage
  origins. A key created on one cannot be silently read from the other.

## 4. Fix the release and asset contract

- [x] Stop making the OpenPGP loader depend on raw hard-coded bundle URLs
  (working tree; deploy pending).
- [x] Have server rendering emit the current fingerprinted v5 and v6 URLs in a
  small asset configuration object or loader data attributes.
- [x] Make `openpgp_loader.js` select between those emitted URLs according to
  `window.isSecureContext`.
- [x] Register both bundles as explicit runtime asset dependencies so a static
  artifact build copies them even though they are not literal HTML `src` or
  `href` values.
- [x] Apply the same emitted-asset/dependency mechanism to
  `lazy_compose_signing.js`, which currently dynamically requests raw loader
  and browser-signing filenames.
- [ ] Choose and document one compatibility policy for raw filenames:
  preserve raw aliases at the public asset origin during transition, or make
  all runtime callers use emitted fingerprints. Do not leave the behavior
  dependent on an undocumented deployment copy rule.
- [x] Extend the static-artifact validator so it checks declared runtime
  dependencies, not only fingerprinted URLs found by regex in rendered HTML.
- [x] Ensure `build-static --shared-only` carries or regenerates runtime assets
  required by the refreshed pages.

## 5. Fix the identity prompt failure path

- [x] Check OpenPGP availability before asking for a username.
- [x] When the loader cannot load, show the OpenPGP-unavailable/anonymous-post
  UI without opening a username prompt.
- [x] Add a single in-flight identity-preparation promise shared by compose,
  reactions, invitations, account setup, and private-site authentication.
- [x] Ensure concurrent identity requests produce one prompt and one key
  generation attempt.
- [x] Record a terminal loader failure for the current page so a retry does not
  show another username prompt before the asset issue is fixed.
- [ ] Preserve the compose draft and re-enable the action controls after a
  loader failure.
- [ ] Explain that a pre-existing identity may be on a different scheme/host
  only when that is applicable; do not describe the asset failure as a missing
  identity.

## 6. Add regression coverage

- [ ] Test loader selection: insecure public context selects v5; secure
  context selects v6.
- [ ] Test a generated static release contains every declared OpenPGP runtime
  dependency, including v5 and v6 bundles.
- [ ] Test an artifact-only asset origin, if supported, can satisfy all
  selected bundle requests.
- [ ] Test the static-artifact checker fails when a declared runtime asset is
  absent.
- [ ] Test lazy compose uses deployable emitted asset URLs rather than raw
  names.
- [ ] Test a loader failure results in zero username prompts and leaves no
  partial identity state.
- [ ] Test two concurrent identity requests result in exactly one username
  prompt and one key-generation attempt.
- [ ] Test a successful first identity setup persists the username and keypair
  before a subsequent signed action.
- [ ] Test HTTP v5 authored posting, HTTPS v6 authored posting, and explicit
  anonymous posting when OpenPGP is unavailable.
- [ ] Add a deployment smoke test that fetches both actual selected bundle URLs
  over their respective origins and rejects non-JavaScript responses.

## 7. Update operations and observability

- [ ] Add the two direct bundle `GET` checks to the production deployment
  runbook and deploy checklist.
- [ ] Record which deployment artifact owns `public/assets` and which owns
  `state/static_html`; they must not be conflated.
- [ ] Add monitoring for failed `/assets/openpgp*.js` responses, including
  status code, content type, final URL, and release identifier.
- [ ] Add client-side telemetry or a privacy-preserving error count for loader
  failure, selected bundle version, and anonymous fallback selection.
- [ ] Include a rollback instruction: restore the last known-good public asset
  package first, then invalidate only the affected CDN/service-worker cache.
- [ ] Document the browser-storage boundary: scheme, hostname, and port each
  have separate `localStorage`; no automatic private-key migration is allowed.

## Completion criteria

- [ ] Both bundle URLs return the expected JavaScript from production.
- [ ] HTTP uses v5 and HTTPS uses v6 without a browser identity error.
- [ ] A first successful identity setup is followed by no prompt on the next
  signed action from the same origin.
- [ ] A failed loader produces no username prompt and leaves a usable anonymous
  fallback.
- [ ] Static builds, shared-only refreshes, and the production deployment gate
  all verify runtime OpenPGP dependencies.
