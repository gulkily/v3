# Gloss default — one-shot

Cycle: `v3/gloss_default`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Effort study: `railspike-effort` / `v3/gloss_default`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_estimation_trial.md))

**Cycle estimate: 0.10**

Status: technically complete on `feat/gloss-default`; pushed, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Feedback item: [PF-20261111-F03](../../feedback/items/PF-20261111-F03.txt), created for this cycle (no existing item matched).

Request, 2026-11-11, from the maintainer in a Claude session: "Can we please make gloss and gloss dark the default themes on Zen memes?" Exclusions: merge, deployment.

## Outcome

On ZenMemes, Auto/System (still the default, and still what a visitor with no saved choice gets) shows Gloss for a light device setting and Gloss Dark for a dark one, and follows a live change of the setting. Saved explicit choices, including Light and Dark, are untouched.

## Approach and reuse decisions

- **Keep Auto as the default, change what it resolves to.** Setting `defaultTheme` to `gloss` would give everyone Gloss regardless of the device setting; the request names both halves of the pair. A new optional site profile field `autoThemes` (`['light' => ..., 'dark' => ...]`, default Light and Dark) names the pair; `SiteProfileRegistry::autoThemes()` reads it and `validate()` requires permitted explicit themes. Only the zenmemes profile sets it; chouse, qdb and mitrapclub are unchanged.
- **Where Auto is resolved.** The layout carries the pair as `data-auto-light-theme` / `data-auto-dark-theme` on `<html>`; the inline head script and `theme_toggle.js` `systemTheme()` read them, so first paint and later changes agree. The server's no-hint initial stylesheet uses the light half (Gloss) instead of Light.
- **`data-theme` under Auto.** Gloss CSS selects on `data-theme`, which Auto used to leave unset (Light and Dark follow the device in `site.css`). When Auto shows a theme other than plain Light/Dark, the head script and `applyTheme()` set `data-theme` to it; the media-query listener re-applies on a device change. The saved choice stays `auto` in storage, so the menu still shows Auto/System.
- **Untouched:** the standalone (Forte docs) layout only derives a light/dark scheme, which is unchanged.

## Verification

- `./v3 test --smoke SiteProfileRegistryTest ProfileThemePresentationTest ThemeRegistryTest`: 461 run, 461 passed. Full suite not run.
- Updated `LocalAppSmokeTest::testLayoutUsesOnlyAValidatedThemeHintForTheInitialStylesheet` (no-hint ZenMemes stylesheet is now Gloss); `ProfileThemePresentationTest` now checks the auto attributes and the auto light stylesheet per profile; new `SiteProfileRegistryTest::testAutoThemesDefaultToLightAndDarkAndMustBePermitted`.
- Headless Chrome, 390px, local server: Auto in light and dark rendered the same as explicit Gloss and Gloss Dark (only a caret differed); emulating a light-to-dark change switched to Gloss Dark live; a saved Light choice still showed Light.
