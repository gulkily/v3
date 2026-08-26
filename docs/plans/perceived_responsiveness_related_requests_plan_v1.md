# Perceived Responsiveness Related Requests Plan V1

## Source Requests

`related_feature_requests.txt` lists:

- things that should be instant take too long
- clicking things in the menubar should be instant, not ~1 second
- clicking "post reply" should not take 2-3 seconds
- the "no css loaded yet" flag is annoying/disturbing

## Are These Actually Related?

Yes, but only at the user-experience level.

They are all reports that the site feels unsettled or slow before it reaches the state the user expected. They should be addressed under one perceived-responsiveness effort so the fixes share measurement, acceptance thresholds, and production verification.

With the added goal of reaching "mostly offline-capable PWA app" status, the requests become more tightly related: the same static artifacts, immutable assets, prefetch hints, cache headers, and client-side route warming that make clicks feel instant are also the foundation for a read-mostly offline experience.

They are probably not one single code bug:

- Menubar clicks are plain navigation links in `templates/partials/nav.php`. If these take about one second, the likely causes are route/render/read-model/static-artifact/server latency, cache behavior, or full document navigation cost.
- `Post reply` is a signed write path. Existing plans already implemented optimistic inline replies in `docs/plans/production_slow_actions_slice3_optimistic_reply_submit_plan_v1.md`, so a current 2-3 second report probably means production is not on that path, the user is using a non-inline/anonymous fallback path, browser identity setup is still blocking first feedback, or success navigation/reload is the perceived slow part.
- The "no css loaded yet" complaint is a different class: asset loading and loading-state copy. There is no literal `no css loaded yet` string in the current PHP templates, but the repo contains many repeatedly fingerprinted asset files in `public/assets`, which points to static asset hygiene as a credible related investigation.
- Offline posting is not the same problem as instant posting. The first PWA slice should make already-viewed public pages and core assets available offline, while preserving normal online canonical writes. Offline write queuing should be a later explicit product decision because identity, approval, git commits, and conflict handling are authoritative server-side.

## Existing Context

- `production_slow_actions_client_responsiveness_plan_v1.md` inventories slow-capable actions and recommends optimistic client behavior.
- `production_slow_actions_slice2_optimistic_reactions_plan_v1.md` is marked implemented for reaction clicks.
- `production_slow_actions_slice3_optimistic_reply_submit_plan_v1.md` is marked implemented for inline reply pending cards.
- `production_slow_actions_slice5_identity_prewarm_prepare_plan_v1.md` is marked implemented through quiet prewarm/no standalone prepare UI.
- `production_instant_busy_page_simple_plan_v1.md` covers fail-fast lock contention by setting `FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0`.
- `StaticArtifactBuilder::build()` copies fingerprinted assets, and the current `public/assets` directory contains many already-fingerprinted copies with repeated hashes. That is not necessarily the reported CSS flag, but it is a concrete asset-loading risk.
- There is no current service worker or web app manifest in the searched app files. PWA support should therefore be introduced as a new, narrow layer rather than assumed to exist.
- The production deploy model already favors static HTML artifacts and immutable/versioned assets. That aligns well with both service-worker caching and older browser prefetch hints.

## Solution Options

### Option A: Measure First, Then Patch The Hot Path

Add or use existing browser/server timing around menu navigation, reply first feedback, reply success navigation, CSS first paint, and asset load failure.

Pros:

- Avoids guessing which path is actually slow in production.
- Can distinguish backend latency from first-click identity setup, full-page navigation, cache miss artifact builds, and CSS 404/mis-cache behavior.
- Fits the existing `Server-Timing` and opt-in browser timing work.

Cons:

- Does not immediately remove the annoyance.
- Requires production reproduction or debug capture.

### Option B: Client-Side Perceived-Speed Fixes

Keep server behavior authoritative, but make the UI react immediately:

- Add pressed/loading state to nav links on click.
- Prefetch likely nav destinations after idle.
- Ensure inline reply shows pending feedback before waiting on the reply write.
- Keep the reply draft visible until confirmed.
- Hide transient identity-status messages once the actual action-specific pending state appears.

Pros:

- Directly addresses what the user sees.
- Low risk to canonical data.
- Builds on existing optimistic reply/reaction code.

Cons:

- Does not reduce actual backend latency.
- Navigation still replaces the full document unless a more ambitious partial navigation model is added.

### Option C: Backend And Static-Artifact Speed Fixes

Reduce the amount of server work needed for common GET and write paths:

- Verify nav routes are served from static HTML where intended.
- Skip same-request static artifact generation when it would contend for locks.
- Make read-model freshness checks fail fast or run outside user-facing requests where possible.
- Keep reply writes on incremental read-model update, especially when labels are present.
- Confirm production uses `FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0` or another fail-fast contention setting.

Pros:

- Improves real latency, not only perceived latency.
- Helps all clients, including non-JavaScript fallback paths.

Cons:

- More operational and architectural risk.
- Requires careful production validation around stale read models and artifact freshness.

### Option D: Asset Loading And CSS-State Hardening

Treat the CSS complaint as its own track:

- Find where "no css loaded yet" is emitted in production or static output.
- Remove alarming copy; if a CSS diagnostic is needed, make it operator-only or console-only.
- Stop recursive fingerprinting of already-fingerprinted assets.
- Clean generated fingerprint duplicates out of source/static asset directories.
- Add a smoke test that the rendered stylesheet URL resolves to HTTP 200 and is not a repeatedly fingerprinted filename.

Pros:

- Directly addresses the most disturbing visual symptom.
- Reduces chances of cache misses, large asset directories, and broken stylesheet references.

Cons:

- May not affect menubar or reply latency.
- Needs care to avoid deleting user/generated files blindly.

### Option E: Partial Navigation Or App-Shell Navigation

Intercept same-site nav clicks, fetch page HTML or a fragment, swap the main content, and keep the shell/CSS/JS loaded.

Pros:

- Can make menubar navigation feel genuinely instant after the first load.
- Avoids full CSS/JS re-evaluation on every nav click.

Cons:

- Highest complexity.
- Needs history, focus, scroll, error, script lifecycle, static/dynamic route, and non-JavaScript fallback design.
- Premature unless simpler caching/prefetch/static-route fixes fail.

### Option F: PWA Read Cache Plus Legacy Prefetch

Add a minimal PWA layer for modern browsers and a no-service-worker prefetch layer for older browsers:

- Add a web app manifest linked from the shared layout.
- Add a service worker that precaches the app shell essentials and runtime-caches visited public GET pages.
- Add a small offline fallback page for navigations not yet cached.
- Add `<link rel="prefetch">` hints for top-level nav destinations and critical assets.
- Add JavaScript idle prefetch with `fetch()` where available, falling back to link hints only.
- Keep all normal links and forms functional without service worker, JavaScript prefetch, or PWA install support.

Pros:

- Makes instant navigation and offline-read support reinforce each other.
- Service workers help modern browsers; link prefetch/static artifacts help older browsers and no-JavaScript clients.
- Keeps canonical writes online-only for the first version.
- Reduces repeated CSS/JS loading concerns by explicitly caching immutable fingerprinted assets.

Cons:

- Requires careful cache invalidation so users do not get stuck on stale pages.
- Needs an app version/cache version contract.
- Offline behavior can mislead users unless write actions clearly require a connection.
- Some older browsers ignore prefetch, so static HTML and HTTP cache headers still matter.

## Recommended Plan

Recommend a staged approach: Option A plus Option D first, then add Option F as the main strategic direction for "instant-feeling" navigation and mostly offline reads. Use targeted Option B/C fixes based on measurements. Defer Option E's app-shell/partial-navigation approach until the simpler PWA and prefetch layers prove insufficient.

The first offline target should be read-mostly:

- cached app shell assets
- cached public pages the user has visited
- cached top-level nav pages
- an offline fallback page
- online-only canonical writes with drafts preserved locally

Do not promise offline posting in this plan. Treat offline write queueing as a separate future plan.

### Stage 1: Reproduce And Classify

Goal:

- Determine whether each report is active in the current code and where the delay occurs.
- Establish a baseline for both online speed and offline-read readiness.

Work:

- Test a local thread page with JavaScript enabled:
  - click each nav item and record click-to-first-paint and click-to-load.
  - submit an inline signed reply and record click-to-pending-card, click-to-response, and click-to-canonical-page.
  - submit from `/compose/reply` and anonymous reply paths to see if the reported delay is from fallback behavior.
- Enable existing `debug_timing` paths where available.
- Capture `Server-Timing` for menu GETs and reply APIs.
- Inspect the rendered stylesheet URL and confirm it resolves.
- Check whether the browser supports service workers, Cache Storage, web app manifests, and link prefetch on the target browser set.
- Define the minimum older-browser fallback target:
  - normal links keep working
  - static artifacts and HTTP cache headers still help
  - `<link rel="prefetch">` may improve warm nav where supported
  - no core behavior depends on service workers

Acceptance:

- Each request is classified as one of: backend GET latency, backend write latency, identity-prep latency, success-navigation latency, asset/CSS load problem, or stale production deployment.
- The plan has a target browser matrix with modern PWA support, partial prefetch-only support, and baseline no-prefetch behavior.

### Stage 2: CSS And Asset Hygiene

Goal:

- Remove the disturbing CSS-loading state and prevent stylesheet cache/path churn before adding any PWA cache layer.

Work:

- Locate the exact production source of "no css loaded yet"; check static artifacts in addition to PHP templates.
- Replace user-facing CSS diagnostic copy with no visible message, a quiet fallback style, or an operator-only diagnostic.
- Update asset fingerprint copying so it ignores already-fingerprinted asset filenames.
- Add cleanup guidance or a script for generated duplicate fingerprint files after confirming which files are source assets.
- Define cache classes:
  - immutable fingerprinted assets: long-lived cacheable
  - canonical source asset paths such as `/assets/site.css`: short-lived or redirected/resolved to fingerprinted paths
  - HTML pages: network-first or stale-while-revalidate, never immutable
  - API and write routes: no-store
- Add smoke coverage for:
  - stylesheet link matches one hash segment, for example `/assets/site.<hash>.css`
  - the linked asset maps back to `/assets/site.css`
  - static artifact build does not create `site.<hash>.<hash>.css`
  - PWA/service-worker asset manifests do not include recursively fingerprinted files

Acceptance:

- No normal page shows "no css loaded yet."
- Rendered stylesheet links resolve.
- Static build does not multiply fingerprint hashes.
- Asset paths are stable enough to be safely precached.

### Stage 3: PWA Foundation

Goal:

- Add a minimal, read-mostly PWA layer without changing canonical write behavior.

Work:

- Add `public/manifest.webmanifest` with conservative app metadata, icons, theme color, `start_url`, and `display`.
- Link the manifest from `templates/layout.php`.
- Add `public/offline.html` as a small static fallback page using inline critical styling or the already-cached stylesheet.
- Add `public/service_worker.js` with an explicit version string based on app version or build hash.
- Register the service worker from a small deferred script loaded by the shared layout.
- Precaching should include only:
  - `/`
  - `/about/`
  - `/users/`
  - `/tools/`
  - `/offline.html`
  - current fingerprinted CSS and small first-party JS assets
  - favicon and manifest
- Runtime caching should include public same-origin `GET` HTML pages that are safe for anonymous viewing.
- Do not cache:
  - `/api/*`
  - `POST` responses
  - account/key pages with identity-sensitive state unless explicitly reviewed
  - pages requested with cookies if they contain personalized content
- Add an offline banner or understated status only when a navigation fails and the service worker serves cached/fallback content.

Acceptance:

- A modern browser can load the board once, go offline, reload the board, and see cached content or the offline fallback.
- Cached assets render with CSS; no "no css loaded yet" message appears offline.
- Write actions while offline fail clearly and preserve drafts where compose draft support already exists.
- API responses and signed/private state are not served stale from the service worker.

### Stage 4: Menubar Navigation And Prefetch

Goal:

- Make top-level nav clicks feel immediate in modern browsers and improve warm navigation in older browsers where possible.

Work:

- Confirm whether `/`, `/about/`, `/users/`, `/tools/`, and `/account/key/` are static, dynamic, or mixed in production.
- For static-safe public routes, prefer direct static artifacts and cacheable fingerprinted assets.
- Add lightweight click feedback to `.nav-link` for full-page navigations.
- Add declarative hints in the shared layout for stable public destinations:
  - `<link rel="prefetch" href="/">`
  - `<link rel="prefetch" href="/about/">`
  - `<link rel="prefetch" href="/users/">`
  - `<link rel="prefetch" href="/tools/">`
- Avoid prefetching identity/account pages unless reviewed for personalized state.
- Add an idle prefetch script for browsers with `fetch`, `requestIdleCallback`, or a timeout fallback:
  - only prefetch same-origin `GET` routes
  - only prefetch when the connection is not marked constrained where `navigator.connection` exists
  - do not prefetch when `Save-Data` is enabled
  - keep request count small
- Let the service worker populate its runtime cache from these prefetches in modern browsers.
- In older browsers, rely on link prefetch where supported and ordinary HTTP cache/static artifacts where not.
- If a route still takes about one second, use `Server-Timing` to identify read-model rebuild, stale marker, lock wait, template render, or artifact generation.

Acceptance:

- Nav click first feedback occurs within 100 ms.
- Cached/static nav destination reaches first contentful render within an agreed threshold, initially 300-500 ms on a warm connection.
- If backend work blocks the route, the response exposes timing enough to identify the blocking step.
- In a service-worker browser, revisiting prefetched nav destinations works from cache while offline.
- In a no-service-worker browser, nav still works normally and benefits from static artifacts/cache headers or link prefetch where supported.

### Stage 5: Reply Submit

Goal:

- Make `Post reply` show meaningful feedback immediately and avoid a 2-3 second dead period.

Work:

- Verify current production includes the implemented optimistic inline reply flow.
- Confirm inline signed replies call `/api/create_reply` and render a pending card before the fetch resolves.
- Confirm anonymous replies and standalone `/compose/reply` are intentionally fallback paths; decide whether to add optimistic behavior there too.
- Measure first signed action separately from later signed actions to isolate identity setup.
- If the slow part is success navigation, consider in-place canonical reconciliation instead of navigating after success.
- If the slow part is server write time, apply backend fixes from Stage 6 while preserving pending UI.
- For offline/PWA behavior:
  - do not attempt canonical reply submission while offline in the first version
  - detect offline before signed submit when possible
  - keep the draft available and show a clear online-required message
  - consider storing an explicit local draft marker, not a hidden outbox, if the user tried to submit offline

Acceptance:

- Inline signed reply first visible feedback appears within 100 ms after identity readiness.
- A fresh identity setup shows clear user-initiated progress rather than silence.
- Failed replies keep the draft recoverable.
- Success does not leave duplicate pending/canonical reply cards.
- Offline reply attempts do not appear posted and do not lose text.

### Stage 6: Backend Contention And Real Latency

Goal:

- Reduce actual server wait time where measurement shows backend delay.

Work:

- Confirm production lock timeout policy and whether busy pages fail fast.
- Audit reply writes for incremental read-model update fallback cases.
- Skip or defer best-effort static artifact generation when it would extend a user response.
- Add or tighten timing buckets for git commit, read-model update, artifact invalidation, and static artifact generation.

Acceptance:

- Reply API timing identifies git, read-model, lock, and artifact components.
- Common reply writes avoid full read-model rebuilds.
- Lock contention returns a clear immediate busy response rather than a silent stall.

### Stage 7: PWA Verification And Release Guardrails

Goal:

- Keep the PWA layer from making freshness, privacy, or offline-write semantics worse.

Work:

- Add smoke tests or Playwright checks for:
  - manifest link exists and resolves
  - service worker script resolves with JavaScript content type
  - offline fallback exists
  - fingerprinted CSS is in the precache list exactly once
  - `/api/version`, `/api/create_reply`, and other API routes are not cached as offline successes
  - service-worker cache version changes when app assets change
- Add a runbook note for clearing/rotating service-worker caches after deploys.
- Add a manual test matrix:
  - modern Chromium/Firefox/Safari with service worker
  - browser with service worker disabled
  - older browser with only normal links/cache
  - slow connection with `Save-Data` or constrained network settings where available

Acceptance:

- Modern browser passes install/offline-read smoke checks.
- Older browser still gets normal static/public pages and no broken PWA-only dependency.
- A deploy can invalidate stale service-worker caches predictably.

## Non-Goals

- Do not weaken canonical write, identity, approval, or git-history guarantees.
- Do not remove non-JavaScript compose/reply fallbacks.
- Do not convert the site to a single-page app in the first pass.
- Do not delete generated asset files until the source/generated boundary is confirmed.
- Do not implement offline canonical posting in this plan.
- Do not cache authenticated, identity-sensitive, or approval-sensitive responses without a separate privacy review.

## Open Questions

- Is the "no css loaded yet" flag from current production HTML, old static artifacts, browser extension/debug tooling, or a page not represented by the PHP templates?
- Is the reported `Post reply` path inline signed reply, standalone signed reply, or anonymous reply?
- Are menubar clicks slow on all routes or only routes that enter PHP/read-model work?
- Should standalone `/compose/reply` get optimistic API submission, or is canonical redirect acceptable there?
- What production latency target should count as "instant" for warm nav and signed writes?
- Which browsers are specifically meant by "older browsers," and do they support `<link rel="prefetch">`, only normal HTTP caching, or neither?
- Should the PWA install prompt/manifest use the forum brand only, or expose a more app-like short name?
- Should cached offline pages show their last-updated time?
- How aggressive should prefetch be on metered or slow connections?
- Is `/account/key/` allowed to be cached as an app shell page, or should all account/identity surfaces remain network-only?
