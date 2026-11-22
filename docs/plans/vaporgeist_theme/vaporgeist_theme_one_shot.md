# Vaporgeist theme: Vapor's look at Zalgo's intensity

Cycle: `v3/vaporgeist_theme`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`); [debug log](./vaporgeist_theme_debug_log.md)

Effort study: `railspike-effort`, protocol P2, reference R1; [cycle ledger](../effort_ledger/vaporgeist_theme.md)

Extended estimates: enabled; no real alternatives or work units, so no unit estimates

**Cycle estimate: 0.40 (execution entry)**

Status: implemented, revised on maintainer feedback (2026-11-20) and verified locally; PR #341 open.

## Original request

2026-11-19, in chat: "Can you please make a new theme with Zalgo's level of intensity and the aesthetic from the Vapor theme?"

Feedback items: none match (searched `docs/feedback/items/` for vapor and zalgo).

## Authorization

The request names a bounded outcome: one new theme. This record authorizes building it on a branch through an open PR. The name (Vaporgeist) is Claude's choice and easy to change. The theme ships behind its own feature flag, off by default, the same way [Zalgo](../zalgo_theme/zalgo_theme_one_shot.md) does. Excluded: changes to Vapor or to Zalgo's look, merge and deployment.

## Outcome and work type

Application change. With "Vaporgeist theme" on in Tools → Feature Flags, Vaporgeist appears in the theme menu, `/themes`, Preferences and the composer. It has Vapor's palette (cyan ink, hot-pink lines, purple dusk, yellow labels, Trebuchet type) plus a vaporwave sun and perspective grid. On top of that sits Zalgo's corruption at Zalgo's strength: dripping marks, colour split, glitch slices, tilted cards, scanlines and tear lines, all in pink, cyan and violet. With the flag off, nothing changes.

Allowed paths: `public/assets/theme-vaporgeist.css` (from 2026-11-20; earlier `theme-zalgo.css`, shared), `ThemeRegistry`, `FeatureFlagRegistry`, `SiteProfileRegistry`, theme tests, the theme development guide, this folder, the plans index and the ledger.

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

Planning commit: `bf0b83d5` (record, debug log and index only).

## Dated execution history

- 2026-11-19: record written; planning commit `bf0b83d5`.
- 2026-11-19: baseline Zalgo screenshots (Recent and a thread, 1280px and 380px) taken before any change.
- 2026-11-19: Zalgo's shared rules rewritten against `--glitch-hot`, `--glitch-cool`, `--glitch-deep` and four helper tokens (vignette, link hover, placeholder, image filter); its fixed rgba colours became `color-mix(... transparent)`; the selector became `:root:is([data-theme="zalgo"], [data-theme="vaporgeist"])`. Zalgo's token block, swatch and menu preview keep literal colours.
- 2026-11-19: added Vaporgeist's tokens (Vapor's palette; hot pink, cyan `#01cdfe`, violet), its own mark strings, swatch and menu preview, and Vaporgeist-only rules: a striped sun sinking behind a perspective grid, drawn as extra layers of the page background with per-layer size and position; uppercase wide headings; yellow eyebrows; a pink-cyan-yellow header rule. Registry entry with `'stylesheet' => 'zalgo'` and `'flag'`; flag `FORUM_VAPORGEIST_THEME_ENABLED` (off, site-mutable, Experience); listed on the four profiles that list Zalgo.
- 2026-11-19 correction: the stylesheet header first read "Themes: zalgo, vaporgeist", which broke `testEveryExplicitThemeHasAnIsolatedStylesheet` (it looks for `Theme: <name>`); changed to one `/* Theme: ... */` line per theme, as Gloss does.
- 2026-11-19 correction: the sun's gradient ended before its box, so its stripes ran past the edges; the sun now fills its box and the stripe band is narrower than the sun.
- 2026-11-19: updated `FeatureFlagEvaluatorTest` flag lists and `ThemeRegistryTest::testMenuLayoutPairsMutualCounterpartsAndPacksTheRest` (Vaporgeist joins the unpaired dark column); added `testVaporgeistIsOfferedOnlyWhileItsFlagIsOnAndSharesZalgosStylesheet`; documented the shared glitch tokens in the theme development guide.
- 2026-11-20, maintainer feedback on PR #341 (still open): "can you please make it a bit less chaotic, and more cyberpunk/vaporwave. it looks like the zalgo personality is still strong in it, and we want to remove htat." Same scope, same branch.
- 2026-11-20: Vaporgeist no longer shares Zalgo's stylesheet; it has `public/assets/theme-vaporgeist.css`. With no second user, Zalgo's `--glitch-*` token rewrite was dropped: `theme-zalgo.css` and the theme guide are back to the branch point (diff empty), so this PR no longer touches Zalgo.
- 2026-11-20: new Vaporgeist look: neon-tube headings and brand (white core, pink and violet glow), cyan glowing thread titles, smoked-glass cards with cyan corner brackets, faint scanlines, a cyan-pink-violet header line, neon nav and buttons, a yellow focus ring. Kept: Vapor's palette and type, the striped sun. The flat grid is replaced, on pages with the app shell, by a real perspective grid on the decorative `.shell-sky` element (behind content, `z-index: -1`) that runs toward the viewer; pages without it keep the flat grid (`:has(.shell-sky)` switches). Motion: the grid run and a rare neon flicker on the brand, both off under reduced motion. Removed: dripping marks, colour split, glitch slices, tilted cards, tear lines and the text fringe.
- 2026-11-20 correction: wide heading letter-spacing broke a long word at 380px; headings use 0.04em below 40rem.
- 2026-11-20: ledger entry moved to `docs/plans/effort_ledger/vaporgeist_theme.md` (main froze the shared ledger after this cycle started); the shared ledger is back to the branch point.
- 2026-11-20: merged `origin/main` `597f5328` into the branch to clear PR #341's conflicts with the new Ochre theme (site profile list, menu-layout test, plans index); kept both sides. Theme and flag tests: 495 passed.

## Study history

- 2026-11-19 execution entry: **0.40**. Mostly stylesheet work plus a token rewrite of Zalgo's rules; the flag path already exists. Prior exposure: the same agent built Zalgo. Claude single exposed rater; R1 hashes not re-verified; baseline `7d9b4b4d`.
- 2026-11-19 closeout: delivered the recorded scope; retrospective 0.40 (the token rewrite and pixel check were the bulk; two small corrections). Maintainer acceptance pending.
- 2026-11-20 revision: same scope, new look; whole-cycle retrospective 0.50.

## Outcome

Revised 2026-11-20: Vaporgeist is now its own neon cyberpunk/vaporwave theme with its own stylesheet and no Zalgo corruption; Zalgo is untouched by this PR. The 2026-11-19 outcome below is superseded.

Delivered as planned. Vaporgeist: Vapor's colours and type, a vaporwave sun and grid, and Zalgo's full corruption in pink, cyan and violet, behind its own flag. Zalgo unchanged.

Limitations: the stripes stop short of the sun's widest edges (a band narrower than the sun keeps them from spilling past it near the horizon). Motion was screenshotted disabled, so the animations were not watched. No nav icon set, like Vapor and Zalgo.

## Verification

- 2026-11-20 revision: `./v3 test --smoke ThemeRegistryTest ThemeCatalogPageTest PreferencesPageTest ThreadThemeTest FeatureFlagEvaluatorTest` — 493 passed (full suite not run); contrast audit Zalgo and Vaporgeist pass; Chrome at 1280px and 380px on `/recent/` and a thread: no overflow, yellow focus ring, title no longer breaks mid-word; `theme-zalgo.css` diff against the branch point empty. Motion screenshotted disabled.
- Earlier (2026-11-19, shared-stylesheet version):
- Passed: `./v3 test --smoke ThemeRegistryTest ThemeCatalogPageTest PreferencesPageTest ThreadThemeTest FeatureFlagEvaluatorTest` — 493 run, 493 passed. Full suite not run (the maintainer runs it).
- Passed: Zalgo before/after pixel comparison on all four screenshots: largest channel difference 1/255 (alpha rounding from `color-mix`, in the faint tear lines), not visible.
- Passed: `python3 -I tests/theme_contrast_audit.py` — Zalgo and Vaporgeist pass every pair.
- Passed: Chrome (headless), Vaporgeist on `/recent/` and a thread at 1280px and 380px: `data-theme="vaporgeist"` at first paint, no horizontal overflow, focus ring solid `#01cdfe`; reading text stays above the drips. Screenshots checked by eye.
- Inapplicable: migrations, read-model schema. Pending: production, after merge and switching the flag on.

## Final review

One stylesheet serves both themes; no copied rules. Registry, flag and profiles follow the Zalgo pattern. Concept unchanged. Debug log updated.

## Commit/merge/publication status

Planning commit `bf0b83d5`; completion commit follows; PR to be opened. Not merged; not deployed.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*


## PR 341 merge recovery — 2026-11-21

Request: "can you please help me fix pr 341"
Mode: Direct, bounded merge recovery under the supplied task instructions; retain the existing implementation record.
Feedback: No matching feedback item found in docs/feedback/items/ for Vaporgeist or the Vapor theme.
Problem: PR 341 is open but cannot merge because both branches extended the Experience flag presentation list.
Reuse: Preserve FeatureFlagRegistry::PRESENTATION_ORDER as the canonical ordering contract. Keep Vaporgeist after Zalgo and agent check-in after the agent prompt. Reuse the existing registry completeness, flag gating and theme consumer tests; no new component or behavior.
Scope: Merge current origin/main into the open PR branch and resolve the one flag-list conflict. No visual redesign, dependencies, schema changes, deployment or PR merge.
Completion: The combined branch passes smoke and affected flag/theme checks, and GitHub reports the updated PR mergeable. Any failed check must be investigated before pushing; baseline failures are reported separately. No force push.
Verification: Smoke plus ThemeRegistryTest, ThemeCatalogPageTest, PreferencesPageTest, ThreadThemeTest and FeatureFlagEvaluatorTest ran 495 tests: 491 passed and four Unicode tests failed. FeatureFlagChangePageTest and SiteProfileRegistryTest: 12 passed. The four failures replay identically on unchanged origin/main bc16f7a77: AgentPostingApiTest::testSignedControlDiagnosticsBeforeGenericParserFailure, AgentPostingApiTest::testRulesErrorsAndLegacyCompatibility, SingleRequestHttpTest::testUnicodeRoundTripAndSymbolRejection and SignedPostBundleValidatorTest::testStrictSubsetAndExactBytes. This environment has PHP 8.1.2 and PCRE 10.39; UnicodeTextPolicy emits an unsupported Emoji_Presentation property warning. These failures are outside the theme/merge repair. No new test failures found.
Review: The only manual code resolution combines both flag-list additions. No markup or styling changed by the resolution; rendered phone/desktop checks were not repeated. Dependency delta: zero. Full suite not run. Production not deployed. Merge resolution commit: 269e45249, pushed to feat/vapor-zalgo-theme. GitHub reports PR 341 OPEN, MERGEABLE and CLEAN. The PR remains unmerged. Diff check against origin/main passes; the merge-wide check also exposed pre-existing trailing whitespace in the imported zen_hero plan, which was retained unchanged.
