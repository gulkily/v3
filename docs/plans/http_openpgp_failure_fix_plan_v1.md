# HTTP OpenPGP.js Failure Fix Plan

## Cause

When the live site is opened over plain `http://`, browser-side identity setup can show:

```text
OpenPGP.js failed to load.
```

The app loads OpenPGP.js from the local vendored browser bundle:

```html
<script src="/assets/openpgp.min.js?..."></script>
<script src="/assets/browser_signing.js?..."></script>
```

The error message is thrown by `public/assets/browser_signing.js` when `window.openpgp` is missing before key generation. The OpenPGP.js v6 bundle uses WebCrypto during script evaluation. On a public non-local HTTP origin, browsers do not expose `crypto.subtle`, because WebCrypto is restricted to secure contexts. When `crypto.subtle` is unavailable, `public/assets/openpgp.min.js` throws `The WebCrypto API is not available` before it defines the global `openpgp` object. `browser_signing.js` then sees no `window.openpgp` and reports the generic load failure.

Local `http://127.0.0.1` testing does not reproduce the production behavior reliably because loopback origins receive special secure-context treatment in modern browsers.

The deployment docs currently include an Apache port 80 vhost, so the live site can remain reachable over plain HTTP. That access should stay supported: HTTPS adds certificate, renewal, proxy, mixed-content, hostname, clock-skew, captive-portal, and TLS compatibility failure modes that plain HTTP does not have. For sites that can run HTTPS reliably, the docs should nudge operators toward enabling it, but the app should not force users onto HTTPS or make HTTP unusable.

## Findings

- The vendored browser bundle is `OpenPGP.js v6.3.0`, as identified at the top of `public/assets/openpgp.min.js`.
- OpenPGP.js v6 requires the Web Crypto API's `SubtleCrypto`. In browsers, `SubtleCrypto` is only available in secure contexts, so public `http://` origins cannot run this bundle successfully.
- OpenPGP.js v6 changed behavior from earlier releases: v6 intentionally fails when WebCrypto is unavailable. Older OpenPGP.js v5 builds had JavaScript/asm.js fallback paths and could work in some non-WebCrypto browser contexts, which explains why OpenPGP may have worked over HTTP in the past.
- The current compose UI is identity-first. `public/assets/browser_signing.js` intercepts compose form submission, calls `ensureComposeIdentity()`, and only submits after it can populate `author_identity_id`.
- Because `ensureComposeIdentity()` may call `window.openpgp.generateKey()` and `window.openpgp.readKey()`, a first-time or unlinked user on public HTTP cannot reliably create the browser OpenPGP identity needed by the current compose flow.
- The server write path can store anonymous posts when `author_identity_id` is absent: `LocalWriteService::resolveAuthorIdentityId()` returns `null` for an empty value, and `createThread()` / `createReply()` omit `Author-Identity-ID` in that case. However, the current browser compose script does not naturally allow this path because it prevents default submit while preparing identity.
- Therefore, keeping HTTP access does not automatically mean normal browser posting works over HTTP. HTTP posting needs either an OpenPGP implementation that works without WebCrypto, an explicit anonymous compose path, or a server-side/non-browser identity path.

## Selected Compatibility Strategy

1. Keep OpenPGP.js v6 as the preferred browser bundle.
   - Works on HTTPS and secure loopback origins.
   - Does not support public HTTP.
   - Best matches current upstream support and avoids returning to the older asm.js bundle.

2. Add an OpenPGP.js v5 legacy fallback for public HTTP.
   - Load v6 by default.
   - When `window.isSecureContext === false`, load a vendored v5 bundle that still has pure-JS/asm.js fallback behavior.
   - Scope this fallback to account key and compose identity flows.
   - Test key generation, public-key parsing, identity linking, and compose submission separately under public HTTP and HTTPS.
   - Document that this fallback is for availability on HTTP and carries older-library compatibility and maintenance risk.

3. Add an explicit anonymous HTTP compose path.
   - Let users submit posts without `author_identity_id` when browser OpenPGP is unavailable.
   - Keep OpenPGP identity as an optional enhancement.
   - Make the UI clear that anonymous posts are not linked to a browser OpenPGP profile.

4. Avoid server-side identity for this fix.
   - Do not generate, store, or manage user identity private keys on the server.
   - Keep browser-held OpenPGP identity as the authored-post model.
   - Keep anonymous posting as the HTTP fallback when browser OpenPGP cannot run.
   - This preserves the current key-custody model and avoids introducing a separate trust model into the HTTP availability fix.

## Fix Plan

1. Keep HTTP as a supported access path.
   - Do not add an HTTP-to-HTTPS redirect as a required deployment behavior.
   - Keep the real PHP/static `DocumentRoot` usable on the port 80 vhost.
   - If an operator wants HTTPS, document it as an additional vhost or reverse-proxy option, not as a mandatory replacement for HTTP.

2. Do not add HSTS.
   - Avoid `Strict-Transport-Security` in the default deployment examples and runbooks.
   - HSTS can prevent users from reaching the site after certificate expiry, hostname changes, proxy mistakes, subdomain gaps, or other TLS failures.
   - If a deployment operator chooses to add HSTS independently, treat it as an advanced local policy outside the default plan.

3. Split the OpenPGP browser bundle into preferred and legacy paths.
   - Keep a vendored OpenPGP.js v6 bundle as the default for HTTPS and secure loopback origins.
   - Add a vendored patched OpenPGP.js v5 bundle for public HTTP origins.
   - Load exactly one bundle per page through a small deterministic loader.
   - Prefer v6 whenever `window.isSecureContext === true`.
   - Use v5 only when `window.isSecureContext === false` and the page needs account key or compose identity behavior.
   - Pin the exact v5 patch version and document the source, license, and update process.

4. Preserve browser-held OpenPGP identity where possible.
   - Keep `public/assets/browser_signing.js` using the narrow shared API surface: `openpgp.generateKey()` and `openpgp.readKey()`.
   - Normalize any v5/v6 differences in one local compatibility wrapper if needed.
   - Verify generated public keys still produce the same canonical identity form: `openpgp:<lowercase-fingerprint>`.
   - Do not add server-side identity generation or server-side private-key custody.

5. Add an anonymous compose fallback for HTTP.
   - If public HTTP cannot load or run either OpenPGP path, allow an explicit anonymous submit path instead of blocking compose.
   - Keep the fallback visible and intentional; do not silently downgrade an authored post to anonymous.
   - Submit with an empty `author_identity_id`, letting the existing server write path omit `Author-Identity-ID`.
   - Make clear in the compose UI that anonymous posts are not linked to a browser OpenPGP profile.
   - Keep HTTPS/secure-context authored compose behavior unchanged.

6. Improve the app-side failure message.
   - In `public/assets/browser_signing.js`, distinguish these cases:
     - `window.isSecureContext === false` and v5 fallback unavailable: show a clear message such as `Browser OpenPGP is unavailable on this HTTP page. You can post anonymously or switch to HTTPS for browser identity.`
     - `window.isSecureContext === false` and v5 fallback fails: show a clear message that the HTTP OpenPGP fallback failed, with anonymous posting and optional HTTPS as next steps.
     - `window.openpgp` missing on a secure context: keep a script-load/asset failure message.
   - This makes the OpenPGP limitation understandable without implying that all site access must move to HTTPS.

7. Add an optional HTTPS nudge.
   - On the account key/signing flow only, show a non-blocking notice when the page is on a public insecure context.
   - Phrase the notice as an optional reliability/security upgrade, not a requirement for using the whole site.
   - Do not block navigation, login, reading, composing, or other non-WebCrypto flows solely because the origin is HTTP.
   - When the v5 fallback is active, explain that HTTPS uses the preferred modern OpenPGP path.

8. Update production deployment documentation.
   - Keep `docs/examples/apache_vhost.conf` valid for HTTP serving.
   - Add an optional HTTPS example or notes for operators who have reliable certificate automation and hostname control.
   - Explicitly say not to enable forced HTTPS redirects or HSTS by default.
   - Call out that the preferred browser OpenPGP path uses v6 and requires a secure public origin because of browser WebCrypto restrictions.
   - Document that public HTTP uses the v5 legacy fallback where available, and otherwise supports anonymous posting.
   - Document that server-side user identity generation is intentionally out of scope.

9. Add focused tests.
   - Add a loader test proving secure contexts select v6 and public insecure contexts select v5.
   - Add browser-signing behavior tests for both v5 and v6 API compatibility if the existing JS harness can represent it.
   - Add compose tests for:
     - secure-context authored submit
     - public-HTTP authored submit through the v5 fallback
     - public-HTTP explicit anonymous submit when OpenPGP is unavailable
   - Add a smoke/documentation assertion that pages load the OpenPGP loader before `/assets/browser_signing.js`.
   - If deployment config tests are added later, assert the default HTTP vhost still serves the app and does not force redirects or emit HSTS.

10. Verify on staging/live.
   - `curl -I http://<host>/account/key/` should return the app response, not a forced HTTPS redirect.
   - `curl -I http://<host>/assets/<openpgp-loader>` should return the loader response over HTTP.
   - In a fresh browser profile, opening `http://<host>/account/key/` should either use the v5 fallback successfully or show anonymous/HTTPS options instead of `OpenPGP.js failed to load.`
   - On `http://<host>/compose/thread`, users should be able to choose an authored OpenPGP submit when the v5 fallback works or an explicit anonymous submit when it does not.
   - On `https://<host>/account/key/`, when HTTPS is configured by the operator, the account key flow should use v6 and generate a browser key without `OpenPGP.js failed to load.`

## Preferred Implementation Order

1. Add the OpenPGP loader and keep v6 as the secure-context default.
2. Add the pinned v5 legacy fallback for public HTTP.
3. Normalize the narrow browser-signing API surface across v5 and v6.
4. Add explicit anonymous compose fallback when browser OpenPGP is unavailable.
5. Improve diagnostic messages and add the non-blocking HTTPS nudge.
6. Update docs to keep HTTP supported, describe HTTPS as optional, and document the v5/v6 split.
7. Extend tests.
8. Verify HTTP authored fallback, HTTP anonymous fallback, and HTTPS v6 behavior.

The fix is a dual-path browser strategy plus an explicit anonymous fallback: keep modern OpenPGP.js v6 where it works, use patched OpenPGP.js v5 to preserve HTTP authored posting where possible, and avoid server-side identity custody. HTTPS remains recommended when an operator can support it reliably, but it should not be forced by the default plan.

## Implementation Log

- Slice 1: complete. Added `public/assets/openpgp_loader.js`, kept the current v6 bundle as the only selected bundle for now, and updated rendered pages/tests to load the loader before `browser_signing.js`. Verification: `php tests/run.php` passed all loader-related assertions but failed the existing `LocalAppSmokeTest::testFrontControllerShowsBusyErrorForExecutionLockContention` busy-page assertion.
