# Forte CSS Isolation Step 4 Implementation Summary

## Stage 1 - Renderer/layout support for an extra stylesheet
- Changes:
  - `TemplateRenderer::renderStandalonePage()` gains an optional `array $additionalCssPaths = []` parameter; each path is fingerprinted via the existing `assetPath()` and passed to the layout as `additionalCssPaths`.
  - `templates/standalone_layout.php` emits one additional `<link rel="stylesheet">` per entry in `additionalCssPaths`, after the primary `site.css` link.
- Verification:
  - `php -l` clean on both changed files.
  - `curl http://127.0.0.1:8001/forte` — exactly one `<link rel="stylesheet">` present (unchanged), 200 OK.
  - `curl http://127.0.0.1:8001/threads/{id}/forte` — exactly one `<link rel="stylesheet">` present (unchanged), 200 OK.
  - No caller passes the new parameter yet, so both routes render byte-identical to pre-Stage-1 output.
- Notes:
  - Parameter defaults to `[]` so no other `renderStandalonePage()` behavior changed.

## Stage 2 - Introduce forte.css alongside site.css
- Changes:
  - Added `public/assets/forte.css`, a byte-for-byte copy of the `.paned-*`/`body.paned-reader-body` block (site.css lines 3425-3878).
  - `Application::renderForte()` and `Application::renderForteBoard()` now pass `['/assets/forte.css']` as the new `$additionalCssPaths` argument.
  - `site.css` left untouched in this stage (rules temporarily exist in both files).
- Verification:
  - `diff public/assets/forte.css <(sed -n '3425,3878p' public/assets/site.css)` — identical.
  - `curl http://127.0.0.1:8001/forte` and `curl http://127.0.0.1:8001/threads/{id}/forte` both now emit two `<link rel="stylesheet">` tags (`site.*.css` then `forte.*.css`), both 200 OK and correctly content-hashed.
  - Headless-Chromium screenshots of `/forte` in light mode and OS dark mode match the pre-Stage-2 (post dark-mode-fix) screenshots pixel-for-pixel; toolbar/sort/reply-toggle buttons and profile-link color remain correct.
  - Classic UI pages never reference `forte.css` (unaffected; not reloaded in this stage).
- Notes:
  - `site.css` still contains the duplicated block; Stage 3 removes it now that `forte.css` is confirmed live.

## Stage 3 - Remove the paned block from site.css
- Changes:
  - Deleted the `.paned-*`/`body.paned-reader-body` block (previously lines 3425-3878) from `public/assets/site.css`; file now ends after `.sqlite-tab-panel[hidden]`.
- Verification:
  - `grep -c paned public/assets/site.css` — 0.
  - `curl http://127.0.0.1:8001/forte` and the `/threads/{id}/forte` route still emit both stylesheet links (200 OK); headless-Chromium screenshot of `/forte` under OS dark mode is pixel-identical to Stage 2's (toolbar/sort/reply-toggle buttons and link color still correct — now sourced solely from `forte.css`).
  - Classic UI: `curl http://127.0.0.1:8001/threads/{id}` shows the current (post-edit) `site.*.css` hash; headless-Chromium screenshot of that page under dark mode renders correctly.
  - Aside (not a regression from this change, noted for the record): `curl http://127.0.0.1:8001/` and a couple of other root-level routes are served from pre-existing static snapshots in `public/*.html` (from an earlier `build_static_artifacts.php` run, unrelated to this feature) that PHP's built-in server prefers over the live route when a same-named file exists, so they don't reflect live `site.css` edits until rebuilt. `/threads/{id}` and all Forte routes have no such static twin and always hit the live app.
- Notes:
  - No other `site.css` rule referenced `.paned-*` selectors, so nothing else needed adjustment.

## Stage 4 - Confirm static-build asset fingerprinting picks up forte.css
- Changes:
  - None — verification only, as anticipated in the plan.
- Verification:
  - Ran `FORUM_PUBLIC_ARTIFACT_ROOT=<scratch dir> php scripts/build_static_artifacts.php` against a scratch copy of `public/`.
  - Output `assets/` contains `forte.00b2e8fed466.css`, whose hash matches `sha256sum public/assets/forte.css` (first 12 hex chars: `00b2e8fed466`) — `AssetFingerprint::copyFingerprintedAssets()` picked up the new file with no special-casing needed, exactly as expected from its generic directory scan.
- Notes:
  - No code changes were required for this stage; the generic asset-fingerprinting path already covered the new file.
