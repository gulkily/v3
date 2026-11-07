> **Feature plan:** [Step 1](./engagify_theme_step1_solution_assessment.md) · [Step 2](./engagify_theme_step2_feature_description.md) · [Step 3](./engagify_theme_step3_development_plan.md) · [Step 4](./engagify_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/engagify_theme`; [host ledger](../effort_estimation_trial.md#cycle-v3engagify_theme). Standing P2 enrollment. No parent cycle; `v3/theme_directive` (planned on branch `plan/engagify_theme`) depends on this cycle.

Extended estimates: enabled

Cycle estimate: 0.80

Concepts touched: proposed "theme script" (created in Stage 1, entry written in Stage 8); proposed "page theme" (created in Stage 3, entry written in Stage 8); [theme navigation icons](../../concepts/theme_navigation_icons.md) (used unchanged; Engagify adds no icon set).

## Completion Contract

- Normal entry: a reader opens the cookie notice thread, `thread-20261102152258-25e82c97`, or picks Engagify from the theme menu.
- End-to-end outcome: the thread shows Engagify and its five core nags. Exit returns the reader's own theme without a reload, and a revisit stays on it. A reader who picked Engagify gets their previous theme back on Exit.
- Recovery: a failed theme script leaves Engagify colors and a visible Exit. JavaScript off shows the site default theme and no nags. Switching away removes every Engagify element and timer.
- Deployment: the release rebuilds baked thread pages, so the cookie thread's baked file carries its page theme.
- Release condition: smoke and changed tests pass; a browser sweep covers a fresh browser and one with a saved theme, at desktop and 380px, by mouse and keyboard. The full suite is not run.

## Key Risks

- **High risk:** **Pre-paint precedence.** The inline script in `layout.php` now weighs a page theme, a remembered Exit and a saved theme. Impact: a wrong order traps readers in Engagify or flashes the wrong theme. Early validation: Stage 3 tests each combination in `LocalAppSmokeTest`. Mitigation: one precedence function, page theme first unless that page has a remembered Exit.
- **Script cleanup.** Impact: nags or timers survive a theme switch. Early validation: Stage 1 switches a theme with a script to Word 97 and back. Mitigation: every theme script registers one start and one stop entry point, and `theme_toggle.js` calls them.
- **Covering controls.** Impact: overlays hide Reply or the theme menu. Early validation: Stage 5 tabs through the thread at 380px. Mitigation: Exit comes first in each nag's tab order; the theme menu stays above overlays.
- **Baked pages.** Impact: the cookie thread's baked file lacks its page theme. Early validation: Stage 4 builds that file and reads its page theme mark. Mitigation: the layout renders the mark, so the builder bakes it with no change.

## Stage 1

**Estimate: 0.12**

- Goal: a theme can ship a script that runs only while it is active.
- Dependencies: none.
- Expected changes: `ThemeRegistry.php` entries gain an optional script, with `scriptPaths()` beside `stylesheetPaths()`. `TemplateRenderer.php` passes fingerprinted script paths to `layout.php`, whose pre-paint script loads the active theme's script. `theme_toggle.js` stops the old theme's script and starts the new one on every switch.
- Verification approach: `ThemeRegistryTest` cases for script paths; a browser check that switches to and from a stub theme script and finds no leftovers.
- Estimate details: driver: start/stop wiring through the existing stylesheet swap; uncertainty: low to medium.
- Risks or open questions: script cleanup, as in Key Risks.
- Canonical components/API contracts touched: `ThemeRegistry::stylesheetPaths` pattern (extended), the `layout.php` pre-paint script and `window.forumThemeStylesheetPaths` (extended), `theme_toggle.js` theme selection (extended).

## Stage 2

**Estimate: 0.10**

- Goal: Engagify is a selectable theme with its look and an Exit control that restores the previous theme.
- Dependencies: Stage 1.
- Expected changes: an `engagify` registry entry and `zenmemes` permitted theme; `theme-engagify.css` with variables, swatch and scoped garnish; `theme-engagify.js` with an always-visible Exit. Choosing Engagify remembers the theme before it; Exit restores that theme.
- Verification approach: `ThemeRegistryTest` and `LocalAppSmokeTest` allow-list updates; `theme_cycle.cjs`; select Engagify, Exit, confirm the earlier theme returns.
- Estimate details: driver: a bold stylesheet that leaves controls usable; uncertainty: low.
- Risks or open questions: the contrast audit in `theme_contrast_audit.py` may flag the parody palette; fix contrast, keep the joke.
- Canonical components/API contracts touched: the theme guide's file pattern, `zenmemes-theme` storage key (reused), `SiteProfileRegistry` permitted themes (extended).

## Stage 3

**Estimate: 0.14**

- Goal: a page in the site profile shows its own theme by default, and Exit turns it off for that page.
- Dependencies: Stage 2.
- Expected changes: `SiteProfileRegistry.php` profiles gain a validated page theme list; `zenmemes` maps the cookie notice thread to Engagify. `TemplateRenderer.php` resolves the request path and passes a page theme mark to `layout.php`. The pre-paint script applies it unless that page has a remembered Exit. Exit and any menu choice on that page record the Exit. `standalone_layout.php` is checked and left alone if no thread page uses it.
- Verification approach: `SiteProfileRegistryTest` for validation; `LocalAppSmokeTest` for fresh, saved-theme and exited visits; a browser check on the cookie thread.
- Estimate details: driver: precedence in the pre-paint script; uncertainty: medium.
- Risks or open questions: pre-paint precedence, as in Key Risks.
- Canonical components/API contracts touched: `data-default-theme` precedence (extended), `SiteProfileRegistry::validate` (extended).

## Stage 4

**Estimate: 0.05**

- Goal: baked thread pages carry their page theme.
- Dependencies: Stage 3.
- Expected changes: none expected in `StaticArtifactBuilder.php`; a test that bakes the cookie thread and finds its page theme mark.
- Verification approach: `php scripts/build_static_artifacts.php` locally, then read the cookie thread's file.
- Estimate details: driver: confirming the builder path; uncertainty: low.
- Risks or open questions: baked pages, as in Key Risks.
- Canonical components/API contracts touched: `StaticArtifactBuilder` (used unchanged).

## Stage 5

**Estimate: 0.10**

- Goal: the cookie banner, "Open in app" bar and notification prompt.
- Dependencies: Stage 2.
- Expected changes: three nag pieces in `theme-engagify.js`, each with dismiss and Exit, styled in `theme-engagify.css`.
- Verification approach: browser check of each nag by mouse and keyboard at 380px; switch away and confirm removal.
- Estimate details: driver: keyboard order and overlay layering; uncertainty: low.
- Risks or open questions: covering controls, as in Key Risks.
- Canonical components/API contracts touched: Stage 1 start/stop contract (used).

## Stage 6

**Estimate: 0.12**

- Goal: the newsletter popup with a guilt-trip decline, and the rate-us flow from the mockup.
- Dependencies: Stage 5.
- Expected changes: two delayed nag pieces. The rate-us flow asks "Enjoying ZenMemes?", sends low ratings to a private box, asks five-star raters to review elsewhere, and returns after "Remind me later".
- Verification approach: walk every rate-us branch; confirm its timer stops on Exit and on a theme switch.
- Estimate details: driver: the rate-us branches and timer; uncertainty: low.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: Stage 1 start/stop contract (used).

## Stage 7

**Estimate: 0.12**

- Goal: stretch nags, only if Stages 1–6 finish within plan.
- Dependencies: Stage 6.
- Expected changes: fake sign-up toasts, countdown, chat bubble, paywall fade, stacked sticky bars and "Summarize with AI" buttons, each a separate piece. Unbuilt ones are recorded as a follow-up.
- Verification approach: as Stage 5.
- Estimate details: driver: count of nags built; uncertainty: medium.
- Risks or open questions: the paywall fade must not hide the thread for keyboard readers.
- Canonical components/API contracts touched: Stage 1 start/stop contract (used).

## Stage 8

**Estimate: 0.10**

- Goal: documentation and the release sweep.
- Dependencies: Stages 1–6, and Stage 7 if built.
- Expected changes: theme guide sections on theme scripts, page themes and rebuilding; concept entries for theme script and page theme.
- Verification approach: smoke plus changed tests; a browser sweep of the cookie thread and one other page, fresh and with a saved theme, at desktop and 380px; review the full diff for duplication and scope.
- Estimate details: driver: the sweep across browser states; uncertainty: low.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `docs/runbooks/theme_development_guide.md` (extended), `docs/concepts/` (two entries added).

## Study checkpoint

2026-11-07T11:58:20Z, `railspike-effort` / `v3/engagify_theme`, Step 3 handoff. Step 2 explicitly approved after its revision. Scope v5 unchanged. Starting revision `f343b6269a3b786e4ebe1ab383096f2109461933`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Whole-cycle projected total **0.80** reaffirmed. Stage-work originals 0.12/0.10/0.14/0.05/0.10/0.12/0.12/0.10 are independent and do not add up. No implementation, measured labor or accepted outcome.

## Enhancement Requests

Carried from Step 2: page themes declared inside a thread (`v3/theme_directive` (planned on branch `plan/engagify_theme`), approval pending there); stretch nags are Stage 7; the CSS-only alternative remains declined.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
