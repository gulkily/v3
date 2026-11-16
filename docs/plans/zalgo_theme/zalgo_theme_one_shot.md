# Zalgo theme behind a feature flag

Cycle: `v3/zalgo_theme`; Mode: one-shot (continued from staged Step 1); Record/index: this file, [Step 1](./zalgo_theme_step1_solution_assessment.md), [plans index](../README.md)

Debug mode: enabled (inherited from Step 1; project marker `.railspike_debug_on`)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled (inherited); no real alternatives or work units remain, so no unit estimates

**Cycle estimate: 0.45 (execution entry)**

Status: implemented and verified locally; committed on `feat/zalgo-theme`, not pushed (this session has no push access to `gulkily/v3`); handed over as a git bundle. Turning the flag on in production is the maintainer's step.

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

Planning commit: `6dc6d388` (plan, Step 1, debug log and index only; confirmed with `git show --stat` before implementation).

## Dated execution history

- 2026-11-16: staged Step 1 transitioned to one-shot on the maintainer's instruction above; Step 1 kept unchanged except for a transition note and moved into this folder. `v3/operator_theme_choice` shelved at an uncommitted Step 1.
- 2026-11-16: planning commit `6dc6d388`; branch `feat/zalgo-theme` from `origin/main` `66856253`.
- 2026-11-16: added `theme-zalgo.css`, the `zalgo` registry entry with `'flag' => FeatureFlagRegistry::ZALGO_THEME_ENABLED`, the flag (off, site-mutable, Experience group) and `zalgo` on the four profiles that list Bubblegum. `ThemeRegistry::permitted()` now takes the evaluator; all six callers pass one (`TemplateRenderer` ×2, `ToolsPageController`, `ThreadTheme` ×2 via `choices()`/`permittedNames()`, `ThemeCatalog`). `ThreadTheme::forThread` builds its evaluator from the repository root like `KeyRetirementPolicy`; the composer gets the renderer's evaluator as `$themeFlags`.
- 2026-11-16 correction: the first border colour `#b3262b` measured exactly 3.0:1 on panels; raised to `#c42e33` (3.51:1) for margin.
- 2026-11-16: two smoke tests failed from this change and were updated: `FeatureFlagEvaluatorTest::testRegistryListsCurrentPublicFlags` (new flag in both lists) and `ThemeRegistryTest::testMenuLayoutPairsMutualCounterpartsAndPacksTheRest` (Zalgo joins the unpaired dark column, shifting Chouse and MIT Rap Club down a row). Added `ThemeRegistryTest::testZalgoIsOfferedOnlyWhileItsFlagIsOn`.
- 2026-11-16: documented the `'flag'` option in the theme development guide.
- 2026-11-16, maintainer feedback on PR #278 (still open): "this theme as you screenshotted it is about 10% zalgo. please make it closer to 75". Same scope (the stylesheet only), same branch. Added: combining-mark drips above and below headings, thread titles and the brand, built from CSS generated content with `content: ... / ""` so screen readers skip them, and stacked with faded vertical copies because monospace fonts draw stacked marks on top of each other; per-row offsets so list rows drip differently; red/cyan split on headings, nav, buttons and meta; tilted, skewed cards with leaking edge marks; scanline and vignette overlay; a faint colour fringe on all text; tilted nav links; and, only without reduced motion, heading glitch slices, dripping, header flicker and two falling tear lines (the tears are not drawn at all under reduced motion). Reading text (`p`, lists, excerpts, row meta) is layered above the drips. Focus stays a clean cyan ring and drops any transform.

## Study history

- 2026-11-15 intake (staged Step 1): 0.40 for Option B; extended estimates A 0.35, B 0.40, C 0.65.
- 2026-11-16 execution entry: **0.45** for Option A plus the flag gate across six `permitted()` callers and their tests. Scope changed from Step 1's recommendation (B to A, plus the flag); prior exposure: the same agent wrote Step 1. Claude single exposed rater; R1 hashes not re-verified; baseline `66856253`.
- 2026-11-16 closeout: delivered the recorded scope; no scope change after entry. Retrospective scope assessment 0.45 (the gate touched the six callers as expected; the two test updates and one colour correction were small). Maintainer fit/acceptance pending.

## Outcome

Delivered Option A plus the flag. With `FORUM_ZALGO_THEME_ENABLED` off (default), Zalgo is absent everywhere; on, it appears in the theme menu, `/themes`, Preferences and the composer, and thread `theme: zalgo` lines apply. Not delivered (by authorization): the Cyan palette, the operator theme panel, the hex-hashtag fix.

Revision (2026-11-16): intensity raised toward "75% zalgo" at the maintainer's request; see history.

Limitations: no `theme-nav-icons.css` set (Zalgo uses the text nav, like Console). The board screenshot covered the empty Liked view from the fixture; a busy board was not seen.

## Verification

- Passed: `./v3 test --smoke ThemeRegistryTest ThemeCatalogPageTest PreferencesPageTest ThreadThemeTest FeatureFlagEvaluatorTest LocalAppSmokeTest::testStaticArtifactBuilderWritesApacheFriendlyArtifactLayout` — 483 run, 483 passed. Full suite not run (the maintainer runs it).
- Passed: `php -l` on every changed PHP file.
- Passed: `python3 -I tests/theme_contrast_audit.py` — Zalgo passes all ten pairs (lowest `--line` on `--panel` 3.51). The audit reports 14 failing older themes, the same count on `main` without this change; out of scope here, and the audit is not part of `./v3 test`.
- Passed: rendered `/` and `/themes/` from the fixture app with the flag off and on. Off: 0 mentions of zalgo (menu, composer option, stylesheet map). On: menu option, composer option and stylesheet present.
- Passed: Chrome (headless) with `zenmemes-theme=zalgo` saved, board and a thread at 1280px and 380px: `data-theme="zalgo"` at first paint, no horizontal overflow, keyboard focus outline solid cyan `rgb(61, 242, 255)`. Screenshots checked by eye.
- Revision checks (2026-11-16): theme tests re-run (483 passed), contrast audit Zalgo all pass, `/recent/` and a thread re-rendered in Chrome at 1280px and 380px with no horizontal overflow and a cyan focus ring; excerpts and post text stay readable over the drips. Animations were screenshotted disabled, so the motion itself was not seen.
- Inapplicable: migrations and read-model schema (none changed). Pending: production, after the maintainer merges and switches the flag on.

## Final review

Diff checked: no duplicated theme lists; the only new file in `public/` is the stylesheet; all six `permitted()` callers covered (`grep` for `permitted(`, `choices(`, `permittedNames(`, `entries(`). The touched concept (page theme) is used unchanged. Debug log updated.

## Commit/merge/publication status

Planning commit `6dc6d388`; completion commit `ae32bec6` (confirmed with `git log`); this record-only handoff commit follows. Push blocked: the session's git proxy has no credential for `gulkily/v3`, and adding the repository was refused. Not pushed, no PR, not merged, not deployed.

*— Claude · 2026-11-16 UTC · Model: claude-opus-5-5*
