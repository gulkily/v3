# Installed app start page and name — one-shot

Cycle: `v3/owa_start_url`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](owa_start_url_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/owa_start_url`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/owa_start_url.md))
Extended estimates: enabled (no alternatives or work units, so only the cycle estimate applies)

**Cycle estimate: 0.08**

Status: technically complete on `fix/owa-start-url`; pushed for review, not merged or deployed. Next action: maintainer review, merge, deploy, and a real-phone check.

## Request and authorization

Maintainer, 2026-11-23 17:42 ET, in a Claude session: "please one-shot owa_start_url". Cycle 1 of the [notifications checklist](../notifications_checklist.md) (PR #391): where the installed app opens, and its name.

The checklist's question 1 (Unread, the board, or `/offline/`; "ZenMemes" rather than "Zenmemes Offline Reading") was not answered before the request. Decided here and open to his overrule: the home page `/`, named "ZenMemes". Authorizes a one-shot through an open PR; excludes merge and deployment.

Feedback item: none matched in `docs/feedback/items/` (searched "start_url", "home screen", "installed"). PF-20261118-F03 (icons) notes that start_url was left for this work; it is not changed here.

## Outcome and work type

Application change. Opening ZenMemes from a phone's home screen shows the home page, online or offline, and the app is called "ZenMemes" under its icon and in the app switcher.

## Approach and risks

- `start_url` becomes `/`. Unread (`/unread`) was the other candidate: it is behind `FORUM_INBOX_ENABLED`, needs an approved member, and the service worker has no offline answer for it, so an offline launch would fail. `/` is in `supportsOfflineNavigation()`, so offline it gets the cached reader, and it is every site's home page. The maintainer has said landing on the Zen memes at first is fine, just not on every Board click (PR #385). Push taps will open their own page (cycle 5), so the start page does not need to be Unread for notifications.
- `id` stays `/offline/?site=<namespace>`. It names the installed app; changing it makes Android treat existing installs as a different app. Its value no longer matches start_url, which the manifest spec allows.
- Name: a new optional `appName` in the site profile ("ZenMemes", "QDB"); otherwise `ucfirst(displayName)` as before ("Chouse", "MIT Rap Club"). " Offline Reading" is dropped from every site's name. `name` and `short_name` are the same.
- Risk: iPhone reads the manifest only when the app is added to the home screen, so an existing iPhone install keeps opening `/offline/` with the old name until it is removed and added again. Android updates installed apps from the manifest on its own schedule.

## Reuse decisions

- `BrowserRuntimeAssetRenderer::manifest()` and `BrowserRuntimeProfile::fromProfile()` already build the manifest and its names for every site; changed in place.
- Optional per-site values already live in `SiteProfileRegistry` (`calendarTimeZone`, `autoThemes`, `zenStripTags`); `appName` follows them, with a validation rule like the required fields.
- The service worker's existing offline navigation fallback answers `/`; no worker change.

## Concepts touched

None changed. The offline reading concept's runbook does not mention the start URL.

## Completion conditions

- The manifest of every site has `start_url` `/`, the unchanged `id`, and the site's name without "Offline Reading".
- A first launch at `/` installs the worker and caches the reader, and an offline launch at `/` shows the cached board.
- Chrome reports no manifest errors.
- Release: on the iPhone SE, remove and re-add ZenMemes to the home screen, open it from the icon online and in airplane mode, and see "ZenMemes" under the icon.

## Verification

- `./v3 test --smoke SiteProfileRegistryTest OfflineNavigationWorkerTest`: 510 run, 510 passed. New: `SiteProfileRegistryTest::testInstalledAppOpensTheHomePageUnderTheSiteName` (all four sites) and `testEmptyAppNameIsRejected`. The full suite was not run.
- Local replay (a disposable Playwright script outside the repository, on the `parity_minimal_v1` fixture, zenmemes profile, 375 by 667 px): `/manifest.webmanifest` returns `start_url` `/`, `name` "ZenMemes", `id` `/offline/?site=zenmemes`; Chrome's `Page.getAppManifest` reports no errors; opening `/` first caches the reader and snapshot; offline, `/` shows the offline reader with its threads; no page errors.
- Not verified: a real iPhone or Android install; Chrome's install prompt.

## Execution history

- 2026-11-23: changed the manifest start URL and names, added `appName`, tests and this record. Starting revision `93295c82` on `main`; branch `fix/owa-start-url`.

## Study history

2026-11-23T22:45Z; scope v1; starting revision `93295c82`; reference R1, hashes not re-verified. Claude, single exposed rater. Intake **0.08**.
- Closeout: retrospective scope assessment **0.07**; maintainer comfortable-fit judgment pending.

*— Claude · 2026-11-23 UTC · Model: claude-opus-5-5*
