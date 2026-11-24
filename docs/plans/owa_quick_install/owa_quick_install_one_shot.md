# Quick first install of the offline worker — one-shot

Cycle: `v3/owa_quick_install`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](owa_quick_install_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/owa_quick_install`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/owa_quick_install.md))
Extended estimates: enabled (no alternatives or work units, so only the cycle estimate applies)

**Cycle estimate: 0.06**

Status: planned; next permitted action: implement on `fix/owa-quick-install`.

## Request and authorization

Maintainer, 2026-11-24 16:27 ET, in a Claude session, with a screenshot: Chrome on Android shows "Install — This app cannot be installed" for zenmemes.com, while cigar-ranker.com installs on the same device. Claude compared the two live sites: both manifests, icons and root-scoped service workers are valid (cigar-ranker's icons are even the wrong size), and the one real difference found is the first-visit worker install. Cigar-ranker's worker installs at once; ZenMemes' install step waits on five shell pages, every asset they name and the 1.7 MB snapshot before the worker activates, so Chrome's installability check on a first or slow visit can find no active worker. Claude proposed making the first install cache only the reader shell and moving the full refresh to after activation; the maintainer replied "Yes, please go ahead." (16:31 ET).

Authorizes a one-shot through an open PR. Excludes the manifest `id` (changing it makes Android treat existing installs as a different app), merge and deployment.

Feedback item: none matched in `docs/feedback/items/` (searched "install", "installab"). Related: PF-20261118-F03 (home-screen icons), whose evidence says Chrome accepts the icons for install; not changed here.

## Outcome and work type

Application change. On a phone's first visit, the service worker becomes active within one page request, so Chrome can offer Install. The page then fills the offline cache (pages, assets, snapshot) in the background, as it already does on later visits. Updates of an already-installed worker keep today's full refresh during install, so an offline-capable device never loses its snapshot to an update.

## Approach and risks

- `install`: if no cache of this site's family exists yet (a first install), fetch and cache only the reader shell, then `skipWaiting()`. If one exists (an update), run `refreshOfflineReader([])` as today.
- `pwa_registration.js`: after register and update, wait for `navigator.serviceWorker.ready` before deciding to post `refresh-offline-reader`. Today the check runs on the registration returned by `register()`, which has no active worker on a first visit, so a first visit never asked for the full refresh; it came from the install step. The existing guard (`!installing && !waiting`) stays, so an update in progress still does its own refresh.
- The refresh message compares revisions; a first install stores none, so the first message always does the full refresh.
- Risk: a phone that goes offline right after its first visit, before the background refresh finishes, has the reader shell but no snapshot or assets. Today the same phone would have no active worker at all, so this is no regression. The offline page (`/offline/`) already shows missing parts and has a forced refresh.
- Risk: a worker killed mid-refresh. The message handler's `waitUntil` keeps it alive as today; the next page load posts the message again because the revision is still unstored.

## Reuse decisions

- `refreshOfflineReader`, `fetchOfflineResource`, `cacheKey`, `isOwnedCacheName` and the `refresh-offline-reader` message in `public/service_worker.js` are reused unchanged; the install step only chooses between a shell-only cache and the existing full refresh.
- `public/assets/pwa_registration.js` keeps its single registration path; only the timing of its existing refresh message changes.
- Tests extend the vm harness already used in `tests/OfflineNavigationWorkerTest.php`.

## Concepts touched

Offline reading (worker install timing), changed; no inventory entry names the install step, so no inventory update proposed.

## Completion conditions

- First install (no owned cache) caches only `/offline/reader/`, calls `skipWaiting()`, and does not fetch the snapshot.
- Update install (owned cache present) still runs the full refresh.
- After `ready`, the page posts `refresh-offline-reader`, which fills the cache.
- Release: on the Android phone from the report, open zenmemes.com and see Install offered (maintainer check after deploy).

## Verification and recovery plan

- `./v3 test --smoke OfflineNavigationWorkerTest LocalAppSmokeTest::testPublicLayoutRegistersTheNormalNavigationOfflineWorker`, plus new worker tests for both install paths. The full suite is not run.
- The reported failure (Chrome's install dialog on the maintainer's phone) cannot be replayed here; reported as unverified.

Starting revision and branch: `3dc254a` on `main`; branch `fix/owa-quick-install`.
Planning commit: pending.

## Execution history

- 2026-11-24: plan written.

## Study history

2026-11-24T21:40Z; scope v1; starting revision `3dc254a`; reference R1, hashes not re-verified. Claude, single exposed rater. Intake **0.06**.

*— Claude · 2026-11-24 UTC · Model: claude-opus-5-5*
