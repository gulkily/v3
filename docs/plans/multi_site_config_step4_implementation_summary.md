# Multi-Site Config — Step 4: Implementation Summary

## Stage 1 - SiteProfileRegistry class
- Changes:
  - Added `src/ForumRewrite/SiteProfileRegistry.php`
    - `all(): array` — `zenmemes` and `chouse` entries, each with `name`, `defaultTheme`, `composerPrompt`
    - `active(): array` — resolves `getenv('FORUM_SITE_ID')`, defaults to `zenmemes` when unset/empty/unrecognized
  - Added `tests/SiteProfileRegistryTest.php` covering default resolution, known override, unknown-value fallback, and required-field presence
  - Registered the new test file in `tests/run.php`
- Verification:
  - `php tests/run.php` — full suite passes, including all 4 new `SiteProfileRegistryTest` cases
- Notes:
  - Both profiles currently carry identical `defaultTheme`/`composerPrompt` placeholder values; Stages 3-4 wire those fields through to rendering, at which point chouse's values can be revisited/diverged if desired
