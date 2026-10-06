> **Feature plan:** [Step 1](./offline_reader_asset_refresh_step1_solution_assessment.md) · [Step 2](./offline_reader_asset_refresh_step2_feature_description.md) · [Step 3](./offline_reader_asset_refresh_step3_development_plan.md) · [Step 4](./offline_reader_asset_refresh_step4_implementation_summary.md)

## Original Query

Every time I request a new page, a lot of js resources are re-requested. Is there a way to optimize this away?

```
02:45:59 :8002  200  GET       13ms  /users/
02:45:59 :8002  200  GET       15ms  /offline/reader/?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET       15ms  /offline/?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET       12ms  /tools/outbox/?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET       16ms  /offline/?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET       16ms  /offline/reader/?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET       12ms  /tools/outbox/?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  404  GET       12ms  /offline/snapshot.sqlite3?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET        0ms  /assets/theme-light.72bf6ef37e60.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET        1ms  /assets/site.d325b08e5e03.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET        0ms  /assets/sqlite.7baa213fab05.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET        1ms  /assets/thread-list.ad4fc1444fdd.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET        0ms  /assets/tags.f987a07908f1.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET        0ms  /assets/content-interactions.1dd48d0eedbe.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET        1ms  /assets/theme_toggle.6a3f3b32dd31.js?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET        1ms  /assets/openpgp_loader.6ec97296c0eb.js?__offline_bootstrap=zenmemes-offline-reader-v13
02:45:59 :8002  200  GET        2ms  /assets/browser_signing.d5262c6c21fc.js?__offline_bootstrap=zenmemes-offline-reader-v13

02:46:04 :8002  200  GET       13ms  /tools/
02:46:04 :8002  200  GET       15ms  /offline/reader/?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET       14ms  /offline/?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET       12ms  /tools/outbox/?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET       15ms  /offline/?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET       16ms  /offline/reader/?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET       13ms  /tools/outbox/?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  404  GET       13ms  /offline/snapshot.sqlite3?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET        0ms  /assets/theme-light.72bf6ef37e60.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET        1ms  /assets/site.d325b08e5e03.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET        0ms  /assets/sqlite.7baa213fab05.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET        1ms  /assets/thread-list.ad4fc1444fdd.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET        0ms  /assets/tags.f987a07908f1.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET        0ms  /assets/content-interactions.1dd48d0eedbe.css?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET        1ms  /assets/theme_toggle.6a3f3b32dd31.js?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET        1ms  /assets/openpgp_loader.6ec97296c0eb.js?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:04 :8002  200  GET        2ms  /assets/browser_signing.d5262c6c21fc.js?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:02 :8002  200  GET        0ms  /assets/outbox_store.26c40f387624.js?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:02 :8002  200  GET        1ms  /assets/outbox_storage.4a51260ceb22.js?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:02 :8002  200  GET        0ms  /assets/outbox_intent.b49cb95e5c50.js?__offline_bootstrap=zenmemes-offline-reader-v13
02:46:02 :8002  200  GET        1ms  /assets/outbox_sender.931172639460.js?__offline_bootstrap=zenmemes-offline-reader-v13
```

## Problem Statement
Every normal page load re-downloads the saved-reader asset set (and the snapshot) through the service worker, even though the hashed assets have not changed.

## Findings (from code reading)
- `public/assets/pwa_registration.js` posts `refresh-offline-reader` on every window `load` when online and the worker is active.
- `public/service_worker.js` `refreshOfflineReader()` re-fetches the three shell pages, `/offline/snapshot.sqlite3`, and every `/assets/*` referenced by them, with a cache-busting `__offline_bootstrap` query and `cache: "no-store"`. The log above is this refresh.
- The snapshot is in that list, so a full snapshot download can repeat on every load. It returns 404 here, which makes `Promise.all` reject, so the refresh stores nothing.
- Hashed `/assets/` files are already served with `max-age=31536000, immutable`, so the browser's own HTTP cache is not the cause.

## Solution Options
- **Option A: Refresh only on revision change.** Add a combined revision and refresh only when it differs from the cached one. The existing `data-reader-revision` is not usable: it is only the fingerprinted URL of `offline_reader.js`. The combined revision would be a hash of (1) the three shell page bodies, which already embed every fingerprinted asset URL, and (2) a snapshot revision the publisher does not write today, so it would need a new `/offline/manifest.json`. Normal loads make no asset or snapshot requests when the revision is unchanged.
  - Pros: zero requests when nothing changed.
  - Cons: needs a new snapshot revision written by the publisher and a new manifest route; the revision must be computed correctly on every deploy, and a missed signal leaves stale assets.
- **Option B: Incremental refresh on load.** Each load re-fetches only the three small shell pages and fetches only `/assets/` URLs not already cached (hashed names are content-addressed). The snapshot is fetched only when its revision changes, which still requires the snapshot revision from Option A.
  - Pros: self-heals after deploy without a revision discipline for assets; asset cost is near zero on repeat loads.
  - Cons: still about three small HTML requests per load; needs a per-file "already cached" check; snapshot freshness still needs the new snapshot revision.
- **Option C: Refresh only on install, activate, or the manual button.** Remove the automatic `load` refresh.
  - Pros: simplest; no per-load traffic.
  - Cons: installed clients keep the old asset set until the worker updates (requires a `CACHE_NAME` bump on deploy) or the user presses "Refresh saved reader".

## Recommendation
**Option A, as chosen by the user.** It makes normal loads issue no asset or snapshot requests. The cost is a new snapshot revision written by the publisher, a new `/offline/manifest.json` route, and a combined revision computed on every deploy. Option B remains the fallback if the revision signal proves unreliable.

**Vertical-slice viability:** Yes. The entry is a normal page load, the outcome is that navigation stops re-downloading assets, and the recovery is the existing "Refresh saved reader" button. Affected tests (`tests/OfflineNavigationWorkerTest.php`, `tests/LocalAppSmokeTest.php` worker assertions) will need updating in Step 3.

Stopping here. Step 2 will not be drafted until you reply with “Approved Step 1.”
