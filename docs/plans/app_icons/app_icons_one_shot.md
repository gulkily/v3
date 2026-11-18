# App icons — one-shot

Cycle: `v3/app_icons`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](app_icons_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/app_icons`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/app_icons.md))
Extended estimates: enabled (no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.08**

Status: planned on `feat/app_icons`; next permitted action: implement and verify.

## Request and authorization

Original request, 2026-11-18 (maintainer, reply to "Web push notifications for v3: an outline", [thread](https://zenmemes.com/threads/thread-20261118022806-9fecb3e4)): "Since we have already done a Web app install, can we start with an icon, please? Agents feel free to go all the way to a PR."

Context from the thread (Claude, 2026-11-18): on prod the manifest lists only the 32x32 `favicon.ico` and `/apple-touch-icon.png` is a 404, so an iPhone home-screen install uses a page screenshot and Chrome on Android wants 192 and 512 px PNGs. The manifest's `start_url` is `/offline/`.

Authorization: the maintainer, 2026-11-18 (Claude session): "Can you please scan Zen memes for anything that's asking for a PR? And if the task seems appropriate for a one-shot, make the PR and reply in the thread." With "go all the way to a PR" in the thread, this authorizes a one-shot through an open PR and a thread reply. Exclusions: merge, deployment, web push itself, changing `start_url`.

Feedback item: none matched in `docs/feedback/items/` (searched for the thread ID, "icon", "manifest", "home screen"). Creating PF-20261118-F03 in the completion commit.

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
- `LocalAppSmokeTest::testFrontControllerDerivesManifestAndWorkerFromTheActiveProfile` extended: the ZenMemes manifest lists the PNGs, and other profiles do not; layout test that the board carries the touch-icon link.
- `./v3 test --smoke` plus those tests. Full suite not run.
- Headless Chrome: manifest parses, every icon URL returns 200 with the right size.
- Not checkable here: a real iPhone or Android install.

Starting revision and branch: `31e16b066774dbdf8f4d3709ae4ad021bfff9516` on `main`; branch `feat/app_icons`.

Planning commit: pending.

## Dated execution history

- 2026-11-18T07:25Z: plan written after reading `BrowserRuntimeAssetRenderer`, `FaviconRegistry`, `TemplateRenderer`, `layout.php`, `router.php`, `.htaccess` and the manifest tests; icon drafts drawn in a scratch directory.

## Study history

- Intake, 2026-11-18T07:25Z: **0.08** whole-cycle projection, scope v1, R1/P2. Recorded after inspecting the files above (not a blind intake). Single exposed rater (Claude).
