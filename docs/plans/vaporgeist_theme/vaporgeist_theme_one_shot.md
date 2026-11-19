# Vaporgeist theme: Vapor's look at Zalgo's intensity

Cycle: `v3/vaporgeist_theme`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`); [debug log](./vaporgeist_theme_debug_log.md)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled; no real alternatives or work units, so no unit estimates

**Cycle estimate: 0.40 (execution entry)**

Status: planned; next permitted action: planning commit, then implementation.

## Original request

2026-11-19, in chat: "Can you please make a new theme with Zalgo's level of intensity and the aesthetic from the Vapor theme?"

Feedback items: none match (searched `docs/feedback/items/` for vapor and zalgo).

## Authorization

The request names a bounded outcome: one new theme. This record authorizes building it on a branch through an open PR. The name (Vaporgeist) is Claude's choice and easy to change. The theme ships behind its own feature flag, off by default, the same way [Zalgo](../zalgo_theme/zalgo_theme_one_shot.md) does. Excluded: changes to Vapor or to Zalgo's look, merge and deployment.

## Outcome and work type

Application change. With "Vaporgeist theme" on in Tools → Feature Flags, Vaporgeist appears in the theme menu, `/themes`, Preferences and the composer. It has Vapor's palette (cyan ink, hot-pink lines, purple dusk, yellow labels, Trebuchet type) plus a vaporwave sun and perspective grid. On top of that sits Zalgo's corruption at Zalgo's strength: dripping marks, colour split, glitch slices, tilted cards, scanlines and tear lines, all in pink, cyan and violet. With the flag off, nothing changes.

Allowed paths: `public/assets/theme-zalgo.css` (shared), `ThemeRegistry`, `FeatureFlagRegistry`, `SiteProfileRegistry`, theme tests, the theme development guide, this folder, the plans index and the ledger.

## Approach and risks

- Reuse, not a copy: Vaporgeist uses `'stylesheet' => 'zalgo'`, as Gloss Dark uses Gloss's file. Zalgo's rules move from `:root[data-theme="zalgo"]` to a selector covering both themes, and its fixed colours become tokens (`--glitch-hot`, `--glitch-cool`, `--glitch-deep`) that each theme sets. Vaporgeist-only rules (sun, grid, type) follow.
- Risk: the token rewrite changes Zalgo's look. Mitigation: screenshot Zalgo before and after and compare the pixels; any difference is a bug.
- Flag: `FORUM_VAPORGEIST_THEME_ENABLED`, off, site-mutable, Experience group, next to Zalgo's.
- Contrast: Vapor's ink and pink already pass on its background; `theme_contrast_audit.py` must report Vaporgeist OK.

## Reuse decisions

Gating reuses the registry `'flag'` key added for Zalgo. The stylesheet reuses Zalgo's rules through shared tokens and the shared-stylesheet registry option. Palette values come from `theme-vapor.css`. No new component.

## Completion conditions

Flag off: Vaporgeist absent everywhere and Zalgo pixel-identical to before. Flag on: Vaporgeist offered, paints at first paint, readable at 380px with a clean focus ring, motion stops under reduced motion. Release condition: an open PR.

Concepts touched: [page theme](../../concepts/page_theme.md), unchanged.

## Verification and recovery plan

`./v3 test --smoke` plus `ThemeRegistryTest`, `ThemeCatalogPageTest`, `PreferencesPageTest`, `ThreadThemeTest`, `FeatureFlagEvaluatorTest`; the contrast audit; Zalgo before/after pixel comparison; Chrome screenshots of Vaporgeist at 1280px and 380px. Full suite not run. Recovery: switch the flag off.

Starting revisions and branch: `origin/main` `7d9b4b4d`; branch `feat/vapor-zalgo-theme`.

Planning commit: pending.

## Dated execution history

- 2026-11-19: record written.

## Study history

- 2026-11-19 execution entry: **0.40**. Mostly stylesheet work plus a token rewrite of Zalgo's rules; the flag path already exists. Prior exposure: the same agent built Zalgo. Claude single exposed rater; R1 hashes not re-verified; baseline `7d9b4b4d`.

## Outcome

Pending.

## Verification

Pending.

## Final review

Pending.

## Commit/merge/publication status

Planning commit pending; not merged; not deployed.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
