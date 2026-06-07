# App Version Notification Feature Flag Plan V1

## Goal

Add a server-side feature flag for the browser "A new version is available." notification.

The current behavior should remain the default: version polling and the reload banner stay enabled unless an operator explicitly disables them.

## Current Context

The notification is wired into the shared layout:

- `templates/layout.php` always emits `meta[name="app-version"]`
- `templates/layout.php` always emits `meta[name="app-version-endpoint"]`
- `templates/layout.php` always loads `/assets/version_check.js`
- `templates/layout.php` always renders the hidden `data-role="app-version-banner"` markup

`public/assets/version_check.js` then polls `/api/version`, stores pending versions in `sessionStorage`, and reveals the banner when the endpoint returns a version different from the page's current `app-version` meta value.

The existing feature flag pattern lives in `src/ForumRewrite/SiteConfig.php`:

```php
public static function unicodeAuthoredTextEnabled(): bool
{
    return self::envFlagEnabled('FORUM_UNICODE_AUTHORED_TEXT', false);
}
```

## Product Semantics

- The new flag controls only the browser notification/polling UI.
- When enabled, behavior remains unchanged.
- When disabled:
  - pages should not load `/assets/version_check.js`
  - pages should not render the app-version notification banner
  - pages should not emit version-check-only metadata needed by that script
- `/api/version` should remain available. It is a general status endpoint and may be useful outside this UI.
- Asset cache-busting with `?v=<appVersion>` should remain unchanged for CSS, theme toggle JS, and route-specific scripts.

## Flag

Use:

```bash
FORUM_APP_VERSION_NOTIFICATION=false
```

Default:

```text
enabled
```

Accepted enabled values should match the existing `envFlagEnabled()` helper:

- `1`
- `true`
- `yes`
- `on`

Any other non-empty value should be treated as disabled. This means `false`, `0`, `no`, and `off` disable the notification.

## Implementation Slice

1. Add `SiteConfig::appVersionNotificationEnabled()` in `src/ForumRewrite/SiteConfig.php`.

   Recommended implementation:

   ```php
   public static function appVersionNotificationEnabled(): bool
   {
       return self::envFlagEnabled('FORUM_APP_VERSION_NOTIFICATION', true);
   }
   ```

2. Pass the flag into the layout from `src/ForumRewrite/View/TemplateRenderer.php`.

   Add a layout variable such as:

   ```php
   'appVersionNotificationEnabled' => SiteConfig::appVersionNotificationEnabled(),
   ```

3. Gate the version notification pieces in `templates/layout.php`.

   Wrap these with `if ($appVersionNotificationEnabled)`:

   - `meta name="app-version"`
   - `meta name="app-version-endpoint"`
   - `script src="<?= $e($versionCheckScriptPath) ?>"`
   - the `.app-version-banner` markup

4. Keep `versionCheckScriptPath` generation in `TemplateRenderer` unless removing it meaningfully simplifies the data contract. The safer minimal change is to keep computing it and only suppress rendering in the layout.

## Test Plan

Update `tests/LocalAppSmokeTest.php`.

Recommended coverage:

1. Preserve the existing default assertions:
   - `meta name="app-version" content="no-git"`
   - `/assets/version_check.js?v=no-git`
   - `data-role="app-version-banner"`

2. Add a disabled-flag assertion by temporarily setting:

   ```php
   putenv('FORUM_APP_VERSION_NOTIFICATION=false');
   ```

   Render a representative page, then assert it does not contain:

   - `meta name="app-version"`
   - `meta name="app-version-endpoint"`
   - `/assets/version_check.js`
   - `data-role="app-version-banner"`
   - `A new version is available.`

3. Restore the environment variable after the test with:

   ```php
   putenv('FORUM_APP_VERSION_NOTIFICATION');
   ```

Leave `tests/VersionCheckBehaviorTest.php` unchanged. That test file validates the standalone JS behavior when the script is loaded; the new flag controls whether the script is loaded at all.

Run:

```bash
php tests/run.php
```

## Documentation

Update:

- `README.md`
- `docs/examples/env.production.example`
- `docs/runbooks/production_deploy.md`

Suggested wording:

```text
FORUM_APP_VERSION_NOTIFICATION=false disables the browser-side app version polling and reload banner. The default is enabled.
```

## Rollout And Rollback

Rollout is config-only after deployment:

```bash
FORUM_APP_VERSION_NOTIFICATION=false
```

Rollback is also config-only:

```bash
FORUM_APP_VERSION_NOTIFICATION=true
```

No read-model rebuild, canonical data migration, or static asset rebuild is required for the PHP fallback path. If prebuilt static HTML artifacts are being served, rebuild those artifacts after changing the flag so the rendered layout matches the intended config.

## Implementation Log

- Slice 1: Added the `FORUM_APP_VERSION_NOTIFICATION` config helper, passed it into the layout, and gated the version metadata, script tag, and banner markup.
- Slice 2: Added smoke coverage proving the notification remains enabled by default and can be disabled with `FORUM_APP_VERSION_NOTIFICATION=false`.
- Slice 3: Documented the flag in the README, production environment example, and production deploy runbook.
