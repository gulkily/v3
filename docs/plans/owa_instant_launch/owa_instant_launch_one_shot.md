# Installed app shows something at once on a slow connection — one-shot

Cycle: `v3/owa_instant_launch`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](owa_instant_launch_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/owa_instant_launch`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/owa_instant_launch.md))
Extended estimates: enabled (no alternatives or work units, so only the cycle estimate applies)

**Cycle estimate: 0.15**

Status: planned on `feature/owa-instant-launch`; next permitted action: implement.

## Request and authorization

The maintainer, 2026-11-26 18:28 ET, in a Claude session: "I have a problem with zenmemes where I'm using the off-line web app and the Internet is slow. The app is just blank for a very long time." Claude traced it to `networkFirstNavigation` in `public/service_worker.js`: the cached reader is used only when the network request fails, and a slow request does not fail, so the app waits with nothing on screen. The maintainer asked (2026-11-27 04:36 ET) whether something could show before any network request. Claude offered three approaches (last copy plus background refresh, open straight into the reader, cached frame plus streamed content) and recommended the first, with a 3-second fallback to the reader for pages with no saved copy, plus an iPhone launch image. The maintainer, 04:37 ET: "That sounds good to me. You can one-shot it." Authorizes a one-shot through an open PR; excludes merge, deployment and the phone check.

Feedback item: none matched in `docs/feedback/items/` (searched "blank", "slow", "instant"; `PF-20261111-B04` is the Search page, not this).

## Outcome and work type

Application change behind a new flag, **Installed app opens instantly** (`FORUM_OWA_INSTANT_LAUNCH_ENABLED`, default on, EXPERIENCE group). In the app added to the home screen:

1. **Last copy first.** The home page, board, thread pages and tag pages (the routes the offline reader serves) open at once from the copy kept the last time they were opened. A fresh copy loads in the background for next time. If it differs, the existing new-version notice offers Reload. The page on screen is never swapped. Navbar pages get the same treatment, as if "Navbar pages from last copy" were on.
2. **Reader after 3 seconds.** One of those routes with no kept copy, whose network answer has not come within 3 seconds, gets the cached offline reader, which renders from the database on the phone. The network answer still arrives in the background and becomes the kept copy.
3. **Launch image.** On iPhone, the app's background colour with its icon shows from the tap until the first page paints, instead of a white screen (sites with home-screen icon art only).

Browser tabs are not affected. Pages outside those routes (profiles, search, tools) still wait for the network as now.

## Approach

- **Worker (`service_worker.js`).** A marker `/__instant_launch__` in the existing nav-copy cache turns the mode on. `navCopyTarget` then also admits any route `supportsOfflineNavigation` accepts (no query). When there is no kept copy, the network request races a 3-second timer; on timeout the reader shell (already cached by every install) answers and the network response is kept via `waitUntil`. Kept copies are capped at 150 entries, the oldest-inserted dropped first.
- **Page (`nav_cached_copy.js`, layout).** The script now loads when either flag is on. An inline before-paint line (in the existing owa_resume head script's standalone check) sets `data-nav-cached-copy="1"` in the installed app when the new flag is on, so `version_check.js` shows the notice rather than reloading an older copy, and `nav_cached_copy.js` writes the navbar paths and the marker. With the flag off, the script removes the marker. `pwa_registration.js` drops the whole copy cache only when both flags are off.
- **Launch image.** `FaviconRegistry::launchImages()` lists `apple-touch-startup-image` PNGs (icon on `#f4f1e8`, the manifest background) for current iPhone screen sizes, the SE (3rd gen) included, under the same slot as the home-screen icons. The layout links them where the manifest is linked.

## Reuse decisions

- Kept copies, background refresh, new-version notice and identity-change clearing: the nav-copy machinery (`navCopyTarget`, `navCopyRefreshes`, `nav-copy-status`, `nav_cached_copy.js`, `ForumAppVersion.showPage`) from `nav_cached_copy`, extended rather than duplicated.
- Which routes the reader can answer: `supportsOfflineNavigation`, unchanged.
- Reader fallback: the existing catch-path shell lookup (`cachedResponse(..., OFFLINE_READER_URL)`).
- Standalone detection: the owa_resume inline head script's check.
- Flag: `FeatureFlagRegistry` definition plus `TemplateRenderer` pass-through, like `OWA_RESUME_ENABLED`.
- Launch images: keyed like `APP_ICONS` in `FaviconRegistry`, so instances without icon art get none.

## Risks

- **Stale first view.** By design; the notice offers the newer copy. Copies are cleared on identity change as before.
- **Reader while online.** The reader is the offline page; online it may show the offline indicator. Accepted: it shows only after 3 seconds of nothing.
- **Shared storage (Android, desktop).** The marker is shared with browser tabs of the same site, so a tab may also get last-copy pages. The tab still auto-reloads on a version change as before. Accepted; on iPhone home-screen storage is separate.
- **Cache growth.** Capped at 150 pages.
- **Launch images on iPhone.** Shown only when a PNG exactly matches the screen; unverified on a real phone.

## Concepts touched

None changed.

## Completion conditions

- Worker, under Node with fake caches: with the marker, a kept copy answers at once while the network hangs; with none, the reader answers after the timeout and the late network answer is kept; without the marker, behaviour is unchanged; unsupported routes are not admitted.
- Browser, standalone emulated with a throttled or stalled network: a second launch at `/` paints from the kept copy; flag off renders as before.
- Release: on the iPhone SE home-screen app on a slow connection, the board shows at once; a never-opened thread shows the reader within about 3 seconds.

## Verification plan

`./v3 test --smoke` plus `OfflineNavigationWorkerTest`, `FeatureFlagEvaluatorTest`, `LocalAppSmokeTest`, and a browser check if Chrome can emulate standalone. The full suite is not run (AGENTS.md).

## Execution history

- 2026-11-27: branch `feature/owa-instant-launch` from `83d6798f`; planning commit pending.

## Study history

2026-11-27T09:45Z; scope v1; starting revision `83d6798f`; reference R1, hashes not re-verified. Claude, single exposed rater. Intake **0.15**.

*— Claude · 2026-11-27 UTC · Model: claude-opus-5-5*
