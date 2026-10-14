> **Feature plan:** [Step 1](site_favicons_step1_solution_assessment.md) · [Step 2](site_favicons_step2_feature_description.md) · [Step 3](site_favicons_step3_development_plan.md) · [Step 4](site_favicons_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

Our default favicon is a letter Z, which is kind of appropriate for zenmemes, but very inappropriate for other sites. Please make a new favicon for each site we are supporting. Write Step 1 of @docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md

## Understood Intent

- Sites are the four profiles in `src/ForumRewrite/SiteProfileRegistry.php`: `zenmemes`, `chouse`, `qdb`, `mitrapclub`.
- Today only `mitrapclub` opts into its own icon (via the `favicon` presentation slot). `chouse` and `qdb` fall through to the shared Z `public/favicon.ico`.
- Goal: every site shows an icon that fits its own identity; `zenmemes` keeps the Z.

## Problem Statement

`chouse` and `qdb` display the zenmemes "Z" favicon because the only per-site icon mechanism has been used by one profile so far.

## Solution Options

- **Option A: Extend the existing `favicon` slot with one explicit asset per site.** Add `chouse` and `qdb` choices to the existing slot, ship one new `.ico` each under `public/assets/`, and have those profiles opt in, exactly as `mitrapclub` already does.
  - Pros: reuses the proven, validated mechanism end to end (layout, manifest, static build); explicit and testable; smallest diff; no behavior change for `zenmemes`/`mitrapclub`.
  - Cons: each future site needs a registry edit plus an asset; the fallback is still the Z for any profile that forgets to opt in.
- **Option B: Convention-based lookup.** Resolve `/assets/favicon-{siteId}.ico` automatically for the active profile, falling back to the default Z if the file is missing.
  - Pros: new sites need only drop in a file; no registry edits.
  - Cons: silent fallback hides missing icons (the exact bug being fixed); bypasses slot validation; adds filesystem checks to the render path; reworks a mechanism that already works.
- **Option C: Generate icons from the profile at build/render time.** Derive a monogram or color tile from `displayName` and the site's theme color instead of hand-made art.
  - Pros: no per-site asset authoring; automatically covers future sites.
  - Cons: generic-looking results (little identity beyond a letter, which is the current complaint); needs an image-generation dependency or an on-the-fly SVG path that the manifest, service worker and static builder currently don't expect (`.ico`, 32x32).

## Recommendation

**Option A.** It is the lowest-risk way to deliver distinct, designed icons and keeps the explicit, validated slot contract. Step 2 should decide the visual motif for `chouse` and `qdb`, whether `zenmemes` keeps the current Z asset unchanged, and whether the default fallback should stay the Z.

**Vertical-slice viability:** Yes. Entry is loading any page (or the web manifest) on `chouse` or `qdb`; outcome is that site's own icon in the tab, bookmarks and installed-app icon; recovery is the existing fallback to the default icon if an asset is absent; `zenmemes` and `mitrapclub` remain unchanged.

Waiting for "Approved Step 1" before drafting Step 2.
