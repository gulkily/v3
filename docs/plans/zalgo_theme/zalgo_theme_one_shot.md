# Zalgo theme behind a feature flag

Cycle: `v3/zalgo_theme`; Mode: one-shot (continued from staged Step 1); Record/index: this file, [Step 1](./zalgo_theme_step1_solution_assessment.md), [plans index](../README.md)

Debug mode: enabled (inherited from Step 1; project marker `.railspike_debug_on`)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled (inherited); no real alternatives or work units remain, so no unit estimates

**Cycle estimate: 0.45 (execution entry)**

Status: planned; next permitted action: planning commit, then implementation.

## Original request

The ZenMemes thread [Theme plan: Zalgo](https://zenmemes.com/threads/thread-20261114052859-08a0e3e3) (ilyag, 2026-11-14), preserved verbatim in [Step 1](./zalgo_theme_step1_solution_assessment.md#original-query). Step 1 recommended Option B (one look with Red and Cyan palettes).

2026-11-16, before Step 1 was approved: "claude, with this theme, i think we should first introduce a control panel for the operator to decide which themes are selectable on this server." That started `v3/operator_theme_choice` (Step 1 written, not committed).

2026-11-16: "ok, let's shelve this for now, and just make an on off zalgo theme feature flag"

Feedback items: none match (searched `docs/feedback/items/` for zalgo, glitch and corrupt).

## Authorization

The 2026-11-16 message shelves the operator panel and authorizes building Zalgo now, switched on and off by a feature flag. "Just" is read as the smallest version, so this record takes Step 1's **Option A** (one look, red accents, CSS only) and adds the flag. The Cyan palette (Option B) is left for a follow-up. Exclusions: the operator theme panel, palettes, any theme script, the hex-hashtag bug, merge and deployment.

## Outcome and work type

Application change. An operator turns on "Zalgo theme" on Tools → Feature Flags; Zalgo then appears in the theme menu, the theme catalog, Preferences and the composer's theme choice, and a thread's `theme: zalgo` line applies. With the flag off (the default), Zalgo appears nowhere, and anyone with Zalgo saved sees the site default.

Allowed paths: `public/assets/theme-zalgo.css` (new), `ThemeRegistry`, `FeatureFlagRegistry`, `SiteProfileRegistry` (Zalgo joins the profiles that list Bubblegum), the call sites of `ThemeRegistry::permitted()` (`TemplateRenderer`, `ThreadTheme`, `ThemeCatalog`, `ThemeCatalogPageController`, `ToolsPageController`, `Application` wiring), `templates/partials/composer.php`, the theme development guide, theme tests, this record, the plans index, the ledger and the debug log.

## Approach and risks

- The flag: `FORUM_ZALGO_THEME_ENABLED`, off by default, site-mutable, in the Experience group like the Engagify flag.
- One gate: a registry entry can name a feature flag (`'flag' => ...`). `ThemeRegistry::permitted()` takes the evaluator and drops a gated theme whose flag is off. Every caller already goes through `permitted()`, so they all pass the evaluator they have (or build one from the repository root, as `KeyRetirementPolicy` does). Risk: a caller missed would show Zalgo with the flag off, or hide it with the flag on. Mitigation: `grep` for every `permitted(` call; a test covers both flag states.
- A saved Zalgo preference with the flag off: the pre-paint script only applies themes in the page's allow-list, and the allow-list comes from `permitted()`, so it falls back with no extra code. A flag change already retires the static release (`StaticArtifactInvalidator::invalidateFeatureFlags`), so baked pages pick up the new list.
- The stylesheet follows `theme-bubblegum.css` (tokens, swatch, menu option, element rules) and Step 1's shared rules: link red `#ff1a1a` (5.2:1 on `#0a0505`), a solid cyan focus ring that no border uses, motion only on hover with a `prefers-reduced-motion` stop, no combining characters, usable at 380px.

## Reuse decisions

- Gating reuses `FeatureFlagEvaluator`, `FeatureFlagRegistry` and the Tools → Feature Flags page; no new page or record.
- The theme reuses the registry, the per-theme stylesheet loading, the swatch and menu-option preview custom properties that `/themes` reads (`ThemeCatalog`), and the shared nav icons (Zalgo uses the text-only nav like Console, so `theme-nav-icons.css` is untouched).

## Completion conditions

Flag off: no Zalgo in the theme menu, `/themes`, Preferences or the composer; a `theme: zalgo` thread renders in the site default. Flag on: all four offer Zalgo, choosing it paints Zalgo at first paint, and the thread directive applies. `theme_contrast_audit.py` reports Zalgo OK. Release condition: an open PR; turning the flag on in production is the maintainer's step.

Concepts touched: [page theme](../../concepts/page_theme.md), used unchanged (a gated theme is simply not permitted). No inventory change proposed.

## Verification and recovery plan

`./v3 test --smoke` plus `ThemeRegistryTest`, `ThemeCatalogPageTest`, `PreferencesPageTest`, `ThreadThemeTest` and a new flag test; `python3 tests/theme_contrast_audit.py`; render pages locally in both flag states and look at Zalgo at desktop and 380px widths. Full suite not run (maintainer runs it). Recovery: turning the flag off removes Zalgo everywhere.

Starting revisions and branch: `origin/main` `66856253`; branch `feat/zalgo-theme`.

Planning commit: pending.

## Dated execution history

- 2026-11-16: staged Step 1 transitioned to one-shot on the maintainer's instruction above; Step 1 kept unchanged except for a transition note and moved into this folder. `v3/operator_theme_choice` shelved at an uncommitted Step 1.

## Study history

- 2026-11-15 intake (staged Step 1): 0.40 for Option B; extended estimates A 0.35, B 0.40, C 0.65.
- 2026-11-16 execution entry: **0.45** for Option A plus the flag gate across six `permitted()` callers and their tests. Scope changed from Step 1's recommendation (B to A, plus the flag); prior exposure: the same agent wrote Step 1. Claude single exposed rater; R1 hashes not re-verified; baseline `66856253`.

## Outcome

Pending.

## Verification

Pending.

## Final review

Pending.

## Commit/merge/publication status

Planning commit pending; not merged; not deployed.

*— Claude · 2026-11-16 UTC · Model: claude-opus-5-5*
