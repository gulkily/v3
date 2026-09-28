# Asset Fingerprinting and CSS Splitting

Spec for the content-hash asset fingerprinting scheme
(`src/ForumRewrite/Host/AssetFingerprint.php`) and the CSS delivery strategy
built on top of it: critical inline CSS, a `site.css` base stylesheet, a
per-page CSS split, and a per-theme CSS split. `docs/specs/` otherwise
documents the canonical-record/contract layer thoroughly; this fills the gap
for this newer infra layer.

## Why

Public reads get long-lived, immutable `Cache-Control` on every asset
response, which requires the URL to change whenever the file's contents
change. Fingerprinting embeds a content hash into each `/assets/...` URL so
browsers/CDNs can cache indefinitely while still picking up new content
immediately after a deploy.

## Fingerprint format

`AssetFingerprint::fingerprintedPath()` takes a request path like
`/assets/site.css`, hashes the file's contents (`sha256`, truncated to the
first 12 hex characters), and inserts the hash before the extension:

```
/assets/site.css  ->  /assets/site.a1b2c3d4e5f6.css
```

Only paths under `/assets/` are fingerprinted; anything else passes through
unchanged. If the source file doesn't exist, the original path is returned
unchanged (fails open, not closed).

`AssetFingerprint::sourcePathForFingerprint()` is the inverse: given a
fingerprinted request path, it extracts the hash, recomputes it from the
current source file, and returns the source file path only if the hashes
match (`hash_equals()`) — this is also the mechanism that rejects a stale or
forged fingerprint.

`AssetFingerprint::replacementPathForFingerprint()` handles the "valid
source file, but the fingerprint in the URL is stale" case (the file changed
since the URL was generated): it returns the *current* fingerprinted path for
that source file, or `null` if the given path is already current.

## Request-time serving (`FrontController`)

On every request, before any other routing:

1. `resolveFingerprintedAssetPath()` — tries to resolve the request path via
   `sourcePathForFingerprint()`. On a match, serves the file directly with
   `Cache-Control: public, max-age=31536000, immutable` (`sendAsset()`).
2. `resolveStaleFingerprintedAssetPath()` — if step 1 didn't match, tries
   `replacementPathForFingerprint()`. On a match (a stale-but-recognizable
   fingerprinted URL), issues a `302` redirect to the current fingerprinted
   path with `Cache-Control: no-store` (`sendAssetRedirect()`), rather than
   a 404. This is what keeps old fingerprinted links (e.g. from a stale
   static HTML artifact or an external cached page) working across a CSS
   change instead of breaking.

Only unfingerprinted `text/html` responses get the short-lived
`no-cache, must-revalidate` treatment; asset responses are the
`immutable` branch specifically because the content-hash URL guarantees the
bytes never change under that URL.

## Template-time generation (`TemplateRenderer`)

`TemplateRenderer::assetPath()` wraps `AssetFingerprint::fingerprintedPath()`
and is the only way template code should reference a `/assets/...` URL — it
fingerprints every stylesheet and script path emitted into `templates/layout.php`
(`siteCssPath`, per-page/per-theme stylesheet paths, all script paths).

## Critical CSS

`site.css` contains a `/* critical-css-end */` marker (currently around
line 341). `TemplateRenderer::criticalCss()` reads everything in the file
*before* that marker and inlines it directly into `<style data-role="critical-css">`
in `<head>`, so above-the-fold styling doesn't wait on an external stylesheet
request. The full `site.css` (fingerprinted) then loads via the standard
preload-and-swap pattern:

```html
<link rel="preload" href="{siteCssPath}" as="style" fetchpriority="high">
<link rel="stylesheet" href="{siteCssPath}" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="{siteCssPath}"></noscript>
```

(`media="print"` + `onload` swap is the standard non-render-blocking
stylesheet load trick; the `<noscript>` fallback covers JS-disabled clients.)

## Per-page CSS split

Beyond `site.css` (base styles, loaded on every page), each page template can
declare additional page-specific stylesheets via
`TemplateRenderer::PAGE_STYLESHEET_PATHS`, a `page template => stylesheet
paths` map:

```php
'about.php' => ['/assets/about.css'],
'activity.php' => ['/assets/activity.css'],
'account_key.php' => ['/assets/identity.css'],
'bookmarklets.php' => ['/assets/tools.css'],
'invites.php' => ['/assets/invitations.css', '/assets/identity.css'],
'post.php' => ['/assets/identity.css', '/assets/content-interactions.css'],
'profile.php' => ['/assets/identity.css'],
'board.php' => ['/assets/thread-list.css'],
'tags.php' => ['/assets/tags.css'],
'tag.php' => ['/assets/thread-list.css'],
'thread.php' => ['/assets/identity.css', '/assets/content-interactions.css'],
'tools.php' => ['/assets/tools.css'],
```

A page template not listed here loads only `site.css`. These load as plain
render-blocking `<link rel="stylesheet">` tags (no preload/swap — they're
page-specific, so there's no cross-page benefit to the print-media trick),
each fingerprinted individually. To add a new page-specific stylesheet, add
an entry to this map and create the CSS file under `public/assets/`; no
other wiring is needed.

## Per-theme CSS split

Each theme is its own `public/assets/theme-<name>.css` file, loaded on
demand by swapping `<link id="theme-stylesheet">`'s `href` — this is a
separate, older split from the per-page one above and is documented in full
in `docs/runbooks/theme_development_guide.md` (registry wiring, file
structure, anti-FOUC allow-list). Both splits are fingerprinted the same way
via `TemplateRenderer::assetPath()`.

## Static HTML artifact build (`StaticArtifactBuilder`)

Thread/profile/tag/etc. routes can be served from pre-baked static HTML
(`./v3 build-static`). When building an artifact:

- `copyReferencedAssets()` scans the rendered HTML for fingerprinted asset
  references and copies each referenced source file into the artifact root
  under its *current* fingerprinted name, via
  `AssetFingerprint::copyReferencedFingerprintedAssets()`.
- `assertFingerprintReferencesAvailable()` then verifies every fingerprinted
  reference in the rendered HTML resolves to a file that actually exists in
  the artifact root, throwing if any are missing — this is what makes a
  broken build fail loudly instead of shipping a page with dead CSS/JS
  links.

The standalone `scripts/check_static_artifacts.php` (documented in
`docs/reference/v3_cli.md`) runs the same missing-reference check against an
already-built artifact tree, independent of a build run — useful for
catching drift (e.g. a manually deleted or renamed asset) after the fact.

## Non-goals / not covered here

- Fingerprinting only applies to paths under `/assets/`; nothing else is
  content-hashed.
- This doc covers the fingerprinting/delivery mechanism and the two CSS
  splits. It is not a catalog of every current stylesheet file or every
  page's full asset list — read `TemplateRenderer::PAGE_STYLESHEET_PATHS`
  and `ThemeRegistry` directly for that, since it changes often.
