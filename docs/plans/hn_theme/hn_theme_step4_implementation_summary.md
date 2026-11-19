> **Feature plan:** [Step 1](./hn_theme_step1_solution_assessment.md) · [Step 2](./hn_theme_step2_feature_description.md) · [Step 3](./hn_theme_step3_development_plan.md) · [Step 4](./hn_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/hn_theme`; host ledger [hn_theme.md](../effort_ledger/hn_theme.md). Standing P2 enrollment.

Extended estimates: enabled. Stage estimates are copied from Step 3, not newly rated.

**Cycle estimate: 0.65**

Branch: `hn-theme`, rebased onto `origin/main` `a1cefffa`; planning commit `aab00ed6`.

Step 4 entry (2026-11-19 UTC): Step 3 projection **0.65** reaffirmed after the planning commit, before implementation. Claude, same exposed rater.

Stage 3 open question (hide board excerpts): not answered at approval; the plan's default, hide, applies.

## Stage 1 - Registry entry, palette and top bar

**Original estimate: 0.10**

**Retrospective stage work: 0.10**

- Changes:
  - `ThemeRegistry.php`: an `ochre` entry (light), last in the list so other menus keep their order; `ochre` → `dark` in `COUNTERPARTS`. The `script` flag waits for Stage 4.
  - `SiteProfileRegistry.php`: `ochre` added to the `zenmemes` profile's `permittedThemes`. Step 2's inventory missed this list; it is an in-scope refinement, since a theme a site does not permit is never offered.
  - New `public/assets/theme-ochre.css`: the palette, swatch and orange bar. On desktop the bar holds the name and plain-text destinations; the current page is white on black. Phones keep the shared bottom tab bar, made beige with an orange rule, and the name gets an orange strip at the top.
  - `LocalAppSmokeTest.php`: allow-list and option check; `ThemeRegistryTest.php`: Ochre's unpaired menu row.
- Verification:
  - `./v3 test --smoke ThemeRegistryTest LocalAppSmokeTest ThemeCatalogPageTest NavIconSetsTest`: passed (478 smoke plus the extra classes; the first run failed only on the expected menu-layout row, then fixed).
  - `python3 -I tests/theme_contrast_audit.py . report.json`: Ochre OK. Other themes' listed failures predate this change; this is an audit, not a suite test.
  - `build_static_artifacts.php` with `FORUM_STATIC_DETAIL_PAGES_ENABLED=true` on a fixture with a ported thread: the baked thread page's allow-list includes `ochre` and links `theme-ochre.css`. Without the rebuild, older baked pages keep their old list.
  - Screenshots at 1280px and 380px of the board, Recent, a thread, a ported thread and Tools (headless Chrome, outside the repository).
- Notes: the base rules for the app header tie on specificity with Ochre's and load later, so Ochre's header rules use `.site-header.site-header--app`. The phone layout departs from the mockup's wrapping top nav: the shared tab bar stays, so phone navigation keeps working.
- Estimate details: original scope as Step 3, plus the profile list; no revision.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*

## Stage 2 - Thread styling

**Original estimate: 0.10**

**Retrospective stage work: 0.08**

- Changes:
  - `theme-ochre.css`: posts become plain text blocks (no border or panel). Each has a grey 12px line naming who and when, 13.33px text, and small underlined Reply, Like and Flag. The root title is 15px and regular weight. Continuations sit closer to the post they continue. A `:target` jump gets a pale orange wash.
  - The content column widens from 760px to 880px. Composers, the Activity drawer and other cards keep a thin beige frame; inputs are white with a grey border.
  - The shared depth indent (1rem per level, capped by `ReplyTree::MAX_INDENT_DEPTH`) is kept unchanged as Ochre's thin indent.
- Verification:
  - Screenshots at 1280px and 380px of a thread and a ported thread nested 9 deep: every action stays visible.
  - Computed styles: metadata colour `rgb(107, 107, 107)`, 12px from the grey line to the text.
  - Contrast audit: Ochre OK.
- Notes: no markup changed. The Activity drawer only appears with title-only replies, which the fixture lacks; it is covered by the general `.card` rule and checked again in Stage 5.
- Estimate details: original scope as Step 3; no revision.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*

## Stage 3 - Board, Recent and Tools

**Original estimate: 0.10**

**Retrospective stage work: 0.08**

- Changes:
  - `theme-ochre.css`: `thread-rows` lose their box and number their rows with a CSS counter in a narrow grey column. Each row is a 13.33px title (grey once visited), an 11px grey line, and the reply count as small grey text at the right. Hidden rows take no number. The pinned and latest lists number separately.
  - Excerpts and thumbnails are hidden, per the plan's default for the open question.
  - Board controls, pagination and Tools launchers become plain small words instead of buttons; the current one is bold.
- Verification:
  - Screenshots at 1280px and 380px of the board (`/threads/`), Recent and Tools.
  - Board composer at 380px: focusing it expands it, showing Post, Post anonymously, Save to Outbox and Discard draft.
  - Contrast audit: Ochre OK.
- Notes: the excerpt question was not answered at Step 3 approval; one rule restores excerpts if the maintainer wants them.
- Estimate details: original scope as Step 3; no revision.

*— Claude · 2026-11-19 UTC · Model: claude-opus-5-5*
