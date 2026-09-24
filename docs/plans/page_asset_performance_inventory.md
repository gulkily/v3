# Non-Minification Page Asset Performance Inventory

This is the exhaustive source-level inventory for the current application. It excludes minification; validate byte and request impact with browser/network measurements before implementation.

## Autonomous Continuation

The user asked the agent to continue this inventory autonomously while the workstation is unattended. Continue in the order that provides the safest measured reduction in delivered or parsed assets; commit each verified change and update this document in the same commit. Preserve readable source, no-JavaScript behavior, theme behavior, static-artifact correctness, and unrelated working-tree files. Record any item deliberately retained or deferred with its reason before moving on.

## CSS Ownership Candidates

- [x] **About** — `.about-section` and its glyph rules now load from fingerprinted `about.css` only for `templates/pages/about.php`.
- [x] **Tools/bookmarklets** — `.tool-launcher-*` now loads from fingerprinted `tools.css` only on `tools.php` and `bookmarklets.php`, including its mobile rules.
- [x] **Tag directory** — `.tag-group*`, `.tag-thread-list`, and the tag-directory card now load from fingerprinted `tags.css` only for `templates/pages/tags.php`.
- [x] **Activity manifests** — `.activity-commit-manifest*` now loads from fingerprinted `activity.css` on classic Activity and Forte Activity, including lazy-loaded manifest details.
- [x] **Invitations** — `.invitation-destination-*` now loads from fingerprinted `invitations.css` only for `templates/pages/invites.php`, alongside its existing invitation scripts.
- [x] **Account/profile identity** — `.account-key-*` now loads from shared fingerprinted `identity.css` on account, profile, invitation, thread, and post identity-detail surfaces.
- [x] **Pending approvals** — the responsive approvals table and `.pending-approvals-*` now load from fingerprinted `pending-approvals.css` only for `users_pending.php`, alongside its route-specific script.
- [x] **Compose controls** — inline-reply, standalone compose, normalization, compact board composer, and reply-context rules now share fingerprinted `compose.css` on Board, Thread, Compose Thread, and Compose Reply routes.
- [x] **Post/thread interactions** — `.post-card-actions`, `.thread-reaction-*`, and handoff/analysis styles now load from fingerprinted `content-interactions.css` on post and thread routes only.
- [x] **Thread-list/density controls** — `.thread-density-*` and compact-list rules now load from fingerprinted `thread-list.css` only on Board and Tag views.
- **SQLite viewer** — `.sqlite-*` is exclusive to the SQLite tool and should leave the standard shared stylesheet with its existing route-specific JS/WASM runtime.
- **Instance/backup, profile/user-directory, feature-flag, and LLM-exchange selector families** — inventory their exact template ownership before extraction; keep any cross-route utility rule in the base stylesheet.
- **Already isolated** — Forte is in `forte.css`; explicit themes are in `theme-<name>.css`. Do not duplicate them into page stylesheets.

## Page and Render-Path Candidates

- **Critical CSS** — `TemplateRenderer::criticalCss()` currently inlines an 8,306-byte fixed prefix of `site.css` into every standard page. Retain only true above-the-fold shared rules after browser measurement; route-only rules now belong in cached page files.
- **Shared layout markup** — measure repeated nav, theme-menu, status-bar, and inline configuration payload size. Keep accessibility/navigation requirements intact; remove or defer only route-irrelevant data.
- **Board/thread/tag payloads** — measure card count, body-preview length, reaction/analysis markup, and manifest detail. Use pagination, progressive disclosure, or on-demand detail only where first-view content is not required.
- **Static pages** — verify generated HTML does not embed per-request state unnecessarily and uses the same route asset contract as dynamic pages.
- **Data URI glyphs** — page-only glyphs become page-only parse cost when their owning CSS is extracted; shared glyphs should remain shared unless measurements justify external assets.

## JavaScript and Runtime Candidates

- **Layout-wide scripts** — audit completed for `compose_draft_clear.js` and `invite_navigation.js`: retain both globally. The former consumes a post-submit cookie on arbitrary landing routes; the latter preserves the current route when the global Invite nav link is clicked. Keep `theme_toggle.js` global; `thread_density_toggle.js` is already conditional.
- **Route scripts** — preserve explicit `scriptPaths` contracts for reactions, inline replies, invitations, approvals, feature flags, bookmarklets, SQLite, and Forte; audit whether each is loaded only where its DOM exists.
- **OpenPGP/browser signing** — large OpenPGP and browser-signing assets should stay strictly on authenticated or compose flows and load only after intent when existing lazy loaders permit.
- **SQLite runtime** — `sql-wasm.wasm`, `sql-wasm.js`, `sqlite_viewer.js`, and the query catalog remain tool-only; avoid preloading them elsewhere.
- **Heavy reader scripts** — Forte paned-reader assets remain Forte-only; defer non-visible panes/details until interaction where behavior permits.
- **Inline head code** — measure the theme and density resolver payload separately; retain only paint-critical logic and move noncritical synchronization to deferred scripts.

## Asset Delivery and Artifact Candidates

- **Fingerprint caching** — retain fingerprinted immutable asset URLs; add or verify long-lived cache headers and compression at the web-server/CDN layer without changing source readability.
- **Compression** — enable Brotli or gzip for CSS, JavaScript, HTML, JSON, and SVG responses; this is transfer compression, not minification.
- **Source maps** — the tracked `openpgp.min.js.map` is about 1.8 MB. Keep it available for development/debugging but exclude it from production/static artifacts unless production debugging explicitly requires it.
- [x] **Static artifact copying** — static rendering now copies only fingerprinted assets referenced by each rendered artifact; development-only maps and stale/generated assets are not swept into releases.
- **Stale fingerprint chains** — audit public asset directories and release outputs for recursively fingerprinted leftovers; serve only canonical source or current fingerprinted release assets.
- **Preload priority** — preload only render-critical base/active theme assets. Avoid preloading route scripts, alternate themes, or tools runtimes that compete with first render.
- **Connection and cache policy** — measure cache hit rate, conditional requests, HTTP/2 or HTTP/3 multiplexing, and third-party absence before adding resource hints.

## Measurement and Guardrails

- Record uncompressed and transferred bytes, request count, CSS/JS parse-execution time, LCP, and cache-hit behavior for representative Board, About, Thread, Tools, Account, SQLite, Forte, and static routes.
- Establish per-route budgets before each extraction; reject a split that raises first-load requests or LCP without a compensating measured benefit.
- Test JavaScript-disabled rendering, every explicit theme, Auto/System behavior, authenticated/private routes, responsive widths, and static artifacts.
- Preserve readable source files, fingerprints, source maps in development, accessibility, and existing no-JavaScript fallback behavior.
