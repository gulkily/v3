# App icons — one-shot

Cycle: `v3/app_icons`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](app_icons_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/app_icons`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/app_icons.md))
Extended estimates: enabled (no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.08**

Status: technically complete on `feat/app_icons`; pushed for review, not merged or deployed. Next action: maintainer review, merge, deployment and a real-phone install check.

## Request and authorization

Original request, 2026-11-18 (maintainer, reply to "Web push notifications for v3: an outline", [thread](https://zenmemes.com/threads/thread-20261118022806-9fecb3e4)): "Since we have already done a Web app install, can we start with an icon, please? Agents feel free to go all the way to a PR."

Context from the thread (Claude, 2026-11-18): on prod the manifest lists only the 32x32 `favicon.ico` and `/apple-touch-icon.png` is a 404, so an iPhone home-screen install uses a page screenshot and Chrome on Android wants 192 and 512 px PNGs. The manifest's `start_url` is `/offline/`.

Authorization: the maintainer, 2026-11-18 (Claude session): "Can you please scan Zen memes for anything that's asking for a PR? And if the task seems appropriate for a one-shot, make the PR and reply in the thread." With "go all the way to a PR" in the thread, this authorizes a one-shot through an open PR and a thread reply. Exclusions: merge, deployment, web push itself, changing `start_url`.

Feedback item: none matched in `docs/feedback/items/` (searched for the thread ID, "icon", "manifest", "home screen"). Created [PF-20261118-F03](../../feedback/items/PF-20261118-F03.txt) in the completion commit.

## Outcome and work type

Application change. Adding ZenMemes to a phone's home screen gives a proper icon: the Z mark from the favicon on its dark slate square. iPhone gets a 180 px `apple-touch-icon`; the manifest lists 192 and 512 px PNGs (512 also as `maskable` for Android's shaped icons).

## Approach and risks

- Icons are drawn fresh at full size, matching the favicon (white Z with a gold glow on `#1f2937`), rather than upscaling the 32 px antialiased favicon, which looks blurry at 180 px and up. Z kept inside the central 80% so one 512 file is safe as `maskable`.
- Files live in `public/assets/` (`app-icon-180.png`, `-192`, `-512`), which Apache and `router.php` already serve without PHP. No `.htaccess` or router change (prod keeps a local `.htaccess` edit that a change there would collide with), so no root `/apple-touch-icon.png`; the `<link rel="apple-touch-icon">` tag is what iOS reads.
- Only the default (ZenMemes) favicon slot gets app icons. QDB, chouse.club and the Rap Club keep the favicon-only manifest until they get their own art. Recorded as follow-up.
- `start_url` stays `/offline/`. Changing where an installed app opens is the open question in the thread (Unread vs offline reading) and belongs with web push.

## Reuse decisions

- `FaviconRegistry` (`src/ForumRewrite/View/FaviconRegistry.php`) already maps the `favicon` presentation slot to files; extend it with the app-icon set for the same slot rather than a new registry.
- `BrowserRuntimeAssetRenderer::manifest` builds the manifest for both the front controller and static artifacts; add the icons there.
- `TemplateRenderer` already passes `faviconPath` to `layout.php` and `standalone_layout.php`; pass the touch-icon path beside it. Docs builds (`/api/docs-version`) get no touch icon, since their assets are copied separately.

## Concepts touched

None in the concept inventory.

## Completion conditions and verification plan

- Normal entry: on an iPhone, Share, Add to Home Screen on zenmemes.com shows the Z icon; Chrome on Android offers install with the Z icon.
- `LocalAppSmokeTest::testFrontControllerDerivesManifestAndWorkerFromTheActiveProfile` extended: profiles on the default favicon slot (ZenMemes) list the PNGs, others do not; layout test that the board carries the touch-icon link.
- `./v3 test --smoke` plus those tests. Full suite not run.
- Headless Chrome: manifest parses, every icon URL returns 200 with the right size.
- Not checkable here: a real iPhone or Android install.

Starting revision and branch: `31e16b066774dbdf8f4d3709ae4ad021bfff9516` on `main`; branch `feat/app_icons`.

Planning commit: `f228896a` (2026-11-18T07:26Z), containing only this record and its index link.

## Dated execution history

- 2026-11-18T07:25Z: plan written after reading `BrowserRuntimeAssetRenderer`, `FaviconRegistry`, `TemplateRenderer`, `layout.php`, `router.php`, `.htaccess` and the manifest tests; icon drafts drawn in a scratch directory.
- 2026-11-18T07:27Z: planning commit `f228896a` confirmed. Added the PNGs, `FaviconRegistry::touchIcon` and `::manifestIcons`, the manifest change, the `apple-touch-icon` link in both layouts, and test assertions.
- 2026-11-18T07:31Z: discovery. `testFourthProfileFixtureUsesTheSameRuntimeAndSharedStateMatrix` reruns the manifest test with a fourth profile that uses the default favicon slot, so keying the expectation on the profile ID failed. The test now keys it on the favicon slot (and still checks ZenMemes is on it). Correction to the test, not to scope.
- 2026-11-18T07:33Z: the generated icons are produced by a scratch script (vector Z drawn at 2048 px, gold glow, downsampled); the PNGs are committed, the script is not, since nothing rebuilds them.

## Study history

- Intake, 2026-11-18T07:25Z: **0.08** whole-cycle projection, scope v1, R1/P2. Recorded after inspecting the files above (not a blind intake). Single exposed rater (Claude).
- Execution entry, 2026-11-18T07:27Z: 0.08 reaffirmed.
- Closeout, 2026-11-18T07:36Z: delivered scope v1. Retrospective **0.07**. Maintainer fit and acceptance pending.

## Outcome

Delivered: ZenMemes pages link a 180 px `apple-touch-icon`; the manifest lists the favicon plus 192 and 512 px PNGs, the 512 also as `maskable`. Not delivered (out of scope): icons for QDB, chouse.club and the Rap Club (they keep the favicon-only manifest), a root `/apple-touch-icon.png`, and any `start_url` change.

## Verification

- `./v3 test --smoke LocalAppSmokeTest::testPublicLayoutRegistersTheNormalNavigationOfflineWorker LocalAppSmokeTest::testFrontControllerDerivesManifestAndWorkerFromTheActiveProfile LocalAppSmokeTest::testPrivateLayoutDoesNotRegisterOfflineReaderPwa`: 478 of 478 (smoke set includes the fourth-profile test).
- Scratch headless-Chrome check on a thread page: the touch icon loads at 180x180; every manifest icon loads at its declared size; `Page.getAppManifest` reports no errors and `Page.getInstallabilityErrors` reports only `in-incognito` (the headless context), no icon errors.
- `./v3 feedback check`: 0 problems; `./v3 feedback index` regenerated.
- Not run: the full suite; a real iPhone or Android install (needs deployment).

## Final review

Diff limited to three PNGs in `public/assets/`, `FaviconRegistry.php`, `BrowserRuntimeAssetRenderer.php`, `TemplateRenderer.php` (one key in each of two data arrays), `layout.php` and `standalone_layout.php` (one link), `LocalAppSmokeTest.php`, this record and its index line, the feedback item and index, the ledger and debug log. No routing, `.htaccess`, dependency or copy change. `public/manifest.webmanifest` (a static file the front controller does not serve) left as is.
