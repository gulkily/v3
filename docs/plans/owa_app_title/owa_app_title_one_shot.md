# Installed app name in every browser, fresh manifest after a deploy — one-shot

Cycle: `v3/owa_app_title`; Mode: one-shot; Record/index: this file, [plans index](../README.md), [notifications checklist](../notifications_checklist.md) (follow-up to cycle 1, `owa_start_url`)
Debug mode: enabled ([log](owa_app_title_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/owa_app_title`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/owa_app_title.md))
Extended estimates: enabled (no alternatives or work units, so only the cycle estimate applies)

**Cycle estimate: 0.06**

Status: implemented on `fix/owa-app-title`; PR open, not merged or deployed. Next action: the maintainer merges and deploys, then adds the app from Firefox on the iPhone (release check below).

## Request and authorization

The maintainer, 2026-11-24/25 in a Claude session, after adding ZenMemes to his iPhone home screen from two browsers: Safari offered "ZenMemes" with the Z icon; Firefox offered "Board" (the page `<title>`) with a letter icon. Earlier that evening the app icon opened the offline reader although the live manifest's `start_url` is `/`; the live manifest is sent with both `Cache-Control: no-store` (PHP) and `max-age=172800` (the host), so a browser may install from a copy cached before the `owa_start_url` deploy. Claude proposed, and the maintainer answered "Yes please" (2026-11-25 02:00 ET) to: one one-shot for the app-name tag plus manifest versioning, and a second for resuming where you were (`owa_resume`, its own record). Authorizes this one-shot through an open PR; excludes merge, deployment and the phone check.

Feedback item: none matched in `docs/feedback/items/` (searched "home screen", "manifest"); PF-20261118-F03 is the icons item, already done.

## Outcome and work type

Application change.

1. Every layout page names the app in its own HTML with `<meta name="apple-mobile-web-app-title">` (the manifest's name, "ZenMemes" on zenmemes.com), so a browser that skips the manifest when adding to the home screen can still use the right name. Whether Firefox on iPhone reads it is unknown; the phone check decides.
2. The manifest link carries the manifest's content hash, `/manifest.webmanifest?v=<12 hex>`, so a browser that cached an older manifest fetches the current one after it changes.

Not in scope: the Firefox letter icon (no known fix), the home page `<title>` (kept "Board" unless the phone check shows Firefox ignores the meta tag), the host's `max-age` header.

## Reuse decisions

- `BrowserRuntimeProfile::fromProfile()['manifestName']` is the app name the manifest already uses; the meta tag reads the same value, so they cannot drift.
- `BrowserRuntimeAssetRenderer::manifest()` produces the manifest bytes; the version is a hash of that output, the same value `DocsPublication` already hashes for docs assets.
- `FrontController::browserRuntimeAsset()` matches the manifest by path only, so `?v=` needs no route change. The service worker's `cacheableRequest()` skips URLs with a query, so the versioned link is fetched from the network; its offline precache of `/manifest.webmanifest` stays as it is (the manifest is read when installing, which is online).
- Both layouts (`templates/layout.php`, `templates/standalone_layout.php`) get the meta tag through `TemplateRenderer`; only `layout.php` links the manifest today, unchanged.

## Risks

- Changing the manifest URL: app identity is the manifest `id`, unchanged, so existing installs stay the same app.
- The hash is computed per render; the manifest is a short JSON string, negligible.

## Concepts touched

None changed.

## Completion conditions

- `/` on the zenmemes profile has `<meta name="apple-mobile-web-app-title" content="ZenMemes">` and `<link rel="manifest" href="/manifest.webmanifest?v=…">` whose hash matches the served manifest; `/manifest.webmanifest?v=…` returns the manifest.
- Release: on the iPhone, Add to Home Screen from Firefox shows "ZenMemes" (or, if not, record that Firefox ignores the tag and decide on the `<title>`); from Safari still "ZenMemes" with the Z.

## Verification

- `./v3 test --smoke LocalAppSmokeTest SiteProfileRegistryTest`: 500 run, 500 passed. `testPublicLayoutRegistersTheNormalNavigationOfflineWorker` now checks the meta tag and that the manifest link's hash matches `BrowserRuntimeAssetRenderer::manifest()`; the two existing manifest-link assertions match the versioned link, and the members-only lobby check now asserts no `rel="manifest"` at all. The full suite was not run.
- Local replay (`./v3 start 127.0.0.1:8123 --no-worker`, zenmemes profile): `/` and `/offline/reader/` carry `<meta name="apple-mobile-web-app-title" content="ZenMemes">` and `<link rel="manifest" href="/manifest.webmanifest?v=3f46d86b6af3">`; the first 12 hex of the sha256 of the served `/manifest.webmanifest` is `3f46d86b6af3`; `/manifest.webmanifest?v=x` returns the manifest.
- Not verified: Firefox or Safari on a real iPhone; whether Firefox reads the tag.

## Execution history

- 2026-11-25: planning commit `11b6314a` on `fix/owa-app-title` from `eac0968f`; then the meta tag in both layouts, the versioned manifest link, test updates and this record.

## Study history

2026-11-25T07:00Z; scope v1; starting revision `eac0968f`; reference R1, hashes not re-verified. Claude, single exposed rater. Intake **0.06**.
- Closeout: retrospective scope assessment **0.05**; maintainer comfortable-fit judgment pending.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*
