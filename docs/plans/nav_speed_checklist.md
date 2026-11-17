# Nav Speed Checklist

Working checklist for cutting click-to-done time on navbar links. It is not a Railspike artifact and approves no implementation. Each item below is its own Railspike cycle, following AGENTS.md. Record progress, checks and limitations here.

Requested by the maintainer on 2026-11-16 in the ZenMemes thread [thread-20261116184900-5ca26894](https://zenmemes.com/threads/thread-20261116184900-5ca26894) ("still feels sluggish on prod"). Claude drives the cycles and approves steps on the maintainer's behalf, except where an item below says the maintainer decides. Cycles 2 and 3 run as one-shots, one after the other.

Every change ships behind a feature flag. Pages keep working without JavaScript, and offline mode keeps looking and behaving the way it does online.

## Baseline

Measured 2026-11-16 from a cloud box (headless Chrome, 375 px viewport, a dummy cookie so every request went through PHP). Medians:

| Part | Full CPU | 4x CPU slowdown |
| --- | --- | --- |
| Click to request start (service worker pass-through, warm) | 5–15 ms | 6–15 ms |
| Network round trip from the cloud box | 105–130 ms | same |
| Server: Board / Recent / Users, About, Tools, thread | 110–120 / 50–60 / 10–30 ms | same |
| Response end to load | ~150 ms | ~230 ms |
| Click to load | ~300 ms | ~440 ms |

At 4x, browser work after the response: script evaluation 60 ms, HTML parse 41, style recalc and stylesheet parse 61, layout 25, paint 10. Each page is 137 KB of HTML (40 KB inline CSS), about 30 deferred scripts and 7 stylesheets. `theme-nav-icons.css` is 358 KB (all eight themes' icons). `browser_signing.js` (165 KB) and the sqlite viewer load on pages with no composer.

Not measured: an approved, logged-in operator session. After load it makes up to four more requests (`/api/auth_status`, `/api/inbox/unread`, the deploy check `POST /tools/deploy/`, messaging unread), and the navbar keeps changing until they return.

## Decided by the maintainer

- 2026-11-16: never flash stale content. No swapping a page out from under the reader after it shows.
- 2026-11-16: reusing a cached copy is fine. Show the cached copy, load the new copy in the background, then either keep it queued for the next click or tell the reader a newer version is there, using the existing new-version notice (`version_check.js`, `[data-role="app-version-banner"]` in `templates/layout.php`).

## Cycles

- [ ] **1. `nav_timing`** (skipped by the maintainer, 2026-11-17: "skip nav_timing, one-shot nav_quick_wins and nav_cached_copy sequentially, pause before page_weight"): measure on the maintainer's phone. Add navigation timing (TTFB, DOM ready, load, and when the navbar's after-load requests finish) to view entries in the usage log, and a `Server-Timing` header on page responses.
  - Then: wait for a deploy and a day of real taps before planning cycle 2.
- [x] **2. `nav_quick_wins`** ([one-shot record](./nav_quick_wins/nav_quick_wins_one_shot.md)): navigation preload in `service_worker.js`; on `pointerdown` on a nav link, start fetching the page and hand the response to the navigation that follows; mark the tapped nav item active on click. Flag "Fast navigation" (`FORUM_FAST_NAVIGATION_ENABLED`, on by default).
  - Status: merged into `main` 2026-11-17 (PR #291, merge `aa8190d3`). No progress bar (the highlight is the reaction). Each early-fetched tap also sends a discarded preload request. Release check on an iPhone pending.
- [x] **3. `nav_cached_copy`** ([one-shot record](./nav_cached_copy/nav_cached_copy_one_shot.md)): navbar destinations show the cached copy right away. A fresh copy loads in the background. It is kept for the next click, or the new-version notice says a newer version is there. The shown page is never swapped.
  - The notice already tracks content: the page's `app-version` meta is the content repository's HEAD (`ReadModelMetadata::repositoryHead`), and `version_check.js` polls `/api/version` (15 s, backing off to 2 min). A cached copy's own `app-version` tells whether it is behind.
  - Status: merged into `main` 2026-11-17 (PR #292, merge `aa8190d3`); flag "Navbar pages from last copy" (`FORUM_NAV_CACHED_COPY_ENABLED`, off by default). Both: the fresh copy is queued for the next click, and the notice appears when the version changed. Under the flag, a remembered newer version shows the notice instead of redirecting. Release check pending.
  - Was for Step 1 (one-shot instead): which of the two (queue or notice) is the default, whether a repo-wide HEAD change is too coarse for one page (any new post anywhere marks every cached page behind), how viewer state (unread count, approval) stays correct on a cached copy, and how this fits the offline cache.
- [ ] **4. `page_weight`** (paused; estimate below suggests holding off for navbar speed): split `theme-nav-icons.css` by icon set, move inline CSS into a cached file, load composer, outbox, sqlite and signing scripts only on pages that use them.
- [ ] **5. In-place main swap (Turbo-style)**: only if 1–4 aren't enough. Step 1 only, left for the maintainer.

## page_weight estimate (2026-11-17)

Measured before starting cycle 4, at the maintainer's request. Prod HTML for Board, Recent and Users (fetched with a cookie, so the PHP path) and their assets were served from a local server with the same cache headers, so network time was zero. Chrome on Linux, 390 px viewport, 4x CPU slowdown, warm cache, service worker blocked. Variants: inline CSS moved to a cached file; `theme-nav-icons.css` cut to the Gloss set (361 KB to 57 KB raw, 21 KB to 5 KB gzip); composer, signing and sqlite scripts removed where unused (Users and Recent: 27 to 17 scripts; Board: 36 to 33); all three. Load and paint medians over 20 interleaved runs swung by plus or minus 30 ms, so per-category main-thread time was read from traces (8 runs each).

| Change | Per click (warm cache, 4x CPU) | First visit only |
| --- | --- | --- |
| Inline CSS to a cached file | HTML parse 5–9 ms less, style and paint a few ms; HTML 22 KB to 12 KB gzip (`/users/`) | none |
| Icon stylesheet split by set | about 0 ms: Chrome reuses the parsed stylesheet across navigations (stylesheet parse was about 1 ms either way) | 16 KB less gzip |
| Unused scripts removed | 0–18 ms on pages without a composer (noisy); not measurable on Board | about 15 KB less gzip |

Total: about 10–25 ms of main-thread work per click at 4x slowdown (roughly 5–10 ms on an iPhone SE 3), plus about 10 KB less HTML per click. About 3–8% of a roughly 300 ms prod click. With cycle 3's flag on, navbar pages come from a copy, so the HTML bytes are no longer on the critical path and the per-click gain shrinks to the parse time. The earlier guess in the thread (50–100 ms) was too high.

Not measured: Safari, a real logged-in session, the four after-load requests (auth status, unread, deploy check, messaging), and the cost of the discarded preload per early-fetched tap. These are the better next targets.

### Inline CSS and the first-paint flash

The inline block (`<style data-role="critical-css">`, cut from `site.css` up to `/* critical-css-end */` by `TemplateRenderer::criticalCss()`) came from reliable page delivery (`edb55cb2`, 2026-09-21): `site.css` loads without blocking first paint (`media="print"` swap), so a cold load could paint unstyled; the inline subset keeps the first screen styled. It was 22 KB raw when added, cut to about 5.7 KB in the [asset inventory](./page_asset_performance_inventory.md), and is now 40 KB (line 1590 of 2306 in `site.css`): styles keep landing above the marker.

- Moving it to a render-blocking cached `<link>` would not bring the flash back: first paint waits for it, and `theme-<name>.css` and `theme-nav-icons.css` already block first paint the same way, so on a cold load it downloads in parallel rather than adding a round trip. Moving it to a non-blocking load would bring the flash back. Unverified: that iOS Safari holds first paint for blocking stylesheets the same way; check on a throttled cold load before relying on it.
- Lower-risk alternative: trim the block back to the first screen's needs (shell, nav, theme variables, card baseline). Needs a first-paint check across all themes, as the inventory already warns.
