> **Feature plan:** [Step 1](./gloss_theme_step1_solution_assessment.md) · [Step 2](./gloss_theme_step2_feature_description.md) · [Step 3](./gloss_theme_step3_development_plan.md) · [Step 4](./gloss_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/gloss_theme`; [host ledger](../effort_estimation_trial.md#cycle-v3gloss_theme). Standing P2 enrollment. Related to `v3/engagify_theme`; not a child cycle.

Extended estimates: enabled

Cycle estimate: 0.90 (revised; original 0.55)

Status: Step 1 approved by the maintainer on 2026-11-07 ("approved step 1") with Revisions 1–6. [Step 2](./gloss_theme_step2_feature_description.md) consolidates the approved direction. Current direction is in [Revision 5](#revision-5--option-b-current-direction) as amended by [Revision 6](#revision-6--open-questions-answered), with the comparison, file list and background in [Revision 4](#revision-4--decisions-and-current-direction).

## Original Query

Which theme is visually the closest to Engagify? We should exit into that theme when we click the Exit button. Also, some of the things in this theme are not bad; maybe we can make it into a new theme? Write Step 1 for that.

## Understood Intent

Engagify's look without the nags is a decent modern theme: soft lavender page, white rounded cards, pill buttons, a violet accent and a sans-serif face. Make that look its own selectable theme, working name Gloss. Exit from Engagify should then land on the theme that looks most like it, so leaving feels like the noise stops rather than the site changing.

Today no theme is close. Whitehot comes nearest: almost the same page color, ink and white panels. It differs in its monospace font, amber accent, scanlines and square corners. Light uses a white page with blue serif text and is further away.

## Problem

Engagify's look exists only inside a parody theme, and its Exit returns the viewer to a theme that looks nothing like it.

Concepts touched: [theme script](../../concepts/theme_script.md) (used unchanged); [page theme](../../concepts/page_theme.md) (Exit target changes, every option); [theme navigation icons](../../concepts/theme_navigation_icons.md) (used unchanged in A and B, extended in C). Matching feedback: [PF-20261104-F13](../../feedback/items/PF-20261104-F13.txt) "Modern theme with icons" (partly, fully in C) and [PF-20261030-F46](../../feedback/items/PF-20261030-F46.txt) "Rounder corners in modern themes".

## Shared by every option

- Gloss is a normal entry in `ThemeRegistry.php` and a permitted theme on ZenMemes, so the theme menu, cycle button and pre-paint script pick it up.
- Gloss has no script, no nags, no "NEW" badge and no Exit pill. It must pass the same contrast and layout checks as other themes on every page, not only on threads.
- Exit from Engagify always switches to Gloss. This replaces the current rule, which restores the previous theme or the viewer's saved theme. See the open question below for page theme threads.

## Option A — Gloss as a separate copy

**Estimate: 0.45**
Main driver: styling every page in a new theme and checking it at two widths. Uncertainty: low to medium.

Copy Engagify's variables and scoped look into a new `theme-gloss.css`. Engagify keeps its own stylesheet unchanged.

- Pro: Engagify stays exactly as shipped; no risk to the parody.
- Pro: Gloss can drift toward a calmer look without touching Engagify.
- Con: two stylesheets carry the same look, against the reuse rule in `AGENTS.md`. A fix in one is easily missed in the other.

## Option B — Gloss as the base, Engagify on top

**Estimate: 0.55**
Main driver: moving the shared look out of `theme-engagify.css` without changing how Engagify looks. Uncertainty: medium, in how a theme reuses another theme's stylesheet.

Gloss owns the look. Engagify becomes Gloss plus its nags, the gradient garnish and the "NEW" badge. Exit then removes exactly the parody layer and leaves the same site underneath.

- Pro: one source for the look, so fixes reach both themes.
- Pro: Exit reads as "the nags stop", which is the joke's payoff.
- Con: themes do not yet build on one another. Step 2 must pick the smallest way, possibly one stylesheet that serves both theme names.
- Con: Engagify needs a recheck at phone and desktop width after the move.

## Option C — Option B plus navigation icons

**Estimate: 0.70**
Main driver: an icon set drawn for every navigation destination. Uncertainty: medium to high, in drawing icons that fit the look.

Option B, plus Gloss icons in `public/assets/theme-nav-icons.css` for the Tools launcher, Tools subnavigation and the main navigation. This answers PF-20261104-F13 in full: a modern theme with icons.

- Pro: closes a requested feature, not just a related one.
- Con: icon drawing is most of the added work, and the icon sources need a dependency check if they come from outside.
- Con: Gloss would ship later for a feature that can follow on its own.

## Recommendation

Choose Option B. It keeps one source for the look and gives Exit the clearest meaning. Option C's icons can follow as a separate cycle once Gloss exists.

First vertical slice: Gloss selectable on ZenMemes, with Engagify unchanged on screen. Second slice: Exit from Engagify lands on Gloss.

Open questions for Step 2:

- The theme's name. Gloss is a working name.
- On a page theme thread, should Exit switch a reader who saved their own theme, such as Word 97, to Gloss? Recommendation: yes, as asked, but only for that page, so their saved theme stays theirs elsewhere.
- Whether Gloss replaces Engagify's "NEW" badge in the menu, or both carry none.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Revision 1 — Option A with icons

The maintainer chose Option A plus the icons from Option C. Gloss is a separate copy of the look, so Engagify's stylesheet stays exactly as shipped. This replaces the Option B recommendation. Option C is defined on top of B, so this revision takes only its icons.

**Estimate: 0.50** for Option A with the existing icon set; 0.75 if Gloss gets a new icon set.

The icons can come from two places:

- **Reuse the existing pixel-art set (recommended).** Add Gloss to the theme list at the top of `public/assets/theme-nav-icons.css`. PF-20261104-F13 asks for exactly this: "Whitehot's looks with Word97's icons". It is small, and every destination is already covered.
- **Draw a new set with smooth lines** to match the rounded look. It fits better, but drawing a glyph for every destination is most of the work and roughly doubles the cycle.

Option A's duplicate-stylesheet cost remains. Step 2 should say which of the two stylesheets owns a fix to the shared look.

The cycle button risk discussed after the handoff also applies here. Engagify sits in the cycle order, so one click from Gloss could start the nags if the two themes are neighbors. Open question for Step 2: keep Engagify out of the cycle button, or place Gloss away from it. The other open questions above stand.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Revision 2 — new icons as a separate cycle

The maintainer chose to draw new icons in a separate cycle. This cycle gives Gloss the existing pixel-art set, as recommended in Revision 1. The new set follows later, working ID `v3/gloss_icons`, and replaces the pixel-art icons for Gloss only. That cycle enrolls in the study at its first artifact.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Revision 3 — new icons in this cycle, drawn first

The maintainer reversed Revision 2: this cycle draws the new icon set itself, and draws it first. `v3/gloss_icons` is no longer a separate cycle. Gloss ships with its own icons and never carries the pixel-art set.

**Estimate: 0.75**

- First slice: a new icon set with smooth lines, one glyph for every navigation destination the pixel-art set covers. It is drawn against Engagify's palette, because Gloss shares that look. It stays unused until Gloss exists.
- Second slice: Gloss selectable on ZenMemes, with the new icons.
- Third slice: Exit from Engagify lands on Gloss.

If Step 3 finds the cycle above 1.0, the icons split back out as their own cycle. The open questions from Revision 1 still stand: the name, Exit on a page theme thread, the "NEW" badge, the cycle button, and which stylesheet owns shared fixes.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Revision 4 — decisions and current direction

The maintainer settled four open questions and opened one design question. This section is the current direction; read it before the earlier sections, which stay as history. Step 1 is **not approved**.

### Decided

- **Name.** The theme is called Gloss: label "Gloss", theme name `gloss`.
- **Exit.** Exit from Engagify always switches to Gloss. This holds both when the reader picked Engagify and on a page theme thread. It replaces the shipped rule, which restores the previous theme or the reader's saved theme.
- **"NEW" badge.** Engagify keeps its "NEW" badge in the theme menu. Gloss has no badge.
- **Cycle button.** The cycle button skips Engagify, from every theme. Engagify stays in the theme menu and still works as a page theme.

### Scope in this cycle

1. A new icon set with smooth lines, one glyph for every destination in the pixel-art set: main navigation, Tools launcher buttons, Tools subnavigation, launcher group headings, Bookmarklets buttons and the phone More panel. The set is drawn in Engagify's palette and stays unused until Gloss exists.
2. Gloss, selectable on ZenMemes, with Engagify's look minus the nags, the gradient garnish and the badge. Gloss uses the new icons.
3. Exit from Engagify lands on Gloss.
4. The cycle button skips Engagify.

Every new theme must pass the contrast audit and look right on every page at phone and desktop widths, not only on threads.

### Still open

- **Exit and the saved theme.** Does Exit save Gloss as the reader's theme, as a menu choice does? Or does Gloss apply to that page only, leaving a reader's saved theme, such as Word 97, in place elsewhere? The words "Exit goes into Gloss" suggest the first; Step 2 must confirm.
- **One theme with a setting, or two themes.** The maintainer asked whether Gloss and Engagify would be easier as one theme with an "Engagify" setting, like the LCD palette. Under discussion; see below. The answer may replace Option A.
- **Stylesheet ownership.** If the two themes stay separate copies, Step 2 says which stylesheet owns a fix to the shared look.

### One theme with a setting, compared

The LCD theme already works this way: one theme name, a palette control in the theme menu, and a separately stored palette that the cycle button steps through.

As one theme with a setting:

- Pro: one stylesheet for the look, so nothing drifts.
- Pro: Exit only turns the setting off, so it always lands on Gloss.
- Con: page themes map a page to a theme name, `engagify`. They would need to carry the setting as well, and so would a remembered Exit.
- Con: theme scripts load by theme name. The script would load for every Gloss reader and check the setting, which changes the theme script contract.
- Con: a stored setting can survive a theme switch. A reader who once turned Engagify on could get the nags back on picking Gloss later. That is the accidental start the maintainer worried about.
- Con: Engagify loses its own menu entry and its "NEW" badge moves into a sub-control, so the joke is harder to find.

As two themes, A or the shared stylesheet of B:

- Pro: page themes, Exit, theme scripts and the cycle skip all key on the theme name, which works today.
- Pro: the nags start only when the theme name is `engagify`.
- Con: Option A copies the look; Option B avoids the copy but needs one stylesheet to serve two theme names.

Claude's view: two themes are easier, because a setting changes three shipped contracts (page themes, theme scripts and Exit) to save one copied stylesheet. If the copy is the worry, Option B removes it without a setting. Awaiting the maintainer.

### Files a later step will touch

- `src/ForumRewrite/View/ThemeRegistry.php`: the theme list, labels, script flags and permitted themes.
- `public/assets/theme_toggle.js`: `forumExitTheme` for Exit and `nextTheme` for the cycle button.
- `public/assets/theme-engagify.css` and a new `public/assets/theme-gloss.css`.
- `public/assets/theme-nav-icons.css`: the icon rules, keyed by link; see the [navigation icons rule](../../../AGENTS.md#navigation-icons).
- Guides and concepts: `docs/runbooks/theme_development_guide.md`, [theme navigation icons](../../concepts/theme_navigation_icons.md), [page theme](../../concepts/page_theme.md) and [theme script](../../concepts/theme_script.md).
- Tests to extend: `ThemeRegistryTest`, `ThemeToggleScriptTest`, the theme allow-lists in `LocalAppSmokeTest`, `tests/browser/theme_cycle.cjs`, `tests/browser/theme_scripts.cjs`, `tests/browser/tools_launcher.mjs` and `tests/theme_contrast_audit.py`.

### Background for a new agent

- Engagify was built in `v3/engagify_theme` on branch `feat/engagify_theme`, which this cycle shares; it is not merged. See its [Step 4 summary](../engagify_theme/engagify_theme_step4_implementation_summary.md).
- The Exit pill is Engagify's only Exit control since commit `54ad7a658`; the nags no longer carry Exit links.
- Whitehot is the closest existing theme but differs in font, accent, scanlines and corners. That is why Exit waits for Gloss instead of switching to Whitehot.
- Feedback items: PF-20261104-F13 (modern theme with icons) and PF-20261030-F46 (rounder corners). Name both in later plans and commits.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Revision 5 — Option B, current direction

The maintainer chose Option B after the comparison in Revision 4: two theme names, one stylesheet for the shared look, and no Engagify setting. This section is the current direction and replaces the Revision 4 lists. Revision 4's comparison, file list and background still apply. Step 1 is **not approved**.

**Estimate: 0.90**

### Approach

Gloss owns the look: the lavender page, white rounded cards, pill buttons, violet accent, soft shadows and sans-serif font. Engagify is Gloss plus its parody layer: the nags from `theme-engagify.js`, the gradient garnish, the Exit pill and the "NEW" badge. Exit then removes exactly the parody layer and leaves the same site underneath.

The two stay separate theme names, `gloss` and `engagify`. Only the name `engagify` starts the theme script, so Gloss readers never see a nag. There is no Engagify setting.

### Decided

- **Name.** Label "Gloss", theme name `gloss`.
- **Exit.** Exit from Engagify always switches to Gloss, both when the reader picked Engagify and on a page theme thread.
- **"NEW" badge.** Engagify keeps it. Gloss has none.
- **Cycle button.** The cycle button skips Engagify, from every theme. Engagify stays in the theme menu and still works as a page theme.
- **One look.** Gloss and Engagify share one source for the look; a fix to it reaches both themes. This settles Revision 4's question about which stylesheet owns shared fixes.

### Scope in this cycle

1. A new icon set with smooth lines, one glyph for every destination in the pixel-art set: main navigation, Tools launcher buttons, Tools subnavigation, launcher group headings, Bookmarklets buttons and the phone More panel. The set is drawn in Gloss's palette.
2. Gloss, selectable on ZenMemes, with the new icons. The shared look moves out of `theme-engagify.css` into Gloss, and Engagify keeps only its parody layer.
3. Engagify looks the same as before the move, checked at phone and desktop widths with its nags open.
4. Exit from Engagify lands on Gloss.
5. The cycle button skips Engagify.

Gloss must pass the contrast audit and look right on every page at phone and desktop widths. Whether Engagify also shows the new icons is left to Step 2; the parody layer may hide them.

### Still open

- **Exit and the saved theme.** Does Exit save Gloss as the reader's theme, as a menu choice does? Or does Gloss apply to that page only, so a reader's saved theme, such as Word 97, stays in place elsewhere? Step 2 must confirm.
- **How one stylesheet serves two theme names.** Themes do not yet build on one another. Step 2 picks the smallest way, and the theme development guide documents it.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Revision 6 — open questions answered

The maintainer answered Revision 5's open questions. Revision 5 stays the current direction, amended by this section. Step 1 is **not approved**.

**Estimate: 0.90** (reaffirmed)

### Decided

- **Exit saves Gloss.** Exit from Engagify works like choosing Gloss in the theme menu: Gloss becomes the reader's saved theme on every page. On a page theme thread, Exit also records the page exit, as today, so a revisit shows Gloss. A reader who had saved Word 97 and then exits Engagify now has Gloss everywhere.
- **Engagify uses the new icons.** The new icon set applies to both theme names. The nags must not hide navigation icons any more than they hide the links today.
- **One stylesheet, two theme names.** Adopt Claude's recommendation below, subject to Step 1 approval.

### How one stylesheet serves two theme names

Today `ThemeRegistry.php` derives each theme's stylesheet from its name, `/assets/theme-<name>.css`. The server, the pre-paint script and `theme_toggle.js` each load exactly one stylesheet per theme. Three ways fit:

- **Shared file (recommended).** A registry entry may name another theme's stylesheet. Engagify names Gloss's, so both load `theme-gloss.css`. That file holds the shared look, written for both theme names, and the parody layer, written for `engagify` only. `theme-engagify.css` goes away. One registry field changes; the loaders, first paint and asset fingerprints keep working as now. Cost: Gloss readers download the small parody section unused, and an Engagify change refreshes Gloss's cached file.
- **Base theme.** Engagify declares Gloss as its base, and every loader fetches the base stylesheet before the theme's own. It keeps the files apart and suits future themes. Cost: the single theme `<link>`, the pre-paint script and `theme_toggle.js` all learn to load two files, which touches first paint.
- **CSS import.** `theme-engagify.css` imports `theme-gloss.css`. No loader changes, but the imported file skips the asset fingerprint, so long-lived caching serves stale CSS after a deploy, and the import delays first paint. Rejected.

Risk for Step 3: `theme_toggle.js` tracks a loaded stylesheet per theme name. Two theme names that share one file must not load it twice, or fail to mark it loaded for the second name. A browser check that switches Gloss to Engagify and back should cover this.

The theme development guide documents the new registry field: when to share a stylesheet, and how to scope each part.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Enhancement requests

- **Request:** Exit from Engagify goes into the visually closest theme. **Disposition and reason:** changed scope for the closed `v3/engagify_theme` cycle, whose approved Exit restores the previous theme; folded into this cycle, because the closest theme is Gloss once it exists. **Approval status:** pending Approved Step 1. **Outcome:** shared by every option above. An interim switch to Whitehot is possible as separate small work if the maintainer wants it before Gloss.
- **Request:** draw a new icon set for Gloss. **Disposition and reason:** separate work at the maintainer's request; this cycle reuses the existing set. **Approval status:** not started. **Outcome:** follow-up cycle `v3/gloss_icons`, no artifact yet. Superseded by Revision 3: folded back into this cycle as its first slice.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

- **Request:** the cycle button skips Engagify. **Disposition and reason:** changed scope for the closed `v3/engagify_theme` cycle; folded into this cycle in Revision 4. **Approval status:** pending Approved Step 1. **Outcome:** scope item 4 in Revision 4.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-07T12:45:05Z, `railspike-effort` / `v3/gloss_theme`, intake and Step 1 handoff. Scope v1: Option B as recommended above. Starting revision `54ad7a6580cc04d3553d6da92689eb2829401944` on `feat/engagify_theme`. Reference R1, protocol P2; frozen R1 hashes not re-verified for this checkpoint. Single exposed Claude rater. Pristine intake is missing: the rater built Engagify in this session and read every theme's variables, `theme_toggle.js` Exit handling and the two feedback items before rating. Intake and Step 1 projected total **0.55**. Option estimates 0.45, 0.55 and 0.70 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

2026-11-07T12:51:40Z, Revision 1 checkpoint: the maintainer chose Option A plus Option C's icons. Scope v2: Option A, Gloss in the existing navigation icon set, Exit from Engagify lands on Gloss. Projected total 0.55 → **0.50**, reason changed scope: no stylesheet split for Engagify, and icons reuse the existing set. A new icon set would be 0.75. Same revision, R1/P2 and single exposed Claude rater. No approval implied.

2026-11-07T12:52:34Z, Revision 2 checkpoint: the maintainer moved the new icon set to a separate cycle, `v3/gloss_icons`. Scope v2 unchanged: this cycle reuses the existing set. Projected total **0.50** reaffirmed. Same revision, R1/P2 and single exposed Claude rater. No approval implied.

2026-11-07T12:54:29Z, Revision 3 checkpoint: the maintainer folded the new icon set back into this cycle, drawn first. `v3/gloss_icons` withdrawn before any artifact. Scope v3: Option A, a new icon set, Gloss with those icons, Exit from Engagify lands on Gloss. Projected total 0.50 → **0.75**, reason changed scope. Same revision, R1/P2 and single exposed Claude rater. No approval implied.

2026-11-07T12:57:11Z, Revision 4 checkpoint: the maintainer named the theme Gloss, made Exit always land on Gloss, kept Engagify's badge, and asked the cycle button to skip Engagify. One theme with a setting is under discussion. Scope v4: Revision 3 plus the cycle skip. Projected total 0.75 → **0.80**, reason changed scope (cycle skip, Exit on page theme threads). If the maintainer picks one theme with a setting, re-estimate. Same revision, R1/P2 and single exposed Claude rater. No approval implied.

2026-11-07T12:59:10Z, Revision 5 checkpoint: the maintainer chose Option B (two theme names, one shared look) over Option A and over one theme with a setting. Scope v5: Option B, new icon set, Exit to Gloss, cycle button skips Engagify. Projected total 0.80 → **0.90**, reason changed scope: moving the shared look out of Engagify and rechecking Engagify, offset slightly by no duplicate stylesheet. Same revision, R1/P2 and single exposed Claude rater. No approval implied.

2026-11-07T13:01:04Z, Revision 6 checkpoint: the maintainer decided Exit saves Gloss as the reader's theme everywhere and Engagify uses the new icons. Claude recommended one shared stylesheet named by both registry entries. Scope v6: Revision 5 with those answers. Projected total **0.90** reaffirmed: the shared file is one registry field and a merge of two stylesheets, and Exit saving Gloss is simpler than a page-only rule. Same revision, R1/P2 and single exposed Claude rater. No approval implied.

Step 1 approved by the maintainer on 2026-11-07 ("approved step 1"), with Revisions 1–6.
