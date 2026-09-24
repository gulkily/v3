# Forte CSS Isolation Step 3 Development Plan

## Stage 1 - Renderer/layout support for an extra stylesheet
- Goal: let a standalone page load a second stylesheet in addition to `site.css`, with no caller using it yet.
- Dependencies: none.
- Expected changes: `TemplateRenderer::renderStandalonePage()` gains an optional parameter (e.g. `array $additionalCssPaths = []`), fingerprinted via the existing `assetPath()` and passed to `standalone_layout.php`; `standalone_layout.php` emits one additional `<link rel="stylesheet">` per provided path, after the primary `site.css` link.
- Verification: manual — render a standalone page with a placeholder extra path and confirm two `<link>` tags appear in order; confirm `/forte` and `/threads/{id}/forte` output is byte-identical to before, since no caller passes the new parameter yet.
- Risks or open questions:
  - Keep the parameter optional/defaulted so existing `renderStandalonePage()` behavior is unchanged until a caller opts in.
- Canonical components/API contracts touched: `TemplateRenderer::renderStandalonePage()`, `templates/standalone_layout.php`.

## Stage 2 - Introduce forte.css alongside site.css
- Goal: create `public/assets/forte.css` with a copy of the `.paned-*`/`body.paned-reader-body` block, and load it from both Forte pages, while leaving the same rules in `site.css` too (temporary duplication).
- Dependencies: Stage 1.
- Expected changes: new file `public/assets/forte.css` containing the copied block; `Application::renderForte()` and `Application::renderForteBoard()` pass `forte.css` via Stage 1's new parameter; `site.css` is untouched in this stage.
- Verification: manual — view source of `/forte` and `/threads/{id}/forte` shows both stylesheet links; visually compare Forte before/after (must be pixel-identical, since both files agree); confirm classic UI pages, which never load `forte.css`, are unaffected.
- Risks or open questions:
  - The copied block must be byte-for-byte identical to the `site.css` original so this stage cannot change rendering.
- Canonical components/API contracts touched: `Application::renderForte()`, `Application::renderForteBoard()`, new `public/assets/forte.css`.

## Stage 3 - Remove the paned block from site.css
- Goal: make `forte.css` the sole source of Forte's styling by deleting the now-duplicated block from `site.css`.
- Dependencies: Stage 2 (forte.css must already be loading correctly).
- Expected changes: delete the identified `.paned-*`/`body.paned-reader-body` block from `public/assets/site.css`; no other file changes.
- Verification: manual — confirm zero `.paned` matches remain in `site.css`; reload `/forte` and `/threads/{id}/forte` and confirm rendering is unchanged, including a spot-check of the four dark-mode fixes under OS/browser dark mode; reload classic UI pages and confirm unchanged.
- Risks or open questions:
  - Confirm no non-`.paned` rule elsewhere in `site.css` incidentally affected Forte before deleting (none identified during Step 2 research).
- Canonical components/API contracts touched: `public/assets/site.css` only.

## Stage 4 - Confirm static-build asset fingerprinting picks up forte.css
- Goal: verify the static-artifact build path serves `forte.css` with content-hash fingerprinting, matching every other asset.
- Dependencies: Stage 3.
- Expected changes: none anticipated — `AssetFingerprint::copyFingerprintedAssets()` already scans `public/assets` generically; this stage only produces a code change if verification surfaces a gap.
- Verification: run `scripts/build_static_artifacts.php` against a scratch output directory and confirm a hashed `forte.*.css` copy is produced alongside the other hashed assets.
- Risks or open questions:
  - None expected; noted in case the build script special-cases asset filenames somewhere not yet found.
- Canonical components/API contracts touched: none expected (`AssetFingerprint`, `StaticArtifactBuilder` read-only verification).
