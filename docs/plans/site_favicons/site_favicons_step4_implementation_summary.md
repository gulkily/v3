> **Feature plan:** [Step 1](site_favicons_step1_solution_assessment.md) · [Step 2](site_favicons_step2_feature_description.md) · [Step 3](site_favicons_step3_development_plan.md) · [Step 4](site_favicons_step4_implementation_summary.md)

Debug mode: enabled

## Stage 1 - Icon assets
- Changes:
  - Added `public/assets/favicon-chouse.ico`: gold "c" (`#d9a94a`) on the chouse page background (`#1c130c`).
  - Added `public/assets/favicon-qdb.ico`: white opening quotation mark on the qdb amber (`#c08000`).
  - Both are single 32x32 icons generated with Pillow; colors come from `theme-chouse.css` and `theme-qdb.css`.
- Verification:
  - `file` reports a 32x32 icon for both.
  - Rendered each at 16px (nearest-neighbor upscaled) and checked it visually: both glyphs are legible and centered.
  - An earlier draft had a quote mark that was too small and off-center; it was regenerated before commit.
- Notes:
  - Icons are PNG-in-ICO rather than the BMP encoding `favicon-mitrapclub.ico` uses; supported by current browsers.
  - No source changes in this stage, so `./v3 test` is deferred to Stage 2.

## Stage 2 - Slot and profile wiring
- Changes:
  - `PresentationSlotRegistry`: `favicon` slot choices now include `chouse` and `qdb`.
  - `FaviconRegistry`: added `/assets/favicon-chouse.ico` and `/assets/favicon-qdb.ico`.
  - `SiteProfileRegistry`: `chouse` and `qdb` profiles select their own `favicon`.
  - `PresentationSlotRegistryTest`: new test asserts all four profiles resolve to the expected path, each file exists, and an unselected profile falls back to `/favicon.ico`.
- Verification:
  - `./v3 test PresentationSlotRegistryTest`: 4/4 pass, including the new test.
  - Full `./v3 test` run: only failures were the five long-standing ones plus four `OfflineSnapshot*` tests flagged "new".
  - Stashing my changes and re-running those four on the untouched branch base gives the same four failures (`no such column: author_identity_id`, and two publish-command exit-code assertions), so they are not caused by this feature.
- Notes:
  - The four offline-snapshot failures look like local database schema drift from working across branches; they are out of scope here and were not touched.
  - Existing slot entries and the `zenmemes`/`mitrapclub` selections are unchanged.

## Stage 3 - End-to-end verification
- Changes:
  - None. No service worker or builder edits were needed.
- Verification:
  - Page head, rendered through `Application` against copies of the local state databases and repositories (`FORUM_SITE_ID` per site): `zenmemes` -> `/favicon.ico`, `chouse` -> `/assets/favicon-chouse.ico`, `qdb` -> `/assets/favicon-qdb.ico`.
  - Manifest, via `BrowserRuntimeAssetRenderer::manifest` for all four profiles: `zenmemes` -> `/favicon.ico`, `chouse` -> `/assets/favicon-chouse.ico`, `qdb` -> `/assets/favicon-qdb.ico`, `mitrapclub` -> `/assets/favicon-mitrapclub.ico`.
  - Static build runtime step (`copyOfflineRuntimeFiles`, run into a temp artifact root per profile): each profile's artifact contains `/favicon.ico` plus its own icon (`chouse`, `qdb`, `mitrapclub`); `zenmemes` contains only `/favicon.ico`.
  - Service worker: it precaches `/favicon.ico` and every `/assets/` URL referenced by `<link href>` in the offline shell pages, which includes the page's `<link rel="icon">`; the per-site icon is therefore precached with no worker change. Checked by reading the worker source, not by running it in a browser.
  - Full `./v3 test` ran at Stage 2 with no source changes since; the only failures are pre-existing (see Stage 2 notes).
- Notes:
  - `/manifest.webmanifest` is not an `Application` route in this checkout (it returns Not Found there); it is served as a runtime file, so it was verified through the renderer and static output instead.
  - `mitrapclub` page head was not rendered because no local `mitrapclub` repository exists under `state/`; its resolved path is covered by the Stage 2 test and the manifest/static checks above.
  - Not verified: an installed-app icon on a real device or browser tab bar.
