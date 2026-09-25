# Forte CSS Isolation Step 2 Feature Description

## Problem
Forte's paned-reader rules live inside `site.css` next to the classic UI's themeable rules, so every Forte selector has to defensively out-specificity the classic theme to stay visually independent — the cause of three dark-mode leaks fixed this session. Extracting Forte's CSS into its own file removes the shared cascade so this class of bug can't recur.

## User Stories
- As a maintainer, I want Forte's styles in their own file so that editing the classic theme (adding/changing a `button`, `a`, or `:root` rule) can never silently change how Forte renders.
- As a maintainer, I want Forte's rules in one place so that editing Forte's look doesn't require checking `site.css` for competing selectors first.
- As a Forte user, I want no visible change from this work — the reader should look and behave exactly as it does today, in both light and dark browser/OS settings.

## Core Requirements
- All `.paned-*` and `.paned-window` rules move out of `site.css` into a new `public/assets/forte.css`; nothing else in `site.css` changes.
- Both Forte pages (`/forte` and `/threads/{id}/forte`) load `forte.css` in addition to `site.css` (for the generic low-level resets Forte still relies on, e.g. `button { appearance: none }`), not instead of it.
- No classic-UI page (`/`, `/tags`, `/threads/{id}`, etc.) loads `forte.css`.
- `forte.css` is served with the same content-hash fingerprinting as every other asset (works in the PHP dev server and in `scripts/build_static_artifacts.php`'s static build).
- The four dark-mode leak fixes just made (button/link/scrollbar isolation) are preserved exactly — this is a pure relocation, not a rewrite.

## Shared Component Inventory
- `TemplateRenderer::renderStandalonePage()` — currently hardcodes a single `siteCssPath`; needs to accept an additional stylesheet path so Forte's two call sites can add `forte.css` without a bespoke rendering path.
- `templates/standalone_layout.php` — currently emits exactly one `<link rel="stylesheet">`; needs to emit the extra one when provided.
- `public/assets/site.css` — source of the `.paned-*`/`.paned-window` block being extracted; every other rule is untouched.
- `AssetFingerprint::fingerprintedPath()` / `copyFingerprintedAssets()` — already generic per-file hashing that scans everything under `public/assets`; a new `forte.css` should be picked up automatically, but this is confirmed in Step 3 rather than assumed.
- `Application::renderForte()` and `Application::renderForteBoard()` — the two call sites that need to pass `forte.css` alongside their existing script paths.

## Simple User Flow
1. A developer edits `forte.css` to change the paned reader's appearance; `site.css` and the classic UI are unaffected.
2. A visitor loads `/forte` or `/threads/{id}/forte`; the page loads both `site.css` (generic resets) and `forte.css` (paned-specific rules).
3. A visitor loads any classic page; only `site.css` loads, exactly as today.
4. Running the static-artifact build produces a correctly content-hashed `forte.css` alongside the other hashed assets.

## Success Criteria
- `site.css` contains zero `.paned-*` or `.paned-window` selectors after the move.
- Forte renders pixel-identical to today in light mode, OS-level dark mode, and the site's explicit dark theme — including the four leaks fixed this session.
- Classic UI pages are visually and behaviorally unchanged.
- `forte.css` is fingerprinted/served correctly by both the dev server and the static build script.
- No new database fields, tables, or endpoints; no JS behavior changes.
