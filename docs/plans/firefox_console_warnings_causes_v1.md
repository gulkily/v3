# Firefox Console Warnings Cause Notes

## Scope

This note documents the Firefox console warnings observed while loading the local app at `http://127.0.0.1:8000`.

Original observed warnings:

- `asm.js type error: Asm.js optimizer disabled by 'asmjs' runtime option openpgp.min.js`
- `asm.js is deprecated and should no longer be used. Please recompile using WebAssembly to get the best performance. openpgp.min.js`
- `GET http://127.0.0.1:8000/favicon.ico [HTTP/1.1 404 Not Found]`
- `Source map error: Error: request failed with status 404 ... Source Map URL: openpgp.min.js.map`

## Causes

## Fix Status

- Fixed before this branch: `/favicon.ico` is now served by a static `public/favicon.ico` file and declared in the shared layout.
- Fixed in this branch step 1: the stale OpenPGP source-map pointer was removed from `public/assets/openpgp.min.js`, so Firefox DevTools no longer requests a missing `openpgp.min.js.map`.
- Pending in this branch: replace the asm.js OpenPGP bundle with a compatible non-asm.js browser bundle.

### OpenPGP.js asm.js warnings

Cause: the app serves the vendored OpenPGP.js browser bundle at `public/assets/openpgp.min.js`. The bundle identifies itself as `OpenPGP.js v5.11.3` at the top of the file and includes asm.js-style code paths. Firefox detects those asm.js sections while parsing the minified bundle and reports that asm.js optimization is disabled/deprecated.

The app loads this bundle on pages that need browser-side key generation, signing, or OpenPGP key parsing. Examples:

- `src/ForumRewrite/Application.php` includes `/assets/openpgp.min.js` for thread pages.
- `src/ForumRewrite/Application.php` includes `/assets/openpgp.min.js` for compose-thread, compose-reply, and account-key pages.
- `templates/layout.php` emits each configured script path with a deferred `<script>` tag.

Impact: this is a browser/runtime warning from Firefox about the third-party OpenPGP.js distribution format, not an application exception. The page can still run. The practical downside is console noise and potentially less optimized crypto code in Firefox.

Likely remediation options:

- Replace the vendored OpenPGP.js bundle with a newer build that no longer ships asm.js fallbacks, if available and compatible.
- Use an OpenPGP.js WebAssembly-capable distribution if the library/version provides one and it works with the app's browser-side signing flow.
- Accept the warning for now if behavior is correct, because the warning originates inside the third-party minified asset.

### Missing OpenPGP source map

Original cause: `public/assets/openpgp.min.js` ended with:

```js
//# sourceMappingURL=openpgp.min.js.map
```

Firefox DevTools followed that pointer and requested `/assets/openpgp.min.js.map`, but the repository only contained `public/assets/openpgp.min.js`; there was no matching `public/assets/openpgp.min.js.map`.

`public/router.php` lets existing files under `public/` be served directly by PHP's built-in server. Since the `.map` file does not exist, the request falls through to the application and returns a 404.

Fix: the trailing `sourceMappingURL` comment was removed from `public/assets/openpgp.min.js`. This preserves runtime behavior and removes the DevTools-only 404.

### Missing favicon

Cause: the browser automatically requests `/favicon.ico` when no favicon is declared in the document head. `templates/layout.php` does not include a favicon `<link>`, and `public/favicon.ico` is not present.

With the current `public/router.php` behavior, existing files under `public/` are served directly. Because `/favicon.ico` has no matching file, the request is handled by the app and returns the generic not-found response.

Impact: this is harmless browser noise, but it creates a visible 404 in the console/network panel.

Likely remediation options:

- Add `public/favicon.ico`.
- Or add an explicit favicon link in `templates/layout.php` pointing at an existing icon asset.
- Or intentionally leave it absent if the local development 404 noise is acceptable.

## Summary

The warnings come from three separate sources:

1. Firefox warning about asm.js code inside the vendored `openpgp.min.js` bundle.
2. Firefox DevTools requesting a source map named by `openpgp.min.js`, but `openpgp.min.js.map` is not present.
3. Browser default favicon discovery requesting `/favicon.ico`, but no favicon file or favicon link exists.

None of these warnings indicate that the forum application route handling or browser-signing code is throwing an application-level JavaScript error.
