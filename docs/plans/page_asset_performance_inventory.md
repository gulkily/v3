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
- [x] **SQLite viewer** — 3,588 bytes of `.sqlite-*` and SQLite data-role rules now load from fingerprinted `sqlite.css` only on the SQLite tool; `site.css` is 3,588 bytes smaller for every standard route. The SQLite route retains the existing tool-only JS/WASM runtime and its no-JavaScript download links.
- [x] **Instance/backup, profile/user-directory, feature-flag, and LLM-exchange selector families** — audit found no dedicated backup or user-directory CSS, while `.agent-label` is shared across profile, activity, content, and Forte surfaces and remains global. The `codebase-*` family (1,281 bytes) now loads as fingerprinted `tool-details.css` only on System State, Feature Flags, and LLM Exchanges.
- **Already isolated** — Forte is in `forte.css`; explicit themes are in `theme-<name>.css`. Do not duplicate them into page stylesheets.

## Page and Render-Path Candidates

- [x] **Critical CSS: account-key reduction** — browser-rendered Board HTML measured a 6,337-byte fixed inline prefix. The 593-byte public-key textarea/focus block is account-only, so it now loads from fingerprinted `account.css` on `account_key.php`; every standard page’s inline critical CSS is 5,744 bytes.
- [x] **Critical CSS: shared-header audit** — deferred after the account-key reduction: the remaining 5,744-byte prefix is the global shell, navigation, theme menu, and card baseline. It needs a filmstrip/LCP comparison across all themes before further trimming; do not trade a visible first-paint regression for source bytes.
- [x] **Shared layout markup** — the only safely route-irrelevant inline branch was the density resolver, removed from non-Board/Tag pages. Navigation, the theme menu, status-bar accessibility markup, and theme resolver are shared or paint-critical and remain.
- [x] **Board/thread/tag payloads** — the measured Board contains 139 cards and 139 previews (85,479-byte HTML). Classic thread HTML is 24,357 bytes. No truncation was made: changing first-view card/previews requires a pagination or disclosure product decision.
- [x] **Static pages** — `LocalAppSmokeTest::testStaticArtifactBuilderWritesApacheFriendlyArtifactLayout` verifies fingerprinted referenced assets are copied for generated routes. The builder excludes per-request authentication state by rendering public routes.
- [x] **Data URI glyphs** — About glyphs are already in route-only `about.css`; remaining data-URI glyphs are explicit `theme-word97.css` decorations, loaded only when that theme is active. Retained to preserve its visual system without adding requests.

## JavaScript and Runtime Candidates

- [x] **Layout-wide scripts** — audit completed for `compose_draft_clear.js` and `invite_navigation.js`: retain both globally. The former consumes a post-submit cookie on arbitrary landing routes; the latter preserves the current route when the global Invite nav link is clicked. Keep `theme_toggle.js` global; `thread_density_toggle.js` is already conditional.
- [x] **Route scripts** — audited explicit `scriptPaths` contracts for reactions, inline replies, invitations, approvals, feature flags, bookmarklets, SQLite, and Forte. SQLite/WASM, Forte readers, bookmarklets, feature flags, and pending-directory approval scripts remain route-only. Profiles without a signed approval form no longer explicitly request `openpgp_loader.js`, `browser_signing.js`, or `pending_approvals.js`; approved-members mode may still inject its authentication bundle. `compose_draft_clear.js`, `invite_navigation.js`, and `theme_toggle.js` remain global for their documented cross-route behavior.
- [x] **OpenPGP/browser signing** — reviewed against the route contracts: compose/account and signed interaction flows retain the loader; Board uses the existing intent-lazy loader. Do not move authenticated-session restoration scripts out of their required private-mode render path.
- [x] **SQLite runtime** — remains tool-only and is not preloaded elsewhere. The SQLite page’s 223,393 bytes of directly referenced source assets are supplemented only on that route by the 659,806-byte `sql-wasm.wasm` (322,177 bytes with gzip).
- [x] **Heavy reader scripts and payload** — Forte reader scripts are route-only (75,152 source bytes), but the all-content pane renders 582 thread articles and 522 reply nodes: 2,421,569-byte HTML (234,492 bytes with gzip). Deferring hidden panes needs a dedicated no-JavaScript/progressive-disclosure design, so it is deliberately deferred rather than silently removing content.
- [x] **Inline head code: density resolver** — the layout’s inline resolver measured 2,836 source bytes. The density branch is now emitted only when the enabled density control is rendered on Board/Tag, preserving its pre-paint compact-mode behavior while removing 592 bytes from other rendered pages. The theme resolver remains inline because it selects the active theme before first paint.

## Asset Delivery and Artifact Candidates

- [x] **Fingerprint caching investigation** — `FrontController` serves validated fingerprints with `Cache-Control: public, max-age=31536000, immutable`; static HTML revalidates by ETag. Apache may directly serve physical `/assets` files, so transfer-header and CDN policy remain host configuration rather than duplicated in readable source.
- [x] **Compression investigation** — no repository-owned Apache/CDN compression configuration exists. Brotli or gzip for CSS, JavaScript, HTML, JSON, and SVG remains an operator/vhost task, because enabling a module-specific filter without the target host’s enabled-module and proxy validation would be unsafe. This is transfer compression, not minification.
- [x] **Source maps** — the tracked `openpgp.min.js.map` is 1,847,778 bytes. It remains available for development/debugging but is not referenced by rendered HTML, so referenced-asset static releases do not copy it.
- [x] **Static artifact copying** — static rendering now copies only fingerprinted assets referenced by each rendered artifact; development-only maps and stale/generated assets are not swept into releases.
- [x] **Stale fingerprint chains** — removed 491 ignored recursive fingerprint artifacts (66,239,690 bytes), reducing `public/assets` from 69 MB to 4.0 MB. `AssetFingerprint` now recognizes only a single hash as current and redirects both ordinary and recursive stale URLs to the canonical current fingerprint; static releases continue to copy only referenced current assets.
- [x] **Preload priority** — verified the layout preloads only fingerprinted `site.css` and loads the active theme at high priority. Route CSS/scripts, alternate themes, and tool runtimes are not preloaded; alternate themes are warmed at low priority after interaction logic permits it.
- [x] **Connection and cache policy** — source has no browser-side third-party asset URLs. Cache-hit rates and HTTP/2/HTTP/3 multiplexing require production/CDN observability and are deferred to the operator rather than guessed from local development.

## Measurement and Guardrails

- [x] **Representative route payload baseline** — local Chromium/`curl` capture after these reductions, with direct unique fingerprint references and local gzip estimates (actual transfer, parse/execution time, LCP, and cache-hit behavior remain host/browser measurements):

  | Route | HTML bytes | Referenced assets | Asset source bytes | HTML gzip bytes |
  | --- | ---: | ---: | ---: | ---: |
  | Board | 85,479 | 12 | 154,433 | 16,221 |
  | About | 20,724 | 9 | 146,063 | 5,127 |
  | Thread | 24,357 | 14 | 188,482 | 5,371 |
  | Tools | 21,358 | 9 | 143,958 | 4,833 |
  | Account | 29,193 | 10 | 146,102 | 6,490 |
  | SQLite | 22,218 | 11 plus WASM | 883,199 | 5,178 |
  | Forte | 2,421,569 | 5 | 75,152 | 234,492 |
- Establish per-route budgets before each extraction; reject a split that raises first-load requests or LCP without a compensating measured benefit.
- Test JavaScript-disabled rendering, every explicit theme, Auto/System behavior, authenticated/private routes, responsive widths, and static artifacts.
- Preserve readable source files, fingerprints, source maps in development, accessibility, and existing no-JavaScript fallback behavior.
