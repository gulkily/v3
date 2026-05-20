# Firefox Console Warnings Cause Notes

## Scope

This note documents the Firefox console warnings observed while loading the local app at `http://127.0.0.1:8000`.

Original observed warnings:

- `asm.js type error: Asm.js optimizer disabled by 'asmjs' runtime option openpgp.min.js`
- `asm.js is deprecated and should no longer be used. Please recompile using WebAssembly to get the best performance. openpgp.min.js`
- `GET http://127.0.0.1:8000/favicon.ico [HTTP/1.1 404 Not Found]`
- `Source map error: Error: request failed with status 404 ... Source Map URL: openpgp.min.js.map`

## Fix Status

- Fixed before this branch: `/favicon.ico` is now served by a static `public/favicon.ico` file and declared in the shared layout.
- Fixed in branch step 1: the stale OpenPGP source-map pointer was removed from `public/assets/openpgp.min.js`, so Firefox DevTools no longer requests a missing `openpgp.min.js.map`.
- Fixed in branch step 2: `public/assets/openpgp.min.js` was updated from OpenPGP.js v5.11.3 to v6.3.0, and the matching `public/assets/openpgp.min.js.map` was added.

## Causes

### OpenPGP.js asm.js warnings

Original cause: the app served the vendored OpenPGP.js browser bundle at `public/assets/openpgp.min.js`. The bundle identified itself as `OpenPGP.js v5.11.3` at the top of the file and included asm.js-style code paths. Firefox detected those asm.js sections while parsing the minified bundle and reported that asm.js optimization was disabled/deprecated.

The app loads this bundle on pages that need browser-side key generation, signing, or OpenPGP key parsing. Examples:

- `src/ForumRewrite/Application.php` includes `/assets/openpgp.min.js` for thread pages.
- `src/ForumRewrite/Application.php` includes `/assets/openpgp.min.js` for compose-thread, compose-reply, and account-key pages.
- `templates/layout.php` emits each configured script path with a deferred `<script>` tag.

Impact: this was a browser/runtime warning from Firefox about the third-party OpenPGP.js distribution format, not an application exception. The page could still run. The practical downside was console noise and potentially less optimized crypto code in Firefox.

Fix: the vendored browser bundle was replaced with OpenPGP.js v6.3.0 from the npm `openpgp` package. The replacement bundle still provides `window.openpgp.generateKey` and `window.openpgp.readKey`, which are the OpenPGP APIs used by `public/assets/browser_signing.js`, and the minified file no longer contains `asm.js` or `use asm` markers.

Compatibility note: this is the only warning fix with meaningful browser compatibility risk. OpenPGP.js v6 is a newer major version than v5.11.3, so older browsers should be validated against the account-key, compose, inline reply, and reaction flows before release if they are in the support target.

### Missing OpenPGP source map

Original cause: `public/assets/openpgp.min.js` ended with:

```js
//# sourceMappingURL=openpgp.min.js.map
```

Firefox DevTools followed that pointer and requested `/assets/openpgp.min.js.map`, but the repository only contained `public/assets/openpgp.min.js`; there was no matching `public/assets/openpgp.min.js.map`.

`public/router.php` lets existing files under `public/` be served directly by PHP's built-in server. Since the `.map` file does not exist, the request falls through to the application and returns a 404.

Step 1 fix: the trailing `sourceMappingURL` comment was removed from `public/assets/openpgp.min.js`. This preserved runtime behavior and removed the DevTools-only 404.

Step 2 update: the OpenPGP.js v6.3.0 replacement bundle includes a source-map pointer, and the matching `public/assets/openpgp.min.js.map` file is now present. Firefox DevTools can request the map without producing a 404.

### Missing favicon

Original cause: the browser automatically requested `/favicon.ico` when no favicon was declared in the document head. `templates/layout.php` did not include a favicon `<link>`, and `public/favicon.ico` was not present.

With the current `public/router.php` behavior, existing files under `public/` are served directly. Because `/favicon.ico` had no matching file, the request was handled by the app and returned the generic not-found response.

Impact: this was harmless browser noise, but it created a visible 404 in the console/network panel.

Fix: `public/favicon.ico` was added, and `templates/layout.php` now declares `<link rel="icon" href="/favicon.ico" sizes="32x32">`.

## Summary

The original warnings came from three separate sources:

1. Firefox warning about asm.js code inside the vendored `openpgp.min.js` bundle. Fixed by upgrading the vendored OpenPGP.js bundle.
2. Firefox DevTools requesting a source map named by `openpgp.min.js`, but `openpgp.min.js.map` was not present. Fixed first by removing the stale pointer, then by adding the matching map with the upgraded bundle.
3. Browser default favicon discovery requesting `/favicon.ico`, but no favicon file or favicon link existed. Fixed before this branch by adding `public/favicon.ico` and a layout favicon link.

None of these warnings indicate that the forum application route handling or browser-signing code is throwing an application-level JavaScript error.
