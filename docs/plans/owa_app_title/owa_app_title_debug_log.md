# owa_app_title — debug log

## 2026-11-25T07:10Z — closeout

- Instructions used: AGENTS.md (one-shot, feedback search, tests, effort study); the `owa_start_url` record as the shape.
- Essential: the live response headers for `/manifest.webmanifest` (both `no-store` and `max-age=172800`), and `cacheableRequest()` in `public/service_worker.js`, which skips query URLs, so the versioned link needs no worker change.
- Friction: a first test assertion rendered `/manifest.webmanifest?v=…` through `Application`, which does not serve the manifest (`FrontController` does); replaced by the local server replay.
- Open: whether Firefox on iPhone reads `apple-mobile-web-app-title`.

*— Claude · 2026-11-25 UTC*
